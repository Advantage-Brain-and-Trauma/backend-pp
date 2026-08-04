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
    public string $view;

    // public function __construct(string $patientName, string $funnelName, string $funnelSlug)
    public function __construct(string $patientId, string $caseId, string $funnelId, string $funnelName,  string $patientName, string $email, string $phone,  string $flag, string $source, bool $isMultipleFunnel = false, string $view = 'emails.assign-funnel')
    {
        $this->patientName = $patientName;
        $this->funnelName  = $funnelName;
        $this->flag        = $flag;
        $this->view        = $view;

        $baseUrl = 'https://app.advantagehcs.com';

        // ENCODE VALUES
        $encodedPatientId   = $this->base64UrlEncode($patientId);
        $encodedCaseId      = $this->base64UrlEncode($caseId);
        $encodedFunnelId    = $this->base64UrlEncode($funnelId);
        $encodedFunnelName  = $this->base64UrlEncode($funnelName);
        $encodedPatientName = $this->base64UrlEncode($patientName);
        $encodedEmail       = $this->base64UrlEncode($email);
        $encodedPhone       = $this->base64UrlEncode($phone);
        $encodedFlag        = $this->base64UrlEncode($flag);
        $encodedSource      = $this->base64UrlEncode($source);

        // ENCODE PARAMETER NAMES ALSO
        $params = [];

        $params[$this->base64UrlEncode('form')] = $encodedFunnelId;
        $params[$this->base64UrlEncode('flag')] = $encodedFlag;
        $params[$this->base64UrlEncode('source')] = $encodedSource;
        $params[$this->base64UrlEncode('patient_id')] = $encodedPatientId;
        $params[$this->base64UrlEncode('case_id')] = $encodedCaseId;
        $params[$this->base64UrlEncode('is_multiple_funnels')] = $this->base64UrlEncode($isMultipleFunnel ? 'true' : 'false');

        if ($flag !== 'user_exists') {

            $params[$this->base64UrlEncode('funnel_name')] = $encodedFunnelName;
            $params[$this->base64UrlEncode('name')]       = $encodedPatientName;
            $params[$this->base64UrlEncode('email')]      = $encodedEmail;
            $params[$this->base64UrlEncode('phone')]      = $encodedPhone;
        }

        // BUILD QUERY STRING
        $queryString = http_build_query($params);

        $this->funnelUrl = $baseUrl . '?' . $queryString;
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
            view: $this->view,
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
