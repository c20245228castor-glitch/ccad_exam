<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $username = config('admin.username');
        $admin = User::query()->firstOrNew(['username' => $username]);
        $password = config('admin.password');

        if (! $admin->exists && (! is_string($password) || $password === '')) {
            throw new RuntimeException('Set ADMIN_PASSWORD before creating the administrator account.');
        }

        $admin->forceFill([
            'name' => 'Administrator',
            'email' => config('admin.email'),
            'role' => 'admin',
        ]);

        if (! $admin->exists) {
            $admin->password = Hash::make($password);
        }

        $admin->save();
    }
}
