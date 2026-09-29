<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Storing and describing chat attachments.
 *
 * ── WHERE THE FILES GO, AND WHAT THAT MEANS ─────────────────────────────────────────────────
 * The PUBLIC disk, matching what FunnelApiController already does for form uploads. This was a
 * deliberate decision on 2026-09-28 after the alternative was put forward, NOT an oversight:
 * a stored attachment is fetchable by anyone holding its URL, with no authentication, no
 * expiry, and no check that the conversation still exists. The random filename is the only
 * thing between a leaked link and the document.
 *
 * Two consequences to keep in mind rather than rediscover:
 *   - Deleting a conversation does not revoke its attachments. Nothing here removes files.
 *   - The URL is built from the disk, so moving the disk to a private one silently breaks every
 *     link already sent. That change needs a download endpoint, not just a config edit.
 *
 * ── THE FILENAME CARRIES THE ORIGINAL NAME ──────────────────────────────────────────────────
 * chat_messages.attachment is a single string, so there is nowhere to record what the patient
 * called the file. The stored name is "<random>__<sanitised original>.<ext>", which lets the UI
 * show something recognisable instead of a UUID while keeping the path unguessable.
 *
 * PHI: filenames and contents are patient data. Never log either.
 */
class ChatAttachmentService
{
    /** Separates the random prefix from the human part of a stored filename. */
    private const SEPARATOR = '__';

    /**
     * The file types a chat attachment may be, for the `mimes:` rule on BOTH upload endpoints.
     *
     * HARDCODED on purpose, not read from config/chat.php. It used to be config('chat.attachments.mimes'),
     * and a server whose config cache predated that key validated against an EMPTY list - every file,
     * PDF included, was refused with "The file field must be a file of type: ." A constant cannot go
     * stale that way. Changing the list is now a code change and a deploy.
     *
     * Image, PDF, Word, Excel. `mimes:` checks the type guessed from the file CONTENTS, not the name the
     * client sent, so a renamed executable is refused - and so is a real file PHP cannot identify (heic is
     * the likely one on older mime maps). Keep in step with Medhiwa's PortalChatClient::ALLOWED_ATTACHMENT_EXTENSIONS and its chat file input's `accept` list.
     */
    public const ALLOWED_EXTENSIONS = [
        // image
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif',
        // pdf
        'pdf',
        // word
        'doc', 'docx',
        // excel
        'xls', 'xlsx', 'csv',
    ];

    /**
     * The `mimes` failure message for both upload endpoints. Because the type comes from the CONTENTS,
     * a renamed or blank file fails with its own extension sitting in Laravel's default "must be a file
     * of type: ..." list, which reads as a bug. Same wording as Medhiwa's PortalChatClient::ATTACHMENT_TYPE_MESSAGE.
     */
    public const TYPE_MESSAGE = 'This file\'s contents are not a recognised image, PDF, Word or Excel '
        . 'document - it may have been renamed from another type, or be empty or damaged. Allowed: :values.';

    /**
     * Store an uploaded file and describe it for the client.
     *
     * @return array{attachment:string, url:string, name:string, size:int, extension:string}
     */
    public function store(UploadedFile $file): array
    {
        $disk = (string) config('chat.attachments.disk', 'public');
        $folder = trim((string) config('chat.attachments.path', 'chat-attachments'), '/');

        $extension = strtolower($file->getClientOriginalExtension());

        // getClientOriginalExtension() can be empty or wrong - it is client-supplied. The
        // validator has already vouched for the real type, so fall back to what PHP guessed
        // rather than storing a file with no extension at all.
        if ($extension === '') {
            $extension = strtolower((string) $file->guessExtension());
        }

        $stored = Str::random(32) . self::SEPARATOR . $this->safeName($file) . ($extension !== '' ? '.' . $extension : '');

        $path = $file->storeAs($folder, $stored, $disk);

        return [
            // What goes into chat_messages.attachment.
            'attachment' => (string) $path,
            'url' => Storage::disk($disk)->url((string) $path),
            'name' => $this->displayName((string) $path),
            'size' => (int) $file->getSize(),
            'extension' => $extension,
        ];
    }

    /**
     * The name to show for a stored attachment path.
     *
     * Falls back to the raw basename for anything that did not come from store() - an older
     * row, or a value written directly - so a message never renders a blank link.
     */
    public function displayName(string $path): string
    {
        $base = basename($path);
        $at = strpos($base, self::SEPARATOR);

        return $at === false ? $base : substr($base, $at + strlen(self::SEPARATOR));
    }

    /** The public URL for a stored path, or null when there is nothing stored. */
    public function url(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        // Anything already absolute is passed through: older rows may hold a full URL.
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::disk((string) config('chat.attachments.disk', 'public'))->url($path);
    }

    /**
     * Whether $path is a file that store() put there - the ONLY thing a send may attach.
     *
     * Both send endpoints used to accept `attachment` as any string up to 2048 characters and
     * store it verbatim. url() passes an absolute URL straight through, so a sender could post
     * "https://anywhere/..." and it rendered on the other side as a paperclip "attachment" - a
     * phishing link wearing the clinic's UI. It also let a message point at a file that was
     * never uploaded, which renders as a link to a 404.
     *
     * Accepted: a relative path directly inside the chat folder, no traversal, and the file
     * exists on the chat disk. Nothing else - not a URL, not another folder.
     */
    public function isStoredReference(?string $path): bool
    {
        $path = trim((string) $path);

        if ($path === '' || str_contains($path, '..') || str_contains($path, '\\')) {
            return false;
        }

        $folder = trim((string) config('chat.attachments.path', 'chat-attachments'), '/');

        // Exactly one level inside the folder: "<folder>/<file>", as store() writes it.
        if (dirname($path) !== $folder || basename($path) === '') {
            return false;
        }

        return Storage::disk((string) config('chat.attachments.disk', 'public'))->exists($path);
    }

    /**
     * The one-line list preview for a message. A file sent on its own has no text, and an
     * empty preview in the queue reads as a blank or broken message.
     */
    public function preview(?string $message, ?string $attachment): string
    {
        $text = trim((string) $message);

        if ($text !== '') {
            return mb_substr($text, 0, 140);
        }

        return trim((string) $attachment) !== ''
            ? mb_substr('Attachment: ' . $this->displayName((string) $attachment), 0, 140)
            : '';
    }

    /**
     * The original filename, reduced to something safe to put in a path.
     *
     * Directory separators and dots are stripped rather than escaped: this becomes part of a
     * filename, and a name like "../../../etc/passwd" must not be able to steer where the file
     * lands. Length is capped so a very long name cannot push the path past the filesystem
     * limit and fail the write.
     */
    private function safeName(UploadedFile $file): string
    {
        $original = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);

        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $original);
        $clean = trim((string) $clean, '-._');

        if ($clean === '') {
            $clean = 'attachment';
        }

        return Str::limit($clean, 60, '');
    }
}
