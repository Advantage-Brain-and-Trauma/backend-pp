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
    public function __construct(string $patientId, string $caseId, string $patientName, string $funnelName, string $email, string $phone, string $funnelId, string $flag) 
    {
        $this->patientName = $patientName;
        $this->funnelName = $funnelName;
        $this->flag = $flag;

        $baseUrl = 'https://app.advantagehcs.com';
        $encodedFlag = base64_encode($flag);
        $encodedFunnelId = base64_encode($funnelId);
        $encodedPatientId = base64_encode($patientId);
        $encodedCaseId = base64_encode($caseId);
        $encodedPatientName = base64_encode($patientName);
        $encodedEmail = base64_encode($email);
        $encodedPhone = base64_encode($phone);

        // IF USER EXISTS
        if ($flag === 'user_exists') {

            $encodedPatientName = base64_encode($patientName);
            $encodedFunnelName = base64_encode($funnelName);

            $this->funnelUrl =
                $baseUrl .
                '?form=' . urlencode($encodedFunnelId) .
                '&flag=' . urlencode($encodedFlag);

        } else {
            // NO USER
            $encodedPatientId = base64_encode($patientId);
            $encodedCaseId = base64_encode($caseId);
            $encodedPatientName = base64_encode($patientName);
            $encodedEmail = base64_encode($email);
            $encodedPhone = base64_encode($phone);

            $this->funnelUrl =
                $baseUrl .
                '?form=' . urlencode($encodedFunnelId) .
                '&patient_id=' . urlencode($encodedPatientId) .
                '&case_id=' . urlencode($encodedCaseId) .
                '&name=' . urlencode($encodedPatientName) .
                '&email=' . urlencode($encodedEmail) .
                '&phone=' . urlencode($encodedPhone) .
                '&flag=' . urlencode($encodedFlag);
        }
    

        // $this->funnelUrl =  'https://app.advantagehcs.com/form/' . $encodedFunnelId .
        //                     '?patient_id=' . urlencode($encodedPatientId) .
        //                     '&case_id=' . urlencode($encodedCaseId) .
        //                     '&name=' . urlencode($encodedPatientName) .
        //                     '&email=' . urlencode($encodedEmail) .
        //                     '&phone=' . urlencode($encodedPhone) .
        //                     '&flag=' . urlencode($encodedFlag);
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
