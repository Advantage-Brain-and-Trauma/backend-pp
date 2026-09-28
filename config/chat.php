<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shared secret
    |--------------------------------------------------------------------------
    |
    | Authenticates server-to-server callers (Medhiwa) on POST /api/chat/identify/external
    | and on the whole /api/chat/staff/* group. Anyone holding it can obtain a chat identity
    | for ANY external id, which makes it the most sensitive value in this subsystem: rotate
    | it whenever a server that held it is retired, and keep the IP allowlist below populated
    | in production.
    |
    */

    'shared_secret' => env('CHAT_SHARED_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Server-to-server IP allowlist
    |--------------------------------------------------------------------------
    |
    | Comma-separated IPs permitted to present the shared secret — the Medhiwa app servers.
    |
    | EMPTY MEANS NO IP RESTRICTION. That is deliberate: it is the behaviour this middleware
    | had before the allowlist existed, so deploying this change cannot lock out an
    | environment whose .env has not been updated yet. Populate it in production.
    |
    |     CHAT_STAFF_IP_ALLOWLIST=10.0.0.122
    |
    */

    'staff_ip_allowlist' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CHAT_STAFF_IP_ALLOWLIST', ''))
    ), static fn ($ip) => $ip !== '')),

    /*
    |--------------------------------------------------------------------------
    | Chat session lifetime
    |--------------------------------------------------------------------------
    |
    | Applies to the patient's chat_token only. Staff never hold a chat token — Medhiwa
    | authenticates each /api/chat/staff/* call with the shared secret instead, so no
    | chat_sessions row is created for a staff member.
    |
    */

    'session_minutes' => (int) env('CHAT_SESSION_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Identity types (chat_users.external_type)
    |--------------------------------------------------------------------------
    |
    | patient      external_id = ahcs_patients.id (the portal user's PRIMARY patient id)
    |
    | department   external_id = the CANONICAL speciality_location.id for a city. A city
    |              can have several speciality_location rows (one per speciality set), so
    |              the lowest non-deleted id for that city is picked as its single
    |              identity — see App\Services\ChatDepartmentResolver, which is the ONLY
    |              place that mapping is made. Medhiwa sends city NAMES and never the
    |              numeric id, so the two repos cannot drift apart on it.
    |
    | staff        external_id = Medhiwa users.id. Recorded on every queue reply as
    |              chat_messages.sent_by_chat_user_id for audit; the patient only ever
    |              sees the department identity as the sender.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Both sides upload through /api/chat/attachments (patient) and
    | /api/chat/staff/attachments (staff). Stored on the PUBLIC disk, matching what
    | FunnelApiController already does for form uploads - a deliberate decision on
    | 2026-09-28, not an oversight. It means a stored file is reachable by anyone
    | holding its URL, with no authentication and no expiry; the random filename is
    | the only thing standing between a link and the document. Do not "harden" this
    | by moving the disk without checking, because the URL returned to clients is
    | built from it.
    |
    | max_kb is what Laravel validates. It is NOT the real ceiling on its own:
    | nginx client_max_body_size (default 1M) rejects a larger body with a 413
    | before PHP runs, and PHP's own upload_max_filesize / post_max_size cap it
    | again. All three have to agree or the limit is whichever is smallest, and the
    | failure looks like a broken form rather than a size problem.
    |
    */
    'attachments' => [
        'disk' => env('CHAT_ATTACHMENT_DISK', 'public'),
        'path' => 'chat-attachments',
        'max_kb' => (int) env('CHAT_ATTACHMENT_MAX_KB', 102400),
        /*
         * The four kinds asked for - image, PDF, Word, Excel - each with the extensions
         * that are the same kind of document. A patient photographing a letter on a
         * phone produces png or heic as often as jpg, and rejecting those would read as
         * "attachments are broken".
         *
         * `mimes:` checks the type GUESSED FROM THE FILE CONTENTS, not the name the
         * client sent, so renaming malware.exe to report.pdf does not get past it. The
         * same strictness cuts the other way: a legitimate file whose type PHP cannot
         * detect is refused, and heic is the likely one since older mime maps do not
         * carry it. That failure is at least loud and on upload, not silent.
         */
        'mimes' => implode(',', [
            // image
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif',
            // pdf
            'pdf',
            // word
            'doc', 'docx',
            // excel
            'xls', 'xlsx', 'csv',
        ]),
    ],
    'types' => [
        'patient'    => 'patient',
        'department' => 'department',
        'staff'      => 'staff',
    ],

    /*
    | Appended to the city when naming a department identity, e.g. "Houston Care Team".
    | This is what the PATIENT sees as the other side of the conversation — never a
    | staff member's name.
    */

    'queue_name_suffix' => env('CHAT_QUEUE_NAME_SUFFIX', 'Care Team'),

    /*
    | Maximum characters in one message, enforced on BOTH the patient and staff send
    | endpoints. There was previously no limit at all (noted as issue #4 in
    | docs/PATIENT_PORTAL_CHAT_DESIGN_QUESTIONS.md).
    */

    'message_max_length' => (int) env('CHAT_MESSAGE_MAX_LENGTH', 5000),

    /*
    |--------------------------------------------------------------------------
    | Conversation lock window
    |--------------------------------------------------------------------------
    |
    | Minutes of silence after which a conversation goes INACTIVE and its lock lapses.
    |
    | The rule: a patient's message is offered to the whole department, but the FIRST staff
    | member to reply takes ownership and everyone else becomes read-only. Ownership holds
    | until this many minutes pass with no message from either side; after that the next
    | patient message releases it and the department competes for it again.
    |
    | There is no scheduled job and no status column behind this — "inactive" is DERIVED from
    | conversations.last_message_at every time it is evaluated, so the lock expires on its own
    | even if nothing is running.
    |
    */

    'lock_minutes' => (int) env('CHAT_LOCK_MINUTES', 60),

];
