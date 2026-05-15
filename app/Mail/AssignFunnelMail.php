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
    public string $flag;

    // public function __construct(string $patientName, string $funnelName, string $funnelSlug) 
    public function __construct(string $patientName, string $funnelName, string $funnelId, string $flag) 
    {
        $this->patientName = $patientName;
        $this->funnelName = $funnelName;
        $this->flag = $flag;
        // $this->funnelUrl = 'https://app.advantagehcs.com/form/' . $funnelSlug;
        $this->funnelUrl = 'https://app.advantagehcs.com/form/' . $funnelId . '/' . $flag;
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
