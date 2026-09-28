<?php

namespace App\Console\Commands;

use App\Models\ChatUser;
use App\Models\Conversation;
use App\Services\ChatDepartmentResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill for threads created BEFORE they were scoped to a case (2026-09-28).
 *
 * WHY THE KEY MUST CHANGE TOO, not just the column. A legacy thread has
 * conversation_key "3-9". If it were given only a case_id, the next time anyone opened that
 * same case the key built for it would be "3-9-c12345" — which does not exist — so a SECOND,
 * empty thread would be created and the original, with all its history, would be orphaned and
 * invisible. Rewriting the key is what stops that.
 *
 * WHICH CASE. The patient's NEWEST case in that thread's own department. That is the same
 * guess Medhiwa's staff page was already making for its chart link, so no existing behaviour
 * gets worse; from here on the case is stated rather than guessed. A thread whose patient has
 * no resolvable case in its department is LEFT ALONE rather than given a wrong one.
 *
 * Safe to re-run: it only touches rows where case_id IS NULL.
 *
 * Delete this command once it has been run everywhere.
 */
class BackfillConversationCaseIds extends Command
{
    protected $signature = 'chat:backfill-case-ids {--apply : Write the changes. Without this the command only reports.}';

    protected $description = 'Give pre-2026-09-28 patient chat threads a case_id and a case-scoped conversation_key.';

    public function handle(ChatDepartmentResolver $departments): int
    {
        $apply = (bool) $this->option('apply');

        if (!$apply) {
            $this->warn('DRY RUN — nothing will be written. Re-run with --apply to commit.');
        }

        $patientType = (string) config('chat.types.patient', 'patient');

        $conversations = Conversation::query()
            ->whereNull('case_id')
            ->whereNotNull('department_chat_user_id')
            ->with(['participants.chatUser', 'department'])
            ->orderBy('id')
            ->get();

        if ($conversations->isEmpty()) {
            $this->info('Nothing to do: no case-less patient threads.');

            return self::SUCCESS;
        }

        $this->info('Candidate threads: ' . $conversations->count());

        $updated = 0;
        $skippedNoCase = 0;
        $skippedCollision = 0;
        $skippedNoPatient = 0;

        foreach ($conversations as $conversation) {
            $patient = $conversation->participants
                ->map(fn ($p) => $p->chatUser)
                ->first(fn (?ChatUser $u) => $u && $u->external_type === $patientType);

            if (!$patient) {
                $skippedNoPatient++;
                $this->line("  {$conversation->uuid}  SKIP  no patient participant");
                continue;
            }

            $city = $conversation->department?->external_id !== null
                ? $departments->displayCity((int) $conversation->department->external_id)
                : null;

            if ($city === null) {
                $skippedNoCase++;
                $this->line("  {$conversation->uuid}  SKIP  department does not resolve to a city");
                continue;
            }

            // Newest case IN THIS THREAD'S OWN DEPARTMENT. Taking the newest case overall could
            // file a Houston case against a Canton thread, which the API would then refuse to
            // reproduce and which would read as corruption rather than a guess.
            $wantedLocation = $departments->canonicalLocationId($city);

            $case = null;

            foreach ($departments->casesForPatientIds([(int) $patient->external_id]) as $candidate) {
                if ($departments->canonicalLocationId($candidate['department']) === $wantedLocation) {
                    $case = $candidate;
                    break; // casesForPatientIds is newest-first.
                }
            }

            if (!$case) {
                $skippedNoCase++;
                $this->line("  {$conversation->uuid}  SKIP  patient has no case in {$city}");
                continue;
            }

            $ids = $conversation->participants->pluck('chat_user_id')->all();

            if (count($ids) < 2) {
                $skippedNoPatient++;
                $this->line("  {$conversation->uuid}  SKIP  fewer than two participants");
                continue;
            }

            $newKey = Conversation::directKeyFor((int) $ids[0], (int) $ids[1], $case['case_id']);

            $taken = Conversation::where('conversation_key', $newKey)
                ->where('id', '!=', $conversation->id)
                ->exists();

            if ($taken) {
                // A thread for this case already exists. Merging histories is not something a
                // backfill should decide, so this is reported and left for a person.
                $skippedCollision++;
                $this->line("  {$conversation->uuid}  SKIP  key {$newKey} already taken — needs a manual decision");
                continue;
            }

            $this->line("  {$conversation->uuid}  ->  case {$case['case_id']} ({$city}), key {$newKey}");

            if ($apply) {
                DB::transaction(function () use ($conversation, $case, $newKey) {
                    $conversation->update([
                        'case_id' => $case['case_id'],
                        'conversation_key' => $newKey,
                    ]);
                });
            }

            $updated++;
        }

        $this->newLine();
        $this->info(($apply ? 'Updated: ' : 'Would update: ') . $updated);

        if ($skippedNoCase) {
            $this->warn("Skipped, no case in the thread's department: {$skippedNoCase}");
        }
        if ($skippedCollision) {
            $this->warn("Skipped, a thread for that case already exists: {$skippedCollision}");
        }
        if ($skippedNoPatient) {
            $this->warn("Skipped, participants unreadable: {$skippedNoPatient}");
        }

        $this->line('Skipped threads keep case_id NULL and stay usable; they simply show no chart link.');

        return self::SUCCESS;
    }
}
