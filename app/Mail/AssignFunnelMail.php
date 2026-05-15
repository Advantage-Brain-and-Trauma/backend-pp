<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssignFunnelMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $patientName;
    public string $funnelName;
    public string $funnelUrl;

    public function __construct(string $patientName, string $funnelName, string $funnelSlug)
    {
        $this->patientName = $patientName;
        $this->funnelName = $funnelName;
        $this->funnelUrl = 'https://app.advantagehcs.com/funnel/' . $funnelSlug;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action Required: Complete Your Funnel Form (' . now()->format('M-d, Y') . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.assign-funnel',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
