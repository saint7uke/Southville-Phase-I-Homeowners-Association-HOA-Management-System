<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Homeowners\RegisterHomeowner;
use App\Models\SmsContactPreference;
use App\Models\SmsDelivery;
use App\Models\SmsVerification;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Sms\SmsEnrollment;
use App\Services\Sms\SmsManagement;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SmsEnrollmentHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 10:00:00', 'Asia/Manila'));
        $this->seed(RolesAndPermissionsSeeder::class);
        Queue::fake();
        Notification::fake();
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['textbee.enabled' => true]);
        SystemSetting::current()->update(['sms_enabled' => true]);
    }

    private function registration(): array
    {
        return ['verification_document' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'), 'first_name' => 'SMS', 'last_name' => 'Applicant', 'sex' => 'Female', 'contact_number' => '09171234567',
            'date_of_birth' => '1990-01-01', 'email' => 'sms-applicant@example.test', 'password' => 'SyntheticPass!2026',
            'phase_suffix' => 'A', 'block' => '7', 'lot' => '8', 'ownership_type' => 'Owner', 'residency_date' => '2020-01-01'];
    }

    private function verifyGuest(): string
    {
        $user = User::factory()->create(['contact_number' => '09171234567']);
        $user->assignRole('homeowner');
        $this->actingAs($user, 'homeowner');
        $response = $this->postJson('/sms/verification/request', ['scope' => 'profile'])
            ->assertStatus(202)->assertJsonMissingPath('code')->assertJsonMissingPath('phone');
        $id = $response->json('verification_id');
        preg_match('/code is (\d{6})/', SmsDelivery::where('type', 'otp')->latest('id')->first()->body, $matches);
        $this->postJson('/sms/verification/confirm', ['scope' => 'profile', 'verification_id' => $id, 'code' => $matches[1]])
            ->assertOk()->assertJsonPath('message', 'Mobile Number Verified Successfully!');

        return $id;
    }

    public function test_registration_rejects_even_a_valid_profile_verification_proof(): void
    {
        $id = $this->verifyGuest();
        try {
            app(RegisterHomeowner::class)->handle([...$this->registration(), 'sms_transactional_opt_in' => true, 'sms_verification_id' => $id]);
            $this->fail('Registration cannot enroll SMS.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sms_verification_id', $exception->errors());
        }
        $this->assertDatabaseMissing('users', ['email' => 'sms-applicant@example.test']);
        $this->assertNull(SmsVerification::findOrFail($id)->consumed_at);
        Http::assertNothingSent();
    }

    public function test_verification_proof_rolls_back_when_registration_transaction_fails(): void
    {
        $id = $this->verifyGuest();
        try {
            DB::transaction(function (): void {
                app(RegisterHomeowner::class)->handle($this->registration());
                throw new \RuntimeException('Synthetic rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseMissing('users', ['email' => 'sms-applicant@example.test']);
        $this->assertNull(SmsVerification::findOrFail($id)->consumed_at);
        $this->assertDatabaseCount('sms_contact_preferences', 0);
    }

    public function test_registration_without_sms_remains_available_when_gateway_disabled(): void
    {
        config(['textbee.enabled' => false]);
        $user = app(RegisterHomeowner::class)->handle($this->registration());
        $this->assertSame('Pending', $user->fresh()->account_status);
        $this->assertDatabaseCount('sms_deliveries', 0);
        Http::assertNothingSent();
    }

    public function test_forged_or_wrong_number_proof_cannot_create_partial_account(): void
    {
        $id = $this->verifyGuest();
        try {
            app(RegisterHomeowner::class)->handle([...$this->registration(), 'contact_number' => '09181234567', 'sms_transactional_opt_in' => true, 'sms_verification_id' => $id]);
            $this->fail('Mismatched number accepted.');
        } catch (ValidationException) {
        }
        $this->assertDatabaseMissing('users', ['email' => 'sms-applicant@example.test']);
        $this->assertNull(SmsVerification::findOrFail($id)->consumed_at);
    }

    public function test_expired_proof_and_code_are_rejected(): void
    {
        $id = $this->verifyGuest();
        $this->travel(31)->minutes();
        $this->expectException(ValidationException::class);
        app(SmsEnrollment::class)->apply(auth('homeowner')->user(), ['sms_transactional_opt_in' => true, 'sms_verification_id' => $id], app(SmsEnrollment::class)->binding(auth('homeowner')->user()));
    }

    public function test_profile_requires_own_authenticated_number_and_supports_immediate_opt_out(): void
    {
        $user = User::factory()->create(['contact_number' => '09171234567']);
        $user->assignRole('homeowner');
        $this->actingAs($user, 'homeowner');
        $response = $this->postJson('/sms/verification/request', ['scope' => 'profile', 'contact_number' => '09999999999'])->assertStatus(202);
        $this->assertSame('+639171234567', SmsVerification::sole()->phone);
        preg_match('/code is (\d{6})/', SmsDelivery::sole()->body, $matches);
        $this->postJson('/sms/verification/confirm', ['scope' => 'profile', 'verification_id' => $response->json('verification_id'), 'code' => $matches[1]])->assertOk();
        $this->postJson('/sms/preferences', ['scope' => 'profile', 'sms_transactional_opt_in' => true, 'sms_announcement_opt_in' => true,
            'sms_verification_id' => $response->json('verification_id')])->assertOk();
        $this->postJson('/sms/preferences', ['scope' => 'profile', 'sms_transactional_opt_in' => false, 'sms_announcement_opt_in' => false])->assertOk();
        $this->assertFalse(SmsContactPreference::sole()->transactional_opt_in);
        $this->assertFalse(SmsContactPreference::sole()->announcement_opt_in);
    }

    public function test_otp_expiration_and_disabled_service_do_not_return_success(): void
    {
        $user = User::factory()->create(['contact_number' => '09171234567']);
        $user->assignRole('homeowner');
        $this->actingAs($user, 'homeowner');
        $response = $this->postJson('/sms/verification/request', ['scope' => 'profile'])->assertStatus(202);
        preg_match('/code is (\d{6})/', SmsDelivery::sole()->body, $matches);
        $this->travel(6)->minutes();
        $this->postJson('/sms/verification/confirm', ['scope' => 'profile', 'verification_id' => $response->json('verification_id'), 'code' => $matches[1]])->assertUnprocessable();
        config(['textbee.enabled' => false]);
        $this->postJson('/sms/verification/request', ['scope' => 'profile'])->assertUnprocessable();
    }

    public function test_registration_form_is_bilingual_and_does_not_contain_credentials(): void
    {
        $this->get('/homeowner/register')->assertOk()->assertDontSee('Optional SMS notifications')->assertSee('Important Reminder')->assertDontSee('synthetic-test-key');
        $this->withSession(['locale' => 'fil'])->get('/homeowner/register')->assertOk()->assertSee('Mahalagang Paalala')->assertSee('Pagkapribado ng Datos at Pahintulot');
    }

    public function test_rejected_array_input_can_be_redisplayed_without_a_render_error(): void
    {
        $this->withSession(['_old_input' => ['contact_number' => ['09171234567'], 'sms_verification_id' => ['forged']]])
            ->get('/homeowner/register')->assertOk()->assertDontSee('data-sms-enrollment', false);
    }

    public function test_invalid_raw_phone_inputs_never_queue_otp_or_create_accounts(): void
    {
        // This matrix tests validation, not the separate public registration rate limit.
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach (['+639171234567', '639171234567', '0917-1234567', ' 09171234567', '09171234567 ', "09171234567\n", '0917123456', '091712345678', '08171234567', '0917abc4567', ['09171234567'], 9171234567, '０９１７１２３４５６７'] as $number) {
            $this->postJson('/sms/verification/request', ['scope' => 'registration', 'contact_number' => $number])
                ->assertForbidden();
            $this->postJson('/homeowner/register', [...$this->registration(), 'contact_number' => $number])
                ->assertUnprocessable()->assertJsonValidationErrors('contact_number');
            try {
                app(RegisterHomeowner::class)->handle([...$this->registration(), 'contact_number' => $number]);
                $this->fail('Malformed contact accepted by registration action.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('contact_number', $exception->errors());
            }
        }
        $this->assertDatabaseCount('sms_verifications', 0);
        $this->assertDatabaseCount('sms_deliveries', 0);
        $this->assertDatabaseMissing('users', ['email' => 'sms-applicant@example.test']);
        Http::assertNothingSent();
    }

    public function test_otp_is_six_ascii_digits_and_never_silently_trimmed(): void
    {
        $id = $this->verifyGuest();
        foreach (['12345', '1234567', '12345a', '１２３４５６', ' 123456', "123456\n", ['123456']] as $code) {
            $this->postJson('/sms/verification/confirm', ['scope' => 'profile', 'verification_id' => $id, 'code' => $code])
                ->assertUnprocessable()->assertJsonValidationErrors('code')->assertJsonMissingPath('verified');
        }
    }

    public function test_profile_verification_preserves_saved_international_contact(): void
    {
        $user = User::factory()->create(['contact_number' => '+639171234567']);
        $user->assignRole('homeowner');
        $this->actingAs($user, 'homeowner')->postJson('/sms/verification/request', ['scope' => 'profile'])
            ->assertStatus(202)->assertJsonPath('resend_after', 60);
        $this->assertSame('+639171234567', $user->fresh()->contact_number);
        $this->assertSame('+639171234567', SmsVerification::sole()->phone);
    }

    public function test_admin_test_sms_rejects_invalid_input_before_any_provider_call(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        foreach (['+639171234567', '0917-1234567', ' 09171234567', ['09171234567'], '0917abc4567'] as $number) {
            try {
                app(SmsManagement::class)->test($admin, $number);
                $this->fail('Invalid test number accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('number', $exception->errors());
            }
        }
        $this->assertDatabaseCount('sms_deliveries', 0);
        Http::assertNothingSent();
    }
}
