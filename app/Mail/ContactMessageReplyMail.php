<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ContactMessageActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ContactMessageReplyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly ContactMessageActivity $activity) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->activity->subject ?? __('Response from the HOA office'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.contact-message-reply');
    }
}
