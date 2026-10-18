<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuthManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Open the Vice President for Administration's ClubSync account (POLICY.md §Event
 * Creation): the account that approves room booking letters, run by the VPA or by the
 * SSG for them.
 *
 * Creates the Firebase sign-in and the users row with the `vpa` role. An existing
 * account with that address is given the role instead, keeping its password. A new
 * account's password is printed once — pass --password to choose it, or one is made up.
 */
class CreateVpaAccount extends Command
{
    protected $signature = 'app:create-vpa
        {--email=vpa@sccpag.edu.ph : The account\'s SCC address}
        {--first-name=VP for : First name, as the account shows in ClubSync}
        {--last-name=Administration : Last name, as the account shows in ClubSync}
        {--password= : Password for a new account (made up when left out)}
        {--firebase-uid= : Use this existing Firebase account instead of creating one}';

    protected $description = 'Create the Vice President for Administration account that approves room booking letters';

    public function handle(AuthManager $auth): int
    {
        $email = Str::lower(trim((string) $this->option('email')));

        // POLICY.md §Authentication: institutional addresses only.
        if (! Str::endsWith($email, '@sccpag.edu.ph') || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Use an @sccpag.edu.ph address.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user?->firebase_uid) {
            $old = $user->role;
            $user->update(['role' => 'vpa', 'is_suspended' => false]);

            AuditLog::record('admin.user.role_changed', $user, ['target_email' => $email, 'old_role' => $old, 'new_role' => 'vpa'], null);

            $this->info("{$email} already has an account. Its role is now vpa; its password is unchanged.");

            return self::SUCCESS;
        }

        $password = (string) ($this->option('password') ?: Str::password(14, symbols: false));
        $uid      = $this->option('firebase-uid') ?: null;

        if (! $uid) {
            try {
                $uid = $auth->registerWithFirebase($email, $password)['localId'] ?? null;
            } catch (\RuntimeException $e) {
                $this->error('Firebase did not create the account: ' . $e->getMessage());

                if ($e->getMessage() === 'EMAIL_EXISTS') {
                    $this->warn('The address already has a Firebase account. Find its UID in the Firebase console and run this again with --firebase-uid=…');
                }

                return self::FAILURE;
            }
        }

        $user ??= new User(['email' => $email]);
        $user->fill([
            'first_name'   => (string) $this->option('first-name'),
            'last_name'    => (string) $this->option('last-name'),
            'password'     => Hash::make($password),
            'role'         => 'vpa',
            'is_admin'     => false,
            'is_suspended' => false,
            'firebase_uid' => $uid,
        ])->save();

        AuditLog::record('admin.vpa_account.created', $user, ['target_email' => $email], null);

        $this->newLine();
        $this->info('✓ Vice President for Administration account ready.');
        $this->table(['Field', 'Value'], [
            ['Email', $email],
            ['Password', $this->option('firebase-uid') ? '(the existing Firebase password)' : $password],
            ['Role', 'vpa'],
            ['Signs in at', rtrim((string) config('app.url'), '/') . '/login'],
        ]);
        $this->warn('Save the password now — it is not shown again. It can be changed from the profile page.');

        return self::SUCCESS;
    }
}
