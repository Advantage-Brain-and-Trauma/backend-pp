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
    public function __construct(string $patientId, string $caseId, string $funnelId, string $funnelName,  string $patientName, string $email, string $phone,  string $flag) 
    {
        $this->patientName = $patientName;
        $this->funnelName = $funnelName;
        $this->flag = $flag;

        $baseUrl = 'https://app.advantagehcs.com';

        // URL SAFE ENCODE
        $encodedFlag = $this->base64UrlEncode($flag);
        $encodedFunnelId = $this->base64UrlEncode($funnelId);

        // USER EXISTS
        if ($flag === 'user_exists') {

            $this->funnelUrl =
                $baseUrl .
                '?form=' . $encodedFunnelId .
                '&flag=' . $encodedFlag;

        } else {

            // NO USER

            $encodedPatientId = $this->base64UrlEncode($patientId);
            $encodedCaseId = $this->base64UrlEncode($caseId);
            $encodedPatientName = $this->base64UrlEncode($patientName);
            $encodedEmail = $this->base64UrlEncode($email);
            $encodedPhone = $this->base64UrlEncode($phone);

            $this->funnelUrl =
                $baseUrl .
                '?form=' . $encodedFunnelId .
                '&patient_id=' . $encodedPatientId .
                '&case_id=' . $encodedCaseId .
                '&name=' . $encodedPatientName .
                '&email=' . $encodedEmail .
                '&phone=' . $encodedPhone .
                '&flag=' . $encodedFlag;
        }       '&flag=' . urlencode($encodedFlag);
    }

    private function base64UrlEncode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
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
