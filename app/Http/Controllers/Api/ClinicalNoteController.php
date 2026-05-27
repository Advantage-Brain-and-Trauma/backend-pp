<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AhcsAttendance;
use Illuminate\Http\Request;

class ClinicalNoteController extends Controller
{
    private const PREVIEW_ALLOWED_HOSTS = ['10.0.0.23', '10.0.0.24'];
    private const STORAGE_BASE = 'http://10.0.0.23/storage/files/mh';
    private const WEBDAV_BASE = 'http://10.0.0.24/webdav/mh';

    /**
     * GET /api/clinical-note/{appointmentId}
     *
     * Fetches clinical note attachment data from external API by appointment ID.
     */
    public function show(Request $request): JsonResponse
    {
        try {

            $appointmentId = $request->query('appt_id');
            $appointmentId = AhcsAttendance::findorFail($appointmentId)->id;

            if(!$appointmentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid appointment ID.',
                ], 400);
            }

            $baseUrl = rtrim((string) config('services.clinical_notes.base_url'), '/');

            if ($baseUrl === '') {
                Log::channel('patient_form')->error('Clinical note base URL is not configured');

                return response()->json([
                    'success' => false,
                    'message' => 'Clinical note API is not configured.',
                ], 500);
            }

            $response = Http::timeout(30)
                ->acceptJson()
                ->get($baseUrl . '/attachments/' . $appointmentId);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => $response->json('message') ?? 'Unable to fetch clinical note.',
                    'status_code' => $response->status(),
                ], $response->status());
            }

            return response()->json([
                'success' => true,
                'data' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('patient_form')->error('Clinical note API error', [
                'appointment_id' => $appointmentId,
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
     * GET /api/clinical-note-preview?url=10.0.0.23/storage/files/mh/...pdf
     *
     * Accepts Medhiwa hover URL and returns document bytes inline for preview.
     */
    public function preview(Request $request): Response|JsonResponse
    {
        $rawUrl = trim((string) $request->query('url', ''));

        // Support filename-only input: build URL from ahcs_attachment_logs first.
        if ($rawUrl === '') {
            $generated = $this->buildUrlFromFilename(
                trim((string) $request->query('filename', '')),
                $request->query('attend_id'),
                $request->query('case_id')
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
        ]);
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

        $split = implode('/', str_split((string) $row->case_id));
        $folder = trim((string) $row->folder, '/\\');
        $subFolder = trim((string) $row->sub_folder, '/\\');
        $file = (string) $row->filename;

        $base = ((string) ($row->serverType ?? '2') === '1') ? self::WEBDAV_BASE : self::STORAGE_BASE;

        $url = rtrim($base, '/')
            . '/' . $split
            . '/' . rawurlencode($folder)
            . '/' . rawurlencode($subFolder)
            . '/' . rawurlencode($file);

        return [
            'url' => $url,
            'serverType' => (int) ($row->serverType ?? 2),
            'case_id' => (int) $row->case_id,
            'attend_id' => (int) $row->attend_id,
            'folder' => $row->folder,
            'sub_folder' => $row->sub_folder,
        ];
    }
}
