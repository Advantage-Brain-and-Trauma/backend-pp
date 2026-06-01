<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AhcsCase;
use App\Services\AmdClinicalNoteService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;

class ClinicalNoteController extends Controller
{
    public function __construct(private readonly AmdClinicalNoteService $amdClinicalNoteService)
    {
    }

    private const PREVIEW_ALLOWED_HOSTS = ['10.0.0.23', '10.0.0.24'];
    private const STORAGE_BASE = 'http://10.0.0.23/storage/files/mh';
    private const LOCAL_WEBDAV_BASE = 'http://10.0.0.23/webdav/mh';
    private const WEBDAV_BASE = 'http://10.0.0.24/webdav/mh';
    private const STORAGE_FS_BASE = '/files/mh';
    private const LOCAL_WEBDAV_FS_BASE = '/webdav/mh';

    /**
     * GET /api/clinical-note?case_id={caseId}
     *
     * Fetch clinical notes list from Medhiwa project API by patient.
     */
    public function show(Request $request): JsonResponse
    {
        $caseId = $request->query('case_id');
        $patientId = auth()->user()->patient_id;

        try {
            if (empty($caseId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case Id is required.',
                ], 422);
            }

            $isValidCaseForPatient = AhcsCase::where('id', $caseId)
                ->where('patient_id', $patientId)
                ->exists();

            if (!$isValidCaseForPatient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Case Id for this patient.',
                ], 422);
            }

            $result = $this->amdClinicalNoteService->getClinicalNotesByMedhiwaPatient((int) $patientId, (int) $caseId);
            if (empty($result['status'])) {
                return response()->json([
                    'success' => false,
                    'message' => (string) ($result['message'] ?? 'Unable to fetch clinical notes.'),
                    'status_code' => $result['status_code'] ?? null,
                    'error' => $result['error'] ?? null,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $result['notes'] ?? [],
            ], 200, [], JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            Log::channel('patient_form')->error('Clinical note API error', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching clinical note.',
            ], 500);
        }
    }

    /**
     * GET /api/clinical-note/view/{noteId}?case_id={caseId}
     *
     * Stream note PDF inline from Medhiwa project API.
     */
    public function viewNote(Request $request, int|string $noteId): Response|JsonResponse
    {
        return $this->proxyNotePdf($request, $noteId, true);
    }

    /**
     * GET /api/clinical-note/download/{noteId}?case_id={caseId}
     *
     * Download note PDF from Medhiwa project API.
     */
    public function downloadNote(Request $request, int|string $noteId): Response|JsonResponse
    {
        return $this->proxyNotePdf($request, $noteId, false);
    }

    /**
     * GET /api/clinical-note-preview?url=10.0.0.23/storage/files/mh/...pdf
     *
     * Accepts Medhiwa hover URL and returns document bytes inline for preview.
     */
    public function preview(Request $request): Response|JsonResponse
    {
        $rawUrl = trim((string) $request->query('url', ''));
        $caseId = $request->query('case_id');
        $patientId = auth()->user()->patient_id;

        if (empty($caseId)) {
            return response()->json([
                'success' => false,
                'message' => 'Case Id is required.',
            ], 422);
        }

        $isValidCaseForPatient = AhcsCase::where('id', $caseId)
            ->where('patient_id', $patientId)
            ->exists();

        if (!$isValidCaseForPatient) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Case Id for this patient.',
            ], 422);
        }

        // Support filename-only input: build URL from ahcs_attachment_logs first.
        if ($rawUrl === '') {
            $generated = $this->buildUrlFromFilename(
                trim((string) $request->query('filename', '')),
                $request->query('attend_id'),
                $caseId
            );

            if (!empty($generated['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $generated['error'],
                ], 422);
            }

            $rawUrl = (string) $generated['url'];
        }

        if ($rawUrl === '') {
            return response()->json([
                'success' => false,
                'message' => 'Document URL is required.',
            ], 422);
        }

        $normalizedUrl = $this->normalizePreviewUrl($rawUrl);
        if (!$normalizedUrl) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or unsupported document URL.',
            ], 422);
        }

        try {
            $remote = Http::timeout(30)->withHeaders([
                'Accept' => '*/*',
            ])->get($normalizedUrl);

            if ($remote->failed() || $remote->body() === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch preview document.',
                    'status_code' => $remote->status(),
                ], $remote->status() > 0 ? $remote->status() : 502);
            }

            $parsed = parse_url($normalizedUrl);
            $path = (string) ($parsed['path'] ?? 'document.pdf');
            $fileName = basename($path) ?: 'document.pdf';
            $contentType = $remote->header('Content-Type') ?: $this->detectContentTypeByName($fileName);

            return response($remote->body(), 200)
                ->header('Content-Type', $contentType)
                ->header('Content-Disposition', 'inline; filename="' . $fileName . '"')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        } catch (\Throwable $e) {
            Log::channel('patient_form')->error('Clinical note preview API error', [
                'url' => $rawUrl,
                'normalized_url' => $normalizedUrl,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error previewing clinical document.',
            ], 500);
        }
    }

    /**
     * GET /api/clinical-note-preview-url?filename=...&attend_id=...&case_id=...
     *
     * Generates a document URL from ahcs_attachment_logs using filename.
     */
    public function generatePreviewUrl(Request $request): JsonResponse
    {
        $filename = trim((string) $request->query('filename', ''));
        $attendId = $request->query('attend_id');
        $caseId = $request->query('case_id');
        $patientId = auth()->user()->patient_id;

        if (empty($caseId)) {
            return response()->json([
                'success' => false,
                'message' => 'Case Id is required.',
            ], 422);
        }

        $isValidCaseForPatient = AhcsCase::where('id', $caseId)
            ->where('patient_id', $patientId)
            ->exists();

        if (!$isValidCaseForPatient) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Case Id for this patient.',
            ], 422);
        }

        $generated = $this->buildUrlFromFilename($filename, $attendId, $caseId);

        if (!empty($generated['error'])) {
            return response()->json([
                'success' => false,
                'message' => $generated['error'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'filename' => $filename,
                'url' => $generated['url'],
                'serverType' => $generated['serverType'],
                'case_id' => $generated['case_id'],
                'attend_id' => $generated['attend_id'],
                'folder' => $generated['folder'],
                'sub_folder' => $generated['sub_folder'],
            ],
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }

    private function normalizePreviewUrl(string $rawUrl): ?string
    {
        $url = $rawUrl;
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'http://' . ltrim($url, '/');
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['host']) || empty($parts['path'])) {
            return null;
        }

        $host = $parts['host'];
        $path = $parts['path'];
        $allowedPath = str_starts_with($path, '/storage/files/mh/') || str_starts_with($path, '/webdav/mh/');

        if (!in_array($host, self::PREVIEW_ALLOWED_HOSTS, true) || !$allowedPath) {
            return null;
        }

        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return 'http://' . $host . $path . $query;
    }

    private function detectContentTypeByName(string $fileName): string
    {
        return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }

    private function buildUrlFromFilename(string $filename, mixed $attendId = null, mixed $caseId = null): array
    {
        if ($filename === '') {
            return ['error' => 'filename is required.'];
        }

        $query = DB::connection('ahcs')
            ->table('ahcs_attachment_logs')
            ->select('id', 'case_id', 'attend_id', 'folder', 'sub_folder', 'filename', 'serverType')
            ->where('filename', $filename);

        if (!empty($attendId)) {
            $query->where('attend_id', $attendId);
        }

        if (!empty($caseId)) {
            $query->where('case_id', $caseId);
        }

        $row = $query->orderByDesc('id')->first();

        if (!$row) {
            return ['error' => 'No attachment record found for this filename.'];
        }

        $file = (string) $row->filename;
        $url = $this->resolvePreferredAttachmentUrl($row);

        return [
            'url' => $url,
            'serverType' => (int) ($row->serverType ?? 2),
            'case_id' => (int) $row->case_id,
            'attend_id' => (int) $row->attend_id,
            'folder' => $row->folder,
            'sub_folder' => $row->sub_folder,
        ];
    }

    private function resolvePreferredAttachmentUrl(object $row): string
    {
        $caseId = (string) ($row->case_id ?? '');
        $folder = trim((string) ($row->folder ?? ''), '/\\');
        $subFolder = trim((string) ($row->sub_folder ?? ''), '/\\');
        $filename = trim((string) ($row->filename ?? ''), '/\\');
        $attendId = (string) ($row->attend_id ?? '');
        $serverType = (string) ($row->serverType ?? '2');

        if ($caseId === '' || $folder === '' || $filename === '') {
            return '';
        }

        $split = implode('/', str_split($caseId));
        // Medhiwa Add/Edit Patient uploads are stored on WebDAV:
        // /webdav/mh/{split_case_id}/{folder}/{sub_folder}/{filename}
        // Those rows are commonly serverType=2 and often not tied to attend_id.
        // Prefer WebDAV first for that shape so clinical-note preview URLs resolve correctly.
        if ($serverType === '1') {
            $bases = [self::WEBDAV_BASE, self::LOCAL_WEBDAV_BASE, self::STORAGE_BASE];
        } elseif ($serverType === '2') {
            // Add/Edit Patient uploads in Medhiwa are saved under /files/mh first,
            // and only then copied to WebDAV as a secondary location.
            $bases = [self::STORAGE_BASE, self::LOCAL_WEBDAV_BASE, self::WEBDAV_BASE];
        } elseif ($attendId === '' || $attendId === '0') {
            $bases = [self::STORAGE_BASE, self::LOCAL_WEBDAV_BASE, self::WEBDAV_BASE];
        } else {
            $bases = [self::STORAGE_BASE, self::LOCAL_WEBDAV_BASE, self::WEBDAV_BASE];
        }

        $folderVariants = array_values(array_unique([$folder, strtolower($folder), strtoupper($folder)]));
        $subVariants = array_values(array_unique([$subFolder, strtolower($subFolder), strtoupper($subFolder)]));
        if ($subFolder === '') {
            $subVariants = [''];
        }

        $fallback = '';

        foreach ($bases as $base) {
            $base = rtrim($base, '/');
            foreach ($folderVariants as $f) {
                foreach ($subVariants as $s) {
                    $url = '';
                    if ($s !== '') {
                        $url = $base
                            . '/' . $split
                            . '/' . rawurlencode($f)
                            . '/' . rawurlencode($s)
                            . '/' . rawurlencode($filename);
                    } else {
                        $url = $base
                            . '/' . $split
                            . '/' . rawurlencode($f)
                            . '/' . rawurlencode($filename);
                    }

                    if ($fallback === '') {
                        $fallback = $url;
                    }

                    if ($this->localAttachmentExists($base, $split, $f, $s, $filename)) {
                        return $url;
                    }
                }
            }
        }

        return $fallback;
    }

    private function localAttachmentExists(string $baseUrl, string $splitCaseId, string $folder, string $subFolder, string $filename): bool
    {
        $fsBase = match (rtrim($baseUrl, '/')) {
            self::STORAGE_BASE => self::STORAGE_FS_BASE,
            self::LOCAL_WEBDAV_BASE => self::LOCAL_WEBDAV_FS_BASE,
            default => null,
        };

        if ($fsBase === null) {
            return false;
        }

        $path = rtrim($fsBase, '/')
            . '/' . $splitCaseId
            . '/' . $folder;

        if ($subFolder !== '') {
            $path .= '/' . $subFolder;
        }

        $path .= '/' . $filename;

        return is_file($path);
    }

    private function enrichClinicalPayload(mixed $payload, string $caseId, string $attendId): mixed
    {
        if (!is_array($payload)) {
            return $payload;
        }

        if ($this->isAttachmentLikeNode($payload)) {
            return $this->enrichAttachmentNode($payload, $caseId, $attendId);
        }

        foreach ($payload as $key => $value) {
            $payload[$key] = $this->enrichClinicalPayload($value, $caseId, $attendId);
        }

        return $payload;
    }

    private function isAttachmentLikeNode(array $node): bool
    {
        return array_key_exists('filename', $node)
            || array_key_exists('file_name', $node)
            || array_key_exists('name', $node)
            || array_key_exists('url', $node)
            || array_key_exists('path', $node);
    }

    private function enrichAttachmentNode(array $node, string $caseId, string $attendId): array
    {
        $rawFileName = $node['filename'] ?? $node['file_name'] ?? $node['name'] ?? null;
        $filename = is_string($rawFileName) ? trim($rawFileName) : '';

        $rawUrl = '';
        if (isset($node['url']) && is_string($node['url'])) {
            $rawUrl = trim($node['url']);
        } elseif (isset($node['path']) && is_string($node['path'])) {
            $rawUrl = trim($node['path']);
        }

        if ($filename === '' && $rawUrl !== '') {
            $parsedPath = (string) (parse_url($rawUrl, PHP_URL_PATH) ?? $rawUrl);
            $filename = basename($parsedPath);
        }

        $fullUrl = '';
        if ($rawUrl !== '') {
            $fullUrl = $this->normalizePreviewUrl($rawUrl) ?? '';
        }

        if ($fullUrl === '' && $filename !== '') {
            $generated = $this->buildUrlFromFilename($filename, $attendId, $caseId);
            if (empty($generated['error']) && !empty($generated['url'])) {
                $fullUrl = (string) $generated['url'];
            }
        }

        if ($filename !== '') {
            $node['filename'] = $filename;
        }

        if ($fullUrl !== '') {
            $node['url'] = $fullUrl;
        }

        return $node;
    }

    private function proxyNotePdf(Request $request, int|string $noteId, bool $inline): Response|JsonResponse
    {
        $caseId = $request->query('case_id');
        $patientId = auth()->user()->patient_id;

        if (empty($caseId)) {
            return response()->json([
                'success' => false,
                'message' => 'Case Id is required.',
            ], 422);
        }

        $isValidCaseForPatient = AhcsCase::where('id', $caseId)
            ->where('patient_id', $patientId)
            ->exists();

        if (!$isValidCaseForPatient) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Case Id for this patient.',
            ], 422);
        }

        try {
            $baseUrl = rtrim((string) config('services.clinical_notes.base_url', ''), '/');
            $endpoint = $inline ? 'view-pdf' : 'download-pdf';
            $remoteUrl = $baseUrl . '/clinical-notes-documents/' . $endpoint . '/' . $noteId;

            $query = array_filter([
                'patientName' => $request->query('patientName'),
                'documentId' => $request->query('documentId'),
            ], static fn($value) => $value !== null && $value !== '');

            if ($baseUrl !== '') {
                $remote = Http::timeout(120)->withHeaders([
                    'Accept' => 'application/pdf,*/*',
                ])->get($remoteUrl, $query);

                if ($remote->ok() && $remote->body() !== '') {
                    $filename = 'clinical_note_' . $noteId . '.pdf';
                    $contentType = $remote->header('Content-Type') ?: 'application/pdf';
                    $pdfBytes = $remote->body();

                    if (!$inline) {
                        return response()->streamDownload(
                            static function () use ($pdfBytes): void {
                                echo $pdfBytes;
                            },
                            $filename,
                            [
                                'Content-Type' => $contentType,
                                'Content-Length' => (string) strlen($pdfBytes),
                                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                            ]
                        );
                    }

                    return response($pdfBytes, 200)
                        ->header('Content-Type', $contentType)
                        ->header('Content-Disposition', 'inline; filename="' . $filename . '"')
                        ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
                }
            }

            $detailRes = $this->amdClinicalNoteService->getClinicalNoteDetail((int) $noteId);
            if (empty($detailRes['ok']) || empty($detailRes['json']) || !is_array($detailRes['json'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch clinical note detail.',
                ], 502);
            }

            $sourcePdfUrl = $this->findSourcePdfUrl($detailRes['json'], (string) $caseId);
            if ($sourcePdfUrl !== '') {
                $remote = Http::timeout(60)->withHeaders([
                    'Accept' => 'application/pdf,*/*',
                ])->get($sourcePdfUrl);

                if ($remote->ok() && $remote->body() !== '') {
                    $filename = 'clinical_note_' . $noteId . '.pdf';

                    return response($remote->body(), 200)
                        ->header('Content-Type', 'application/pdf')
                        ->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"')
                        ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
                }
            }

            $pdfBytes = $this->buildClinicalNotePdf($detailRes['json'], (int) $noteId);
            $filename = 'clinical_note_' . $noteId . '.pdf';

            if (!$inline) {
                return response()->streamDownload(
                    static function () use ($pdfBytes): void {
                        echo $pdfBytes;
                    },
                    $filename,
                    [
                        'Content-Type' => 'application/pdf',
                        'Content-Length' => (string) strlen($pdfBytes),
                        'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    ]
                );
            }

            return response($pdfBytes, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        } catch (\Throwable $e) {
            Log::channel('patient_form')->error('Clinical note PDF proxy API error', [
                'note_id' => $noteId,
                'case_id' => $caseId,
                'inline' => $inline,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching clinical note PDF.',
            ], 500);
        }
    }

    private function findSourcePdfUrl(array $detail, string $caseId = ''): string
    {
        $candidates = [];
        $this->collectPdfUrlCandidates($detail, $candidates);

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizePreviewUrl($candidate);
            if ($normalized !== null && $normalized !== '') {
                return $normalized;
            }
        }

        $filenameCandidates = [];
        $this->collectPdfFilenameCandidates($detail, $filenameCandidates);
        $filenameCandidates = array_values(array_unique(array_filter($filenameCandidates)));

        $header = is_array($detail['header'] ?? null) ? $detail['header'] : [];
        $attendId = (string) ($header['appointmentId'] ?? '');

        foreach ($filenameCandidates as $filename) {
            $generated = $this->buildUrlFromFilename($filename, $attendId, $caseId);
            if (!empty($generated['url'])) {
                $normalized = $this->normalizePreviewUrl((string) $generated['url']);
                if ($normalized !== null && $normalized !== '') {
                    return $normalized;
                }
            }
        }

        foreach ($filenameCandidates as $filename) {
            $generated = $this->buildUrlFromFilename($filename, null, $caseId);
            if (!empty($generated['url'])) {
                $normalized = $this->normalizePreviewUrl((string) $generated['url']);
                if ($normalized !== null && $normalized !== '') {
                    return $normalized;
                }
            }
        }

        return '';
    }

    private function collectPdfUrlCandidates(mixed $value, array &$candidates): void
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return;
            }

            $lower = strtolower($trimmed);
            if (str_ends_with($lower, '.pdf')
                || str_contains($lower, '/storage/files/mh/')
                || str_contains($lower, '/webdav/mh/')) {
                $candidates[] = $trimmed;
            }

            return;
        }

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $item) {
            $this->collectPdfUrlCandidates($item, $candidates);
        }
    }

    private function collectPdfFilenameCandidates(mixed $value, array &$candidates): void
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '' && str_ends_with(strtolower($trimmed), '.pdf')) {
                $path = (string) (parse_url($trimmed, PHP_URL_PATH) ?? $trimmed);
                $name = basename($path);
                if ($name !== '') {
                    $candidates[] = $name;
                }
            }

            return;
        }

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            if (is_string($key) && in_array(strtolower($key), ['filename', 'file_name', 'name'], true) && is_string($item)) {
                $name = trim($item);
                if ($name !== '' && str_ends_with(strtolower($name), '.pdf')) {
                    $candidates[] = basename($name);
                }
            }

            $this->collectPdfFilenameCandidates($item, $candidates);
        }
    }

    private function buildClinicalNotePdf(array $detail, int $noteId): string
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $header = is_array($detail['header'] ?? null) ? $detail['header'] : [];
        $templateName = (string) ($header['templateVersion']['templateName'] ?? 'Clinical Note');
        $serviceDate = (string) ($header['serviceDate'] ?? '');
        $providerName = (string) ($header['createdOrUpdatedByUser']['fullName'] ?? '');
        $isSigned = !empty($header['isSigned']) ? 'Yes' : 'No';
        $appointmentId = (string) ($header['appointmentId'] ?? '');

        $summaryPayload = [
            'noteId' => $noteId,
            'templateName' => $templateName,
            'serviceDate' => $serviceDate,
            'providerName' => $providerName,
            'isSigned' => !empty($header['isSigned']),
            'appointmentId' => $appointmentId,
            'sectionsCount' => is_array($detail['sections'] ?? null) ? count($detail['sections']) : null,
            'noteType' => $detail['noteType'] ?? null,
            'status' => $detail['status'] ?? null,
        ];

        $raw = htmlspecialchars(json_encode($summaryPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        $html = '<html><head><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#111}h1{font-size:18px}table{width:100%;border-collapse:collapse;margin:10px 0}td{border:1px solid #ccc;padding:6px;vertical-align:top}pre{font-size:10px;white-space:pre-wrap;word-break:break-word;border:1px solid #ddd;padding:10px;background:#fafafa}</style></head><body>'
            . '<h1>Clinical Note #' . $noteId . '</h1>'
            . '<table>'
            . '<tr><td><strong>Template</strong></td><td>' . htmlspecialchars($templateName) . '</td></tr>'
            . '<tr><td><strong>Service Date</strong></td><td>' . htmlspecialchars($serviceDate) . '</td></tr>'
            . '<tr><td><strong>Provider</strong></td><td>' . htmlspecialchars($providerName) . '</td></tr>'
            . '<tr><td><strong>Signed</strong></td><td>' . htmlspecialchars($isSigned) . '</td></tr>'
            . '<tr><td><strong>Appointment Id</strong></td><td>' . htmlspecialchars($appointmentId) . '</td></tr>'
            . '</table>'
            . '<h2>Clinical Note Summary</h2>'
            . '<pre>' . $raw . '</pre>'
            . '</body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
