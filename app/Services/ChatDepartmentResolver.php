<?php

namespace App\Services;

use App\Models\AhcsCase;
use App\Models\ChatUser;
use App\Models\MedhiwaSpecialityLocation;

/**
 * Resolves the three chat identity types used by the patient <-> staff queue chat, and owns
 * the city <-> department-identity mapping.
 *
 * WHY A "DEPARTMENT" IDENTITY EXISTS
 * ----------------------------------
 * The chat schema is strictly 1-to-1 (conversations.conversation_key is a sorted pair of
 * chat_user ids). A patient does not know which staff member is on shift, so the peer they
 * talk to is the DEPARTMENT, not a person: one chat_users row per department, and an
 * ordinary direct conversation between the patient and it. That gives exactly one thread per
 * patient + department with no change to conversations / conversation_participants. The real
 * staff author of each reply is kept on chat_messages.sent_by_chat_user_id.
 *
 * THE CITY -> ID MAPPING (the subtle part)
 * ----------------------------------------
 * A "department" in this business is a CITY: ahcs_cases.department stores the city string and
 * speciality_location.city is the same value. But chat_users.external_id is numeric, and a
 * city can own SEVERAL speciality_location rows (one per speciality set). So the canonical
 * identity for a city is the LOWEST non-deleted speciality_location.id carrying it. This
 * class is the only place that choice is made.
 *
 * Medhiwa sends city NAMES over the wire and never the numeric id, so the two repositories
 * cannot drift apart on the mapping — if the canonicalisation ever changes it changes here,
 * and both sides follow.
 *
 * Matching is done on a NORMALISED city (trimmed, collapsed whitespace, lower-cased) because
 * ahcs_cases.department is free text and differs from speciality_location.city in case and
 * spacing often enough to matter. The DISPLAY name always comes from speciality_location, so
 * one department never appears under two spellings.
 *
 * Cross-connection by design: speciality_location lives on `medhiwa_ahcs`, ahcs_cases on
 * `ahcs`, chat_users on the portal's default connection. Resolution is therefore done in
 * separate steps in PHP — never a join.
 */
class ChatDepartmentResolver
{
    /** Normalised city => canonical speciality_location.id. Request-level memo. */
    private ?array $cityToLocationId = null;

    /** Canonical speciality_location.id => display city. Request-level memo. */
    private array $locationIdToCity = [];

    // -- identities ---------------------------------------------------------

    /**
     * The chat identity for a patient. external_id is the AHCS patient id, matching what
     * ChatAuthController::patient() already issues for the patient side.
     */
    public function patientChatUser(int $patientId, ?string $name = null): ChatUser
    {
        return $this->identity($this->type('patient'), $patientId, $name);
    }

    /**
     * The chat identity for a Medhiwa staff member (users.id). Created so a reply can be
     * attributed; it is never a conversation participant.
     */
    public function staffChatUser(int $staffUserId, ?string $name = null): ChatUser
    {
        return $this->identity($this->type('staff'), $staffUserId, $name);
    }

    /**
     * The chat identity for a department, by city name.
     *
     * Returns null when the city matches no speciality_location row — the caller must treat
     * that as "not a routable department" rather than inventing an identity, or a typo in
     * ahcs_cases.department would silently create a queue nobody is watching.
     */
    public function departmentChatUser(string $city, bool $create = true): ?ChatUser
    {
        $locationId = $this->canonicalLocationId($city);

        if ($locationId === null) {
            return null;
        }

        if (!$create) {
            return ChatUser::where('external_type', $this->type('department'))
                ->where('external_id', $locationId)
                ->first();
        }

        $display = $this->displayCity($locationId) ?? trim($city);
        $name = trim($display . ' ' . (string) config('chat.queue_name_suffix'));

        return $this->identity($this->type('department'), $locationId, $name);
    }

    /**
     * Department identities for a list of city names, as [chat_user_id => display city].
     *
     * Does NOT create missing identities: this scopes a staff member's queue listing, and a
     * department nobody has ever written to simply has no conversations.
     *
     * @param  string[]  $cities
     * @return array<int, string>
     */
    public function departmentChatUserIds(array $cities): array
    {
        $byLocationId = [];

        foreach ($cities as $city) {
            $locationId = $this->canonicalLocationId((string) $city);

            if ($locationId !== null) {
                $byLocationId[$locationId] = $this->displayCity($locationId) ?? trim((string) $city);
            }
        }

        if ($byLocationId === []) {
            return [];
        }

        return ChatUser::where('external_type', $this->type('department'))
            ->whereIn('external_id', array_keys($byLocationId))
            ->get(['id', 'external_id'])
            ->mapWithKeys(fn (ChatUser $u) => [
                (int) $u->id => $byLocationId[(int) $u->external_id] ?? '',
            ])
            ->all();
    }

    /**
     * Every department identity that exists, as [chat_user_id => display city]. Used for a
     * staff member who is not restricted to particular departments.
     *
     * @return array<int, string>
     */
    public function allDepartmentChatUserIds(): array
    {
        return ChatUser::where('external_type', $this->type('department'))
            ->get(['id', 'external_id'])
            ->mapWithKeys(fn (ChatUser $u) => [
                (int) $u->id => (string) ($this->displayCity((int) $u->external_id) ?? ''),
            ])
            ->all();
    }

    // -- patient -> departments ---------------------------------------------

    /**
     * The departments a patient may talk to: the distinct, resolvable case departments across
     * all of their patient ids, as DISPLAY city names.
     *
     * This is the allowlist behind the "a patient may only open a conversation with a
     * department they have a case in" rule.
     *
     * @param  int[]  $patientIds
     * @return string[]
     */
    public function departmentsForPatientIds(array $patientIds): array
    {
        $patientIds = array_values(array_filter(array_map('intval', $patientIds)));

        if ($patientIds === []) {
            return [];
        }

        $rawCities = AhcsCase::whereIn('patient_id', $patientIds)
            ->pluck('department')
            ->all();

        $resolved = [];

        foreach ($rawCities as $raw) {
            $locationId = $this->canonicalLocationId((string) $raw);

            if ($locationId !== null) {
                // Keyed by id so two spellings of one city collapse to a single entry.
                $resolved[$locationId] = $this->displayCity($locationId);
            }
        }

        return array_values(array_filter($resolved));
    }

    /**
     * Whether a patient is allowed to talk to a department identity.
     *
     * @param  int[]  $patientIds
     */
    public function patientMayUseDepartment(array $patientIds, ChatUser $department): bool
    {
        if ($department->external_type !== $this->type('department')) {
            return false;
        }

        foreach ($this->departmentsForPatientIds($patientIds) as $city) {
            if ($this->canonicalLocationId($city) === (int) $department->external_id) {
                return true;
            }
        }

        return false;
    }

    // -- city <-> location id -----------------------------------------------

    /** The canonical speciality_location.id for a city, or null when it matches none. */
    public function canonicalLocationId(string $city): ?int
    {
        $key = $this->normalise($city);

        if ($key === '') {
            return null;
        }

        return $this->cityMap()[$key] ?? null;
    }

    /** The display spelling for a canonical location id. */
    public function displayCity(int $locationId): ?string
    {
        $this->cityMap();

        return $this->locationIdToCity[$locationId] ?? null;
    }

    /**
     * Normalised city => canonical (lowest) speciality_location.id, built once per request.
     */
    private function cityMap(): array
    {
        if ($this->cityToLocationId !== null) {
            return $this->cityToLocationId;
        }

        $this->cityToLocationId = [];
        $this->locationIdToCity = [];

        $rows = MedhiwaSpecialityLocation::query()
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'city']);

        foreach ($rows as $row) {
            $key = $this->normalise((string) $row->city);

            if ($key === '') {
                continue;
            }

            // orderBy('id') means the FIRST row seen for a city is the lowest id, so a later
            // duplicate spelling must not overwrite the canonical choice.
            if (!array_key_exists($key, $this->cityToLocationId)) {
                $this->cityToLocationId[$key] = (int) $row->id;
                $this->locationIdToCity[(int) $row->id] = trim((string) $row->city);
            }
        }

        return $this->cityToLocationId;
    }

    /** Trim, collapse internal whitespace, lower-case. */
    private function normalise(string $city): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $city)));
    }

    // -- internals ----------------------------------------------------------

    private function identity(string $externalType, int $externalId, ?string $name): ChatUser
    {
        $chatUser = ChatUser::firstOrCreate(
            [
                'external_type' => $externalType,
                'external_id'   => $externalId,
            ],
            [
                'name' => $name,
            ]
        );

        if ($name && $chatUser->name !== $name) {
            $chatUser->update(['name' => $name]);
        }

        return $chatUser;
    }

    private function type(string $key): string
    {
        return (string) config('chat.types.' . $key, $key);
    }
}
