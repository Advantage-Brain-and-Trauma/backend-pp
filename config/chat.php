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
