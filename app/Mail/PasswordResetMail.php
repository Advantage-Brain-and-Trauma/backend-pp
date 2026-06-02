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
        // Do NOT use config('app.name') — APP_NAME=Laravel in .env, which makes the
        // subject "Reset Your Password – Laravel" and triggers spam filters immediately.
        // Use MAIL_FROM_NAME instead which is set to the real app name ("Advantage").
        $appName = config('mail.from.name', 'MedHiWa Patient Portal');

        return new Envelope(
            subject: 'Your ' . $appName . ' Account – Action Required (' . now()->format('M d, Y') . ')',
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
