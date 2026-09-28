<?php

namespace App\Exceptions;

use App\Models\Conversation;
use RuntimeException;

/**
 * A patient may have exactly ONE open conversation at a time, across every case and every
 * department (decision 2026-09-28). This is thrown when opening another would break that.
 *
 * It carries the conversation already open so the caller can say WHICH one is in the way —
 * "you already have an open conversation" with no way to find it would be a dead end, and the
 * patient app needs its uuid to offer "end it and start this one".
 */
class PatientHasOpenConversation extends RuntimeException
{
    public function __construct(public readonly Conversation $openConversation)
    {
        parent::__construct('This patient already has an open conversation.');
    }
}
