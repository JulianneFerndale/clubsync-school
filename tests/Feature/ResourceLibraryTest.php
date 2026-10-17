<?php

use App\Models\AuditLog;
use App\Models\ChedMemorandum;
use App\Models\ResourceDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * CHED memorandums and the downloadable club handbook / report templates, which
 * replaced the per-activity CHED reports at the client's request (2026-09-22).
 * The administrator maintains them; advisers, officers and the DSA read and download
 * them. Student members have no access.
 */

beforeEach(fn () => Storage::fake('local'));

function libUser(string $role, bool $isAdmin = false): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Lib',
        'last_name'    => "User{$n}",
        'edp_number'   => str_pad((string) (9100 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "lib{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Password1!'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => $isAdmin,
        'is_suspended' => false,
    ]);
}

function libSession(User $user): array
{
    $header  = rtrim(strtr(base64_encode(json_encode(['alg' => 'none'])), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode(json_encode(['exp' => time() + 3600])), '+/', '-_'), '=');

    return [
        'firebase_id_token'  => "{$header}.{$payload}.signature",
        'firebase_uid'       => 'lib-uid-' . $user->id,
        'firebase_user_role' => $user->role,
        'firebase_user_id'   => $user->id,
        'firebase_is_admin'  => $user->is_admin,
    ];
}

function libMemoInput(array $overrides = []): array
{
    return array_merge([
        'type'        => 'order',
        'memo_number' => '09',
        'series_year' => 2013,
        'title'       => 'Enhanced Policies and Guidelines on Student Affairs and Services',
        'summary'     => 'Sets the standards for student organizations and activities.',
        'date_issued' => '2013-03-26',
        'status'      => 'in_effect',
        'file'        => UploadedFile::fake()->create('cmo-09-2013.pdf', 300, 'application/pdf'),
    ], $overrides);
}

function libMemo(User $admin, array $overrides = []): ChedMemorandum
{
    test()->withSession(libSession($admin))
        ->post(route('admin.ched-memos.store'), libMemoInput($overrides))
        ->assertSessionHasNoErrors();

    return ChedMemorandum::latest('id')->firstOrFail();
}

// ── CHED memorandums (admin) ─────────────────────────────────────────────────

it('lets an admin add a CHED memorandum with its PDF and audit-logs it', function () {
    $admin = libUser('dsa', isAdmin: true);

    $this->withSession(libSession($admin))
        ->post(route('admin.ched-memos.store'), libMemoInput())
        ->assertRedirect(route('admin.ched-memos.index'));

    $memo = ChedMemorandum::firstOrFail();

    expect($memo->citation)->toBe('CMO No. 09, s. 2013')
        ->and($memo->uploaded_by)->toBe($admin->id)
        ->and($memo->file_name)->toBe('cmo-09-2013.pdf');

    Storage::disk('local')->assertExists($memo->file_path);

    $entry = AuditLog::where('action', 'admin.ched_memo.created')->first();
    expect($entry)->not->toBeNull()
        ->and($entry->actor_id)->toBe($admin->id)
        ->and($entry->resource_id)->toBe($memo->id);
});

it('keeps the admin pages away from users who are not admins', function () {
    $dsa = libUser('dsa');

    $this->withSession(libSession($dsa))->get(route('admin.ched-memos.index'))->assertForbidden();
    $this->withSession(libSession($dsa))->post(route('admin.ched-memos.store'), libMemoInput())->assertForbidden();
    $this->withSession(libSession($dsa))->get(route('admin.resources.index'))->assertForbidden();

    expect(ChedMemorandum::count())->toBe(0);
});

it('refuses the same memorandum twice but allows the same number in another series', function () {
    $admin = libUser('dsa', isAdmin: true);
    libMemo($admin);

    $this->withSession(libSession($admin))
        ->post(route('admin.ched-memos.store'), libMemoInput())
        ->assertSessionHasErrors('memo_number');

    $this->withSession(libSession($admin))
        ->post(route('admin.ched-memos.store'), libMemoInput(['series_year' => 2024]))
        ->assertSessionHasNoErrors();

    expect(ChedMemorandum::count())->toBe(2);
});

it('only accepts a PDF for a memorandum', function () {
    $admin = libUser('dsa', isAdmin: true);

    $this->withSession(libSession($admin))
        ->post(route('admin.ched-memos.store'), libMemoInput([
            'file' => UploadedFile::fake()->create('memo.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ]))
        ->assertSessionHasErrors('file');

    $this->withSession(libSession($admin))
        ->post(route('admin.ched-memos.store'), libMemoInput(['file' => null]))
        ->assertSessionHasErrors('file');

    expect(ChedMemorandum::count())->toBe(0);
});

it('rejects an effective date before the issue date', function () {
    $admin = libUser('dsa', isAdmin: true);

    $this->withSession(libSession($admin))
        ->post(route('admin.ched-memos.store'), libMemoInput(['effective_date' => '2013-01-01']))
        ->assertSessionHasErrors('effective_date');
});

it('replaces the PDF on update and removes the old file', function () {
    $admin = libUser('dsa', isAdmin: true);
    $memo  = libMemo($admin);
    $old   = $memo->file_path;

    $this->withSession(libSession($admin))
        ->patch(route('admin.ched-memos.update', $memo), libMemoInput([
            'status' => 'superseded',
            'file'   => UploadedFile::fake()->create('cmo-09-2013-signed.pdf', 200, 'application/pdf'),
        ]))
        ->assertRedirect(route('admin.ched-memos.index'));

    $memo->refresh();

    expect($memo->status)->toBe('superseded')
        ->and($memo->file_name)->toBe('cmo-09-2013-signed.pdf');

    Storage::disk('local')->assertMissing($old);
    Storage::disk('local')->assertExists($memo->file_path);

    $entry = AuditLog::where('action', 'admin.ched_memo.updated')->first();
    expect($entry->metadata['fields'])->toContain('status', 'file_name');
});

it('keeps the PDF when an update does not attach a new one', function () {
    $admin = libUser('dsa', isAdmin: true);
    $memo  = libMemo($admin);
    $path  = $memo->file_path;

    $this->withSession(libSession($admin))
        ->patch(route('admin.ched-memos.update', $memo), libMemoInput(['title' => 'Corrected title', 'file' => null]))
        ->assertSessionHasNoErrors();

    expect($memo->fresh()->file_path)->toBe($path)
        ->and($memo->fresh()->title)->toBe('Corrected title');
    Storage::disk('local')->assertExists($path);
});

it('removes a memorandum and its PDF and audit-logs it', function () {
    $admin = libUser('dsa', isAdmin: true);
    $memo  = libMemo($admin);

    $this->withSession(libSession($admin))
        ->delete(route('admin.ched-memos.destroy', $memo))
        ->assertRedirect(route('admin.ched-memos.index'));

    expect(ChedMemorandum::count())->toBe(0)
        ->and(AuditLog::where('action', 'admin.ched_memo.deleted')->where('resource_id', $memo->id)->exists())->toBeTrue();
    Storage::disk('local')->assertMissing($memo->file_path);
});

it('renders the admin memorandum pages', function () {
    $admin = libUser('dsa', isAdmin: true);
    $memo  = libMemo($admin);

    $this->withSession(libSession($admin))->get(route('admin.ched-memos.index'))
        ->assertOk()->assertSee('CMO No. 09, s. 2013');
    $this->withSession(libSession($admin))->get(route('admin.ched-memos.index', ['search' => 'nothing-matches']))
        ->assertOk()->assertSee('No memorandums match your search.');
    $this->withSession(libSession($admin))->get(route('admin.ched-memos.create'))->assertOk();
    $this->withSession(libSession($admin))->get(route('admin.ched-memos.edit', $memo))
        ->assertOk()->assertSee('cmo-09-2013.pdf');
});

// ── Handbook & templates (admin) ─────────────────────────────────────────────

it('renders the admin handbook & templates pages', function () {
    $admin = libUser('dsa', isAdmin: true);
    $this->withSession(libSession($admin))->post(route('admin.resources.store'), [
        'category' => 'template',
        'title'    => 'Activity Proposal Template',
        'file'     => UploadedFile::fake()->create('proposal.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ]);
    $document = ResourceDocument::firstOrFail();

    $this->withSession(libSession($admin))->get(route('admin.resources.index'))
        ->assertOk()->assertSee('Activity Proposal Template');
    $this->withSession(libSession($admin))->get(route('admin.resources.edit', $document))
        ->assertOk()->assertSee('proposal.docx');
});

it('lets an admin publish the handbook and a Word template', function () {
    $admin = libUser('dsa', isAdmin: true);

    $this->withSession(libSession($admin))->post(route('admin.resources.store'), [
        'category' => 'handbook',
        'title'    => 'SCC Club Handbook 2026-2027',
        'file'     => UploadedFile::fake()->create('handbook.pdf', 1500, 'application/pdf'),
    ])->assertRedirect(route('admin.resources.index'));

    $this->withSession(libSession($admin))->post(route('admin.resources.store'), [
        'category'    => 'template',
        'title'       => 'Accomplishment Report Template',
        'description' => 'Submitted after every activity.',
        'file'        => UploadedFile::fake()->create('accomplishment.docx', 80, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ])->assertSessionHasNoErrors();

    expect(ResourceDocument::handbooks()->count())->toBe(1)
        ->and(ResourceDocument::templates()->count())->toBe(1)
        ->and(AuditLog::where('action', 'admin.resource.created')->count())->toBe(2);
});

it('refuses files that are not documents', function () {
    $admin = libUser('dsa', isAdmin: true);

    $this->withSession(libSession($admin))->post(route('admin.resources.store'), [
        'category' => 'template',
        'title'    => 'Not a template',
        'file'     => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
    ])->assertSessionHasErrors('file');

    expect(ResourceDocument::count())->toBe(0);
});

it('removes a published file from storage', function () {
    $admin = libUser('dsa', isAdmin: true);
    $this->withSession(libSession($admin))->post(route('admin.resources.store'), [
        'category' => 'template',
        'title'    => 'Minutes of Meeting',
        'file'     => UploadedFile::fake()->create('minutes.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ]);
    $document = ResourceDocument::firstOrFail();

    $this->withSession(libSession($admin))
        ->delete(route('admin.resources.destroy', $document))
        ->assertRedirect(route('admin.resources.index'));

    expect(ResourceDocument::count())->toBe(0);
    Storage::disk('local')->assertMissing($document->file_path);
});

// ── Library (advisers, officers, DSA) ────────────────────────────────────────

it('shows the library to advisers, officers and the DSA and lets them download', function (string $role) {
    $admin = libUser('dsa', isAdmin: true);
    $memo  = libMemo($admin);
    $this->withSession(libSession($admin))->post(route('admin.resources.store'), [
        'category' => 'handbook',
        'title'    => 'SCC Club Handbook',
        'file'     => UploadedFile::fake()->create('handbook.pdf', 100, 'application/pdf'),
    ]);
    $handbook = ResourceDocument::firstOrFail();

    $user = libUser($role);

    $this->withSession(libSession($user))->get(route('resources.index'))
        ->assertOk()
        ->assertSee('SCC Club Handbook');

    $this->withSession(libSession($user))->get(route('resources.index', ['tab' => 'memos']))
        ->assertOk()
        ->assertSee('CMO No. 09, s. 2013');

    $this->withSession(libSession($user))->get(route('resources.memos.download', $memo))
        ->assertOk()
        ->assertDownload('CMO-No-09-s-2013.pdf');

    $this->withSession(libSession($user))->get(route('resources.documents.download', $handbook))
        ->assertOk()
        ->assertDownload('handbook.pdf');
})->with(['president', 'treasurer', 'mmo', 'adviser', 'dsa']);

it('keeps student members out of the library, downloads included', function () {
    $admin = libUser('dsa', isAdmin: true);
    $memo  = libMemo($admin);
    $this->withSession(libSession($admin))->post(route('admin.resources.store'), [
        'category' => 'handbook',
        'title'    => 'SCC Club Handbook',
        'file'     => UploadedFile::fake()->create('handbook.pdf', 100, 'application/pdf'),
    ]);
    $handbook = ResourceDocument::firstOrFail();

    $member = libUser('member');

    $this->withSession(libSession($member))->get(route('resources.index'))->assertForbidden();
    $this->withSession(libSession($member))->get(route('resources.memos.download', $memo))->assertForbidden();
    $this->withSession(libSession($member))->get(route('resources.documents.download', $handbook))->assertForbidden();
});

it('does not offer the Resources tab in the member layout', function () {
    $this->withSession(libSession(libUser('member')))
        ->get(route('member.dashboard'))
        ->assertOk()
        ->assertDontSee('href="' . route('resources.index') . '"', false);
});

it('still lets the administrator see the library, whatever their role', function () {
    // The seeded administrator's role is 'member'; they upload what the library shows.
    $this->withSession(libSession(libUser('member', isAdmin: true)))
        ->get(route('resources.index'))
        ->assertOk();
});

it('keeps the library behind sign-in', function () {
    $this->get(route('resources.index'))->assertRedirect();
});

it('lists memorandums in effect before superseded ones', function () {
    $admin = libUser('dsa', isAdmin: true);
    libMemo($admin, ['memo_number' => '01', 'series_year' => 2025, 'title' => 'Newer but superseded', 'status' => 'superseded', 'date_issued' => '2025-01-10']);
    libMemo($admin, ['memo_number' => '09', 'series_year' => 2013, 'title' => 'Older but in effect']);

    $this->withSession(libSession(libUser('adviser')))
        ->get(route('resources.index', ['tab' => 'memos']))
        ->assertSeeInOrder(['Older but in effect', 'Newer but superseded']);
});

// ── CHED reports are retired ─────────────────────────────────────────────────

it('no longer offers the per-activity CHED reports', function () {
    expect(Route::has('dsa.reports.index'))->toBeFalse()
        ->and(Route::has('officer.reports.finalize'))->toBeFalse();
});
