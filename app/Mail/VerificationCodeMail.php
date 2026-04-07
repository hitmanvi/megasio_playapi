<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $type = 'default',
        public int $expiresInMinutes = 5,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->type) {
            'register' => 'Verify your email',
            'reset_password' => 'Password reset verification',
            'login' => 'Login verification',
            default => 'Your verification code',
        };

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',
        );
    }
}
