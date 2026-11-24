<?php

namespace Tests\Feature;

use App\Services\ConsumerService;
use App\Services\WaterMeterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneratedIdentifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_consumer_account_numbers_and_meter_serials(): void
    {
        $consumer = (new ConsumerService)->create([
            'consumer_name' => 'John',
            'consumer_address' => 'Main Street',
        ]);
        $meter = (new WaterMeterService)->create([
            'consumer_id' => $consumer->consumer_id,
            'is_active' => true,
        ]);

        $this->assertSame('ACC-1001', $consumer->account_number);
        $this->assertSame('WM-1001', $meter->meter_serial);
        $this->assertDatabaseHas('consumers', [
            'consumer_id' => $consumer->consumer_id,
            'account_number' => 'ACC-1001',
        ]);
        $this->assertDatabaseHas('water_meters', [
            'meter_id' => $meter->meter_id,
            'meter_serial' => 'WM-1001',
        ]);
    }

    public function test_generated_identifiers_cannot_be_overridden_on_create_or_update(): void
    {
        $consumerService = new ConsumerService;
        $consumer = $consumerService->create([
            'account_number' => 'CUSTOM-ACCOUNT',
            'consumer_name' => 'John',
            'consumer_address' => 'Main Street',
        ]);
        $meterService = new WaterMeterService;
        $meter = $meterService->create([
            'consumer_id' => $consumer->consumer_id,
            'meter_serial' => 'CUSTOM-SERIAL',
            'is_active' => true,
        ]);

        $consumerService->update($consumer, [
            'account_number' => 'CHANGED-ACCOUNT',
            'consumer_name' => 'John Doe',
        ]);
        $meterService->update($meter, [
            'meter_serial' => 'CHANGED-SERIAL',
            'is_active' => false,
        ]);

        $this->assertSame('ACC-1001', $consumer->refresh()->account_number);
        $this->assertSame('WM-1001', $meter->refresh()->meter_serial);
    }
}
