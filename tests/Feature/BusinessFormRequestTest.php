<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessFormRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_business_records(): void
    {
        $this->post('/consumers', [
            'consumer_name' => 'John',
            'consumer_address' => 'Main Street',
        ])->assertRedirect('/login');

        $this->assertDatabaseCount('consumers', 0);
    }

    public function test_consumer_request_returns_custom_validation_messages(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)
            ->post('/consumers', [
                'consumer_name' => '',
                'consumer_address' => '',
            ])
            ->assertSessionHasErrors([
                'consumer_name' => 'The consumer name is required.',
                'consumer_address' => 'The consumer address is required.',
            ]);
    }
}
