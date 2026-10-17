<?php

use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The registration pages show each college's own seal (public/images/departments),
 * and fall back to its short name for a college with no seal on file.
 */

function dsDepartment(string $short, string $name): Department
{
    return Department::create(['slug' => 'dept_' . strtolower($short), 'name' => $name, 'short_name' => $short]);
}

it('has a seal for each of the four SCC colleges', function (string $short, string $file) {
    expect(dsDepartment($short, "College {$short}")->logo_url)->toBe("/images/departments/{$file}")
        ->and(public_path("images/departments/{$file}"))->toBeFile();
})->with([
    'CCS'   => ['CCS', 'ccs.png'],
    'CTEAS' => ['CTEAS', 'cteas.png'],
    'CBE'   => ['CBE', 'cbe.png'],
    'COC'   => ['COC', 'coc.png'],
]);

it('shows the seals on the department step, and the short name for a college without one', function () {
    dsDepartment('CCS', 'College of Computing Studies');
    dsDepartment('XYZ', 'College of Something New');

    $this->withSession(['register.email' => 'seal.tester@sccpag.edu.ph'])
        ->get(route('register.department'))
        ->assertOk()
        ->assertSee('src="/images/departments/ccs.png"', false)
        ->assertSee('alt="CCS seal"', false)
        ->assertDontSee('xyz.png')
        ->assertSee('XYZ');
});

it('shows the chosen college\'s seal on the course step', function () {
    $ccs = dsDepartment('CCS', 'College of Computing Studies');

    $this->withSession(['register.email' => 'seal.tester@sccpag.edu.ph', 'register.department_id' => $ccs->id])
        ->get(route('register.course'))
        ->assertOk()
        ->assertSee('src="/images/departments/ccs.png"', false);
});
