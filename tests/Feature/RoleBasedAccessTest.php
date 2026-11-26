<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use App\Models\WaterBill;
use App\Models\WaterMeter;
use App\Models\WaterRate;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RoleBasedAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_management_routes_are_restricted_to_admins(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/consumers')->assertForbidden();
        $this->actingAs($user)->get('/payments')->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)->get('/consumers')->assertOk();
        $this->actingAs($admin)->get('/payments')->assertOk();
    }

    public function test_admin_dashboard_shows_registered_consumer_account_numbers(): void
    {
        $this->post('/register', [
            'name' => 'Registered Customer',
            'username' => 'registered-customer',
            'email' => 'registered@example.com',
            'consumer_address' => '1 Water Street',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        auth()->logout();

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('registeredConsumers.0.consumer_name', 'Registered Customer')
                ->where('registeredConsumers.0.account_number', 'ACC-1001'));
    }

    public function test_consumer_can_make_a_partial_payment_on_only_their_own_bill(): void
    {
        [$user, $bill] = $this->createConsumerWithBill('cash-customer');
        [, $otherBill] = $this->createConsumerWithBill('other-customer');
        $this->assertSame('user', $user->fresh()->role);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->component('consumer/dashboard')
                ->where('bills', fn ($bills) => count($bills) === 1 && $bills[0]['bill_id'] === $bill->bill_id));

        $this->actingAs($user)
            ->post(route('consumer.bills.pay', $bill->bill_id), [
                'amount_paid' => '12.00',
                'payment_method' => 'gcash',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('water_bills', [
            'bill_id' => $bill->bill_id,
            'status' => 'unpaid',
        ]);
        $this->assertDatabaseHas('payments', [
            'bill_id' => $bill->bill_id,
            'amount_paid' => '12.00',
            'payment_method' => 'gcash',
        ]);
        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('bills.0.amount_due', 8));

        $this->actingAs($user)
            ->post(route('consumer.bills.pay', $otherBill->bill_id), [
                'amount_paid' => '12.00',
                'payment_method' => 'gcash',
            ])
            ->assertNotFound();

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)
            ->get('/payments')
            ->assertInertia(fn ($page) => $page
                ->where('payments.data.0.payment_method', 'gcash'));
    }

    public function test_admin_seed_credentials_are_fixed_and_admin_cannot_change_password(): void
    {
        config(['admin.password' => 'test-admin-password']);
        $this->seed();

        $this->post('/login', [
            'login' => 'Admin1',
            'password' => 'test-admin-password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $admin = User::query()->where('username', 'Admin1')->firstOrFail();
        $this->assertSame('admin', $admin->role);

        $this->actingAs($admin)
            ->patch('/settings/profile', [
                'name' => 'Changed Administrator',
                'email' => 'changed-admin@example.com',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->put('/settings/password', [
                'current_password' => 'test-admin-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertForbidden();

        Notification::fake();
        Auth::logout();
        $this->post('/forgot-password', ['email' => $admin->email])
            ->assertRedirect();
        Notification::assertNotSentTo($admin, ResetPassword::class);
    }

    /**
     * @return array{User, WaterBill}
     */
    private function createConsumerWithBill(string $username): array
    {
        $user = User::factory()->create(['username' => $username]);
        $consumer = Consumer::create([
            'account_number' => 'ACC-'.strtoupper($username),
            'consumer_name' => $username,
            'consumer_address' => '1 Test Street',
        ]);
        $user->forceFill(['consumer_id' => $consumer->consumer_id])->save();

        $meter = WaterMeter::create([
            'consumer_id' => $consumer->consumer_id,
            'meter_serial' => 'WM-'.strtoupper($username),
            'is_active' => true,
        ]);
        $rate = WaterRate::create([
            'rate_per_cu_m' => 10,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);
        $bill = WaterBill::create([
            'meter_id' => $meter->meter_id,
            'rate_id' => $rate->rate_id,
            'previous_reading' => 0,
            'current_reading' => 1,
            'consumption_cu_m' => 1,
            'billing_date' => '2026-11-01',
            'due_date' => '2026-11-30',
            'total_water_bill' => 20,
            'penalty_fee' => 0,
            'status' => 'unpaid',
        ]);

        return [$user, $bill];
    }
}
