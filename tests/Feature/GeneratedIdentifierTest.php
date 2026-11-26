<?php

namespace Tests\Feature;

use App\Services\ConsumerService;
use App\Services\WaterMeterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

    public function test_a_consumer_cannot_be_assigned_a_second_meter(): void
    {
        $consumer = (new ConsumerService)->create([
            'consumer_name' => 'John',
            'consumer_address' => 'Main Street',
        ]);
        $meterService = new WaterMeterService;
        $meterService->create([
            'consumer_id' => $consumer->consumer_id,
            'is_active' => true,
        ]);

        try {
            $meterService->create([
                'consumer_id' => $consumer->consumer_id,
                'is_active' => true,
            ]);

            $this->fail('A second meter was created for the same consumer.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This consumer already has a water meter.',
                $exception->errors()['consumer_id'][0]
            );
        }
    }

    public function test_a_meter_cannot_be_reassigned_to_a_consumer_who_already_has_one(): void
    {
        $consumerService = new ConsumerService;
        $firstConsumer = $consumerService->create([
            'consumer_name' => 'First Consumer',
            'consumer_address' => '1 Main Street',
        ]);
        $secondConsumer = $consumerService->create([
            'consumer_name' => 'Second Consumer',
            'consumer_address' => '2 Main Street',
        ]);
        $meterService = new WaterMeterService;
        $firstMeter = $meterService->create([
            'consumer_id' => $firstConsumer->consumer_id,
            'is_active' => true,
        ]);
        $meterService->create([
            'consumer_id' => $secondConsumer->consumer_id,
            'is_active' => true,
        ]);

        try {
            $meterService->update($firstMeter, [
                'consumer_id' => $secondConsumer->consumer_id,
            ]);

            $this->fail('A meter was reassigned to a consumer who already has one.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This consumer already has a water meter.',
                $exception->errors()['consumer_id'][0]
            );
        }

        $this->assertSame($firstConsumer->consumer_id, $firstMeter->refresh()->consumer_id);
    }
}
