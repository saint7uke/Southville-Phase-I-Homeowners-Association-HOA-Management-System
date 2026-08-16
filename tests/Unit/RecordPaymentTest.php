<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\Payments\RecordPayment;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RecordPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_and_status_are_calculated_on_the_server(): void
    {
        $recorder = User::factory()->create();
        $resident = User::factory()->create();
        $homeowner = Homeowner::query()->create(['user_id' => $resident->id, 'house_number' => '8', 'street' => 'Narra', 'residency_date' => '2021-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);
        $dues = DuesSetting::query()->create(['name' => 'Monthly dues', 'amount' => 500, 'frequency' => 'Monthly', 'is_active' => true]);

        $payment = app(RecordPayment::class)->handle(['homeowner_id' => $homeowner->id, 'dues_setting_id' => $dues->id, 'amount_paid' => 300, 'penalty' => 50, 'payment_date' => today()->toDateString(), 'covered_period' => 'August 2026', 'payment_method' => 'Cash'], $recorder);

        $this->assertSame('250.00', $payment->balance);
        $this->assertSame('Partial', $payment->status);
        $this->assertStringStartsWith('OR-', $payment->or_number);
    }
}
