<?php

namespace App\Mail;

use App\Models\ProxyAccess;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProxyInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProxyAccess $proxyAccess,
        public string $patientName,
        public string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->patientName} has invited you to access their health records",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.proxy-invitation',
            with: [
                'patientName'  => $this->patientName,
                'relationship' => $this->proxyAccess->relationship,
                'accessLevel'  => $this->proxyAccess->access_level,
                'acceptUrl'    => $this->acceptUrl,
                'expiresAt'    => $this->proxyAccess->token_expires_at?->format('M d, Y h:i A'),
            ],
        );
    }
}
