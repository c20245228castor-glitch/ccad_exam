<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'username' => 'test-user',
            'email' => 'test@example.com',
            'consumer_address' => '123 Test Street',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'username' => 'test-user',
            'role' => 'user',
        ]);
        $this->assertDatabaseHas('consumers', [
            'account_number' => 'ACC-1001',
            'consumer_name' => 'Test User',
            'consumer_address' => '123 Test Street',
        ]);
    }

    public function test_registration_cannot_assign_the_admin_role(): void
    {
        $this->post('/register', [
            'name' => 'Pretend Admin',
            'username' => 'pretend-admin',
            'email' => 'pretend-admin@example.com',
            'consumer_address' => '1 Example Road',
            'role' => 'admin',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'username' => 'pretend-admin',
            'role' => 'user',
        ]);
    }
}
