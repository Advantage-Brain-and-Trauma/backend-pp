<?php

namespace App\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Thrown when Twilio rejects an outgoing SMS.
 *
 * Carries a message that is safe to return to the caller — Twilio's own
 * description of what was wrong with the request, e.g.
 * "Invalid 'To' Phone Number: +1555..." — so staff can correct the number
 * instead of being told "Something went wrong".
 *
 * Only Twilio's request-level rejections are surfaced this way. Server-side
 * problems (missing credentials, network failures, DB errors) must keep the
 * generic message so nothing internal leaks through the public SMS endpoints.
 */
class SmsDeliveryException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $twilioCode = null,
        public readonly string $rawBody = ''
    ) {
        parent::__construct($message);
    }

    /**
     * Build from a failed Twilio Messages API response.
     *
     * Twilio returns { code, message, more_info, status }. We surface only
     * `message` and `code`; `more_info` is a docs URL and `status` duplicates
     * the HTTP status, neither of which helps the person sending the form.
     */
    public static function fromTwilioResponse(Response $response): self
    {
        $payload = $response->json();
        $message = is_array($payload) ? ($payload['message'] ?? null) : null;
        $code    = is_array($payload) ? ($payload['code'] ?? null) : null;

        return new self(
            is_string($message) && $message !== ''
                ? $message
                : 'The SMS could not be delivered. Please verify the phone number and try again.',
            is_numeric($code) ? (int) $code : null,
            $response->body()
        );
    }
}
