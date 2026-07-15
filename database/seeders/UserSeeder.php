<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Seed the Super Admin account, plus one demo account per role outside production.
     */
    public function run(): void
    {
        $this->createSuperAdmin();

        if (app()->isProduction()) {
            $this->command->info('Production environment: demo accounts skipped.');

            return;
        }

        $this->createDemoAccounts();
    }

    private function createSuperAdmin(): void
    {
        $email = (string) config('praktika.seed.super_admin_email');
        $password = config('praktika.seed.super_admin_password');

        if (app()->isProduction() && blank($password)) {
            throw new RuntimeException(
                'PRAKTIKA_SUPER_ADMIN_PASSWORD must be set before seeding in production.'
            );
        }

        $user = User::withTrashed()->firstOrNew(['email' => $email]);

        // Never silently reset the password of an account that already exists.
        if (! $user->exists) {
            $user->fill([
                'name' => 'Super Admin',
                'password' => Hash::make((string) ($password ?? 'password')),
                'is_active' => true,
            ]);
            $user->email_verified_at = now();
            $user->save();
        }

        $user->syncRoles([RoleName::SuperAdmin->value]);
    }

    private function createDemoAccounts(): void
    {
        $demoAccounts = [
            [RoleName::AdminPkl, 'Admin PKL', 'admin@praktika.test'],
            [RoleName::GuruPembimbing, 'Guru Pembimbing', 'guru@praktika.test'],
            [RoleName::PembimbingIndustri, 'Pembimbing Industri', 'industri@praktika.test'],
            [RoleName::Siswa, 'Siswa', 'siswa@praktika.test'],
        ];

        foreach ($demoAccounts as [$role, $name, $email]) {
            $user = User::firstOrNew(['email' => $email]);

            if (! $user->exists) {
                $user->fill([
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ]);
                $user->email_verified_at = now();
                $user->save();
            }

            $user->syncRoles([$role->value]);
        }
    }
}
