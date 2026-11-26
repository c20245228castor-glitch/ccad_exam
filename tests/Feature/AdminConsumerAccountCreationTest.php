<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminConsumerAccountCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_consumer_with_a_linked_login_account(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)
            ->post('/consumers', [
                'consumer_name' => 'New Consumer',
                'consumer_address' => '1 Water Street',
                'email' => 'New.Consumer@Gmail.com',
                'password' => 'consumer-password',
                'password_confirmation' => 'consumer-password',
            ])
            ->assertRedirect();

        $consumer = Consumer::query()->where('consumer_name', 'New Consumer')->firstOrFail();
        $user = $consumer->user()->firstOrFail();

        $this->assertSame('new.consumer@gmail.com', $user->email);
        $this->assertSame('user', $user->role);
        $this->assertSame($consumer->consumer_id, $user->consumer_id);
        $this->assertTrue(Hash::check('consumer-password', $user->password));
    }

    public function test_admin_must_provide_consumer_login_credentials(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)
            ->post('/consumers', [
                'consumer_name' => 'New Consumer',
                'consumer_address' => '1 Water Street',
            ])
            ->assertSessionHasErrors(['email', 'password', 'password_confirmation']);

        $this->assertDatabaseCount('consumers', 0);
    }

    public function test_admin_cannot_reuse_an_email_or_submit_mismatched_passwords(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        User::factory()->create(['email' => 'taken@gmail.com']);

        $this->actingAs($admin)
            ->post('/consumers', [
                'consumer_name' => 'New Consumer',
                'consumer_address' => '1 Water Street',
                'email' => 'taken@gmail.com',
                'password' => 'consumer-password',
                'password_confirmation' => 'another-password',
            ])
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertDatabaseCount('consumers', 0);
    }
}
