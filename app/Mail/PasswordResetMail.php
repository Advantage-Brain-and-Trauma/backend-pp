<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $patientName;
    public string $resetUrl;
    public int    $expiresInMinutes;

    /**
     * @param string $patientName     Display name shown in the email body
     * @param string $resetUrl        Full frontend URL containing token + email
     * @param int    $expiresInMinutes Token TTL taken from config('auth.passwords.users.expire')
     */
    public function __construct(string $patientName, string $resetUrl, int $expiresInMinutes)
    {
        $this->patientName      = $patientName;
        $this->resetUrl         = $resetUrl;
        $this->expiresInMinutes = $expiresInMinutes;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your Password – ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
