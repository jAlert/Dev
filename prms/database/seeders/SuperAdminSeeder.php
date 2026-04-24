<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'super admin']);

        $email    = env('ADMIN_EMAIL')    or abort(1, 'ADMIN_EMAIL not set in .env');
        $password = env('ADMIN_PASSWORD') or abort(1, 'ADMIN_PASSWORD not set in .env');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Super Admin'),
                'password' => bcrypt($password),
                'email_verified_at' => now(),
            ]
        );

        $user->assignRole($role);
    }
}
