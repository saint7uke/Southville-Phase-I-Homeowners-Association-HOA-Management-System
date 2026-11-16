<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\ContactMessageReplyMail;
use App\Models\Announcement;
use App\Models\Certificate;
use App\Models\ContactMessage;
use App\Models\ContactMessageActivity;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\User;
use App\Notifications as Notice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class EmailBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
    }

    public function test_every_notification_renders_branded_html_and_readable_plain_text_in_both_languages(): void
    {
        $owner = $this->brandingResident();
        $certificate = Certificate::withoutEvents(fn () => Certificate::create([
            'homeowner_id' => $owner->id, 'certificate_number' => 'SYNTHETIC-2026-0001', 'type' => 'Certificate of Residency',
            'status' => 'Issued', 'issued_at' => now(), 'expires_at' => now()->endOfYear(), 'issued_by' => $owner->user_id,
        ]));
        $notices = [new Notice\RegistrationReceived, new Notice\NewHomeownerRegistration($owner->user),
            new Notice\HomeownerAccountStatusChanged('Active', null, 'Pending'),
            new Notice\HomeownerAccountStatusChanged('Active', null, 'Inactive'),
            new Notice\HomeownerAccountStatusChanged('Suspended', 'Synthetic reason', 'Active'),
            new Notice\HomeownerAccountStatusChanged('Rejected', 'Synthetic reason', 'Pending'),
            new Notice\WorkflowStatusChanged('complaint', 'SYNTHETIC', 'Under Review', '<script>unsafe()</script>'),
            new Notice\OperationalAlert('Synthetic alert', 'Synthetic event', url('/staff/complaints'), [], 'manage_complaints'),
            new Notice\CertificateIssued($certificate->id),
            new Notice\PaymentReviewCompleted(new Payment(['review_status' => 'Rejected', 'or_number' => 'SYNTHETIC', 'review_notes' => 'Synthetic reason'])),
            new Notice\PaymentReviewCompleted(new Payment(['review_status' => 'Approved', 'or_number' => 'SYNTHETIC'])),
            new Notice\WorkflowStatusChanged('service request', 'SYNTHETIC', 'Completed'),
            new Notice\OverdueDuesNotice('Synthetic dues', 'January 1'), new Notice\HomeownerDelinquencyNotice(3),
            new Notice\AccountPasswordReset('https://southvillehoams.com/homeowner/password-reset/reset?synthetic=1'),
            new VerifyEmail,
            new Notice\AnnouncementPublished((new Announcement)->forceFill(['id' => 99, 'title' => 'Synthetic announcement', 'content' => '<script>unsafe()</script>Safe text']))];
        foreach (['en', 'fil'] as $locale) {
            app()->setLocale($locale);
            foreach ($notices as $notice) {
                $message = $notice->toMail($owner->user);
                $html = (string) app(Markdown::class)->render('notifications::email', $message->data());
                $plain = (string) app(Markdown::class)->renderText('notifications::email', $message->data());
                $this->assertStringContainsString('/images/hoa-email-logo.png', $html, $notice::class);
                $this->assertStringContainsString('alt="Southville Phase I HOA logo"', $html);
                $this->assertStringContainsString('<h1 ', $html);
                $this->assertStringContainsString(e($message->subject), $html);
                $this->assertStringContainsString($message->subject, $plain);
                if ($message->actionUrl !== null) {
                    $this->assertStringContainsString('href="'.e($message->actionUrl).'"', $html);
                    $this->assertStringContainsString($message->actionUrl, $plain);
                }
                $this->assertStringContainsString('Southville Phase I HOA', $html);
                $this->assertStringNotContainsString('<script>', $html);
                $this->assertNotEmpty(trim($plain));
                $this->assertStringNotContainsString('<table', $plain);
            }
        }
    }

    public function test_contact_reply_has_the_same_branding_and_escaped_content(): void
    {
        $contact = (new ContactMessage)->forceFill(['name' => 'Synthetic sender', 'reference' => 'SYNTHETIC']);
        $activity = new ContactMessageActivity(['body' => '<script>unsafe()</script>\nSynthetic reply', 'subject' => 'Synthetic reply']);
        $activity->setRelation('contactMessage', $contact);
        $mail = new ContactMessageReplyMail($activity);
        $this->assertStringContainsString('/images/hoa-email-logo.png', $mail->render());
        $this->assertStringNotContainsString('<script>', $mail->render());
        $plain = (string) app(Markdown::class)->renderText('mail.contact-message-reply', ['activity' => $activity]);
        $this->assertStringContainsString('Synthetic reply', $plain);
    }

    public function test_untrusted_content_is_literal_and_links_remain_unchanged(): void
    {
        $url = 'https://southvillehoams.com/homeowner/example?expires=123&signature=SYNTHETIC';
        $unsafe = "<script>unsafe()</script> [Unexpected link](https://invalid.example)\nSecond line";
        $message = (new MailMessage)
            ->subject('Synthetic title')->greeting('Hello Synthetic,')->line($unsafe)
            ->markdown('notifications::email', ['details' => ['Reason' => $unsafe]])
            ->action('Review synthetic record', $url);
        $renderer = app(Markdown::class);
        $html = (string) $renderer->render('notifications::email', $message->data());
        $plain = (string) $renderer->renderText('notifications::email', $message->data());
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('href="https://invalid.example"', $html);
        $this->assertStringContainsString('[Unexpected link](https://invalid.example)', $html);
        $this->assertStringContainsString('Second line', $html);
        $this->assertStringContainsString('href="'.e($url).'"', $html);
        $this->assertStringContainsString($url, $plain);
        $this->assertStringContainsString($unsafe, $plain);
        $this->assertSame(1, substr_count($html, '<h1 '));
        $this->assertStringNotContainsString('<table', $plain);
    }

    public function test_logo_is_small_and_original_remains_available(): void
    {
        $path = public_path('images/hoa-email-logo.png');
        $this->assertFileExists(public_path('images/HOA.png'));
        $this->assertLessThan(100_000, filesize($path));
        $this->assertSame([160, 160], array_slice(getimagesize($path), 0, 2));
    }

    protected function brandingResident(): Homeowner
    {
        $user = User::factory()->create();
        $user->assignRole('homeowner');

        return $user->homeowner()->create(['block' => '1', 'lot' => '9', 'street' => null,
            'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'ownership_type' => 'Owner', 'status' => 'Active', 'residency_date' => '2020-01-01']);
    }
}
