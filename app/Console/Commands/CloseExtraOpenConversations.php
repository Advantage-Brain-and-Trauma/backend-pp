<?php

namespace App\Console\Commands;

use App\Models\ChatUser;
use App\Models\Conversation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off cleanup for patients who already had SEVERAL open conversations when the
 * one-open-per-patient rule landed (2026-09-28).
 *
 * The rule is enforced when a conversation is OPENED, so it cannot fix what already exists:
 * a patient who accumulated three open threads before the deploy keeps all three, and the
 * staff inbox shows three rows for one person - the thing the rule exists to prevent.
 *
 * KEEPS THE LATEST, closes the rest. "Latest" is last_message_at, falling back to id for a
 * thread that never received a message. That matches how the queue already sorts, so the row
 * staff have been looking at is the one that survives.
 *
 * closed_by_chat_user_id is left NULL on purpose: nobody ended these conversations, the system
 * tidied them. A staff name there would be a small lie in an audit trail.
 *
 * Safe to re-run: a patient down to one open thread is skipped.
 *
 * Delete this command once it has been run everywhere.
 */
class CloseExtraOpenConversations extends Command
{
    protected $signature = 'chat:close-extra-open-threads
                            {--apply : Write the changes. Without this the command only reports.}';

    protected $description = 'Close all but the latest open conversation for any patient holding several (pre-2026-09-28 rows).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        if (!$apply) {
            $this->warn('DRY RUN - nothing will be written. Re-run with --apply to commit.');
        }

        $patientType = (string) config('chat.types.patient', 'patient');

        /*
         * Patients with more than one OPEN conversation. Counted through participants because
         * that is the only link between a patient identity and its threads - conversations
         * carry the department, never the patient.
         */
        $offenders = DB::table('conversation_participants as cp')
            ->join('conversations as c', 'c.id', '=', 'cp.conversation_id')
            ->join('chat_users as u', 'u.id', '=', 'cp.chat_user_id')
            ->whereNull('c.closed_at')
            ->whereNotNull('c.department_chat_user_id')
            ->where('u.external_type', $patientType)
            ->groupBy('u.id')
            ->havingRaw('COUNT(DISTINCT c.id) > 1')
            ->pluck('u.id')
            ->all();

        if ($offenders === []) {
            $this->info('Nothing to do: no patient has more than one open conversation.');

            return self::SUCCESS;
        }

        $this->info('Patients with several open conversations: ' . count($offenders));
        $this->newLine();

        $closed = 0;

        foreach ($offenders as $chatUserId) {
            $patient = ChatUser::find($chatUserId);

            $open = Conversation::query()
                ->whereNull('closed_at')
                ->whereNotNull('department_chat_user_id')
                ->whereHas('participants', function ($query) use ($chatUserId) {
                    $query->where('chat_user_id', $chatUserId);
                })
                // Newest activity first, exactly as the queue orders it, so the thread staff
                // have been working in is the one kept.
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->get();

            if ($open->count() < 2) {
                continue;
            }

            $keep = $open->shift();

            $this->line(sprintf(
                '  %s (chat_user %d) - %d open, keeping %s',
                $patient?->name ?: 'patient',
                $chatUserId,
                $open->count() + 1,
                $keep->uuid
            ));

            foreach ($open as $conversation) {
                $this->line(sprintf(
                    '      close %s  case %s  last activity %s',
                    $conversation->uuid,
                    $conversation->case_id ?? '-',
                    $conversation->last_message_at?->toDateTimeString() ?? 'never'
                ));

                if ($apply) {
                    DB::transaction(function () use ($conversation) {
                        $conversation->update([
                            'closed_at' => now(),
                            // Nobody ended these - the system tidied them. See the class docblock.
                            'closed_by_chat_user_id' => null,
                            // A closed thread is nobody's to answer.
                            'assigned_chat_user_id' => null,
                        ]);
                    });
                }

                $closed++;
            }
        }

        $this->newLine();
        $this->info(($apply ? 'Closed: ' : 'Would close: ') . $closed);
        $this->line('Each patient is left with exactly one open conversation; the rest move to the archive panel.');

        return self::SUCCESS;
    }
}
