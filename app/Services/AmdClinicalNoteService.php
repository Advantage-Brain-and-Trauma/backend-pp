<?php

namespace App\Services;

use App\Models\AdvancedmdToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AmdClinicalNoteService
{
    private string $ehrOfficeCode;
    private string $ehrUsername;
    private string $ehrPassword;
    private string $ehrAppName;
    private string $ehrLoginUrl;

    public function __construct()
    {
        $this->ehrOfficeCode = (string) env('AMD_EHR_OFFICE_CODE', env('AMD_OFFICE_CODE', ''));
        $this->ehrUsername = (string) env('AMD_EHR_USERNAME', env('AMD_USERNAME', ''));
        $this->ehrPassword = (string) env('AMD_EHR_PASSWORD', env('AMD_PASSWORD', ''));
        $this->ehrAppName = (string) env('AMD_EHR_APP_NAME', env('AMD_APP_NAME', 'TEMP'));
        $this->ehrLoginUrl = (string) env('AMD_EHR_LOGIN_URL', env('AMD_LOGIN_URL', ''));
    }

    public function getClinicalNotesByMedhiwaPatient(int $medhiwaPatientId, ?int $caseId = null): array
    {
        $mappingQuery = DB::connection('ahcs')
            ->table('ahcs_advancedmd_patient')
            ->where('medhiwa_patient_id', $medhiwaPatientId);

        if (!empty($caseId)) {
            $mappingQuery->where('medhiwa_case_id', $caseId);
        }

        $mapping = $mappingQuery->first();

        if (!$mapping && !empty($caseId)) {
            $mapping = DB::connection('ahcs')
                ->table('ahcs_advancedmd_patient')
                ->where('medhiwa_patient_id', $medhiwaPatientId)
                ->first();
        }

        if (!$mapping || empty($mapping->advancedmd_patient_id)) {
            return ['status' => false, 'message' => 'AdvancedMD patient mapping not found for this patient/case.'];
        }

        $amdPatientId = (int) preg_replace('/\D/', '', (string) $mapping->advancedmd_patient_id);
        if ($amdPatientId <= 0) {
            return ['status' => false, 'message' => 'Invalid AdvancedMD Patient ID in mapping table.'];
        }

        $notesRes = $this->requestEhr('GET', '/clinicalnotes/notes', ['patientId' => $amdPatientId]);
        if (!$notesRes['ok']) {
            return [
                'status' => false,
                'message' => 'AMD clinical notes API failed.',
                'status_code' => $notesRes['status'] ?? null,
                'error' => $notesRes['error'] ?? null,
            ];
        }

        $notes = $notesRes['json'];
        if (!is_array($notes)) {
            $notes = [];
        }

        $final = [];
        foreach ($notes as $note) {
            $noteId = (int) ($note['id'] ?? 0);
            if ($noteId <= 0) {
                continue;
            }

            $headerRes = $this->requestEhr('GET', '/clinicalnotes/noteheaders', ['noteid' => $noteId]);
            $header = $headerRes['ok'] && is_array($headerRes['json']) ? $headerRes['json'] : [];

            $appointmentId = (string) ($header['appointmentId'] ?? '');
            $medhiwaAppointmentId = null;
            if ($appointmentId !== '') {
                $map = DB::connection('ahcs')
                    ->table('ahcs_advancedmd_appointments')
                    ->where('amd_appointmentid', $appointmentId)
                    ->first();
                $medhiwaAppointmentId = $map->medhiwa_appointmentid ?? null;
            }

            $final[] = [
                'date' => $note['date'] ?? null,
                'time' => $note['time'] ?? null,
                'template_name' => $note['templateName'] ?? null,
                'note_id' => $noteId,
                'patient_id' => $note['patientId'] ?? null,
                'appointment_id' => $appointmentId !== '' ? $appointmentId : null,
                'medhiwa_appointmentid' => $medhiwaAppointmentId,
                'raw' => $note,
            ];
        }

        return ['status' => true, 'notes' => $final];
    }

    public function getClinicalNoteDetail(int $noteId): array
    {
        return $this->requestEhr('GET', '/clinicalnotes/notes/' . $noteId);
    }

    private function requestEhr(string $method, string $path, array $query = []): array
    {
        try {
            $token = $this->getEhrToken();
            if (empty($token['token']) || empty($token['server'])) {
            return ['ok' => false, 'error' => 'AMD token unavailable. Check AMD_* environment configuration.'];
            }

            $url = rtrim($token['server'], '/') . $path;
            $response = Http::timeout(60)
                ->acceptJson()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token['token'],
                    'appname' => 'TEMP',
                ])
                ->send($method, $url, ['query' => $query]);

            if ($response->status() === 403) {
                $token = $this->getEhrToken(true);
                if (!empty($token['token']) && !empty($token['server'])) {
                    $url = rtrim($token['server'], '/') . $path;
                    $response = Http::timeout(60)
                        ->acceptJson()
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $token['token'],
                            'appname' => $this->ehrAppName,
                        ])
                        ->send($method, $url, ['query' => $query]);
                }
            }

            if ($response->failed()) {
                return ['ok' => false, 'error' => $response->body(), 'status' => $response->status()];
            }

            return ['ok' => true, 'json' => $response->json(), 'status' => $response->status()];
        } catch (\Throwable $e) {
            Log::channel('patient_form')->error('AMD EHR request failed', ['path' => $path, 'error' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function getEhrToken(bool $forceRefresh = false): array
    {
        $officeCode = $this->ehrOfficeCode;
        $username = $this->ehrUsername;
        $password = $this->ehrPassword;
        $appName = $this->ehrAppName;
        $loginUrl = $this->ehrLoginUrl;

        if ($officeCode === '' || $username === '' || $password === '' || $appName === '' || $loginUrl === '') {
            return [];
        }

        $officeKey = $officeCode . '|ehr|' . $username;
        $tokenRecord = AdvancedmdToken::where('office_key', $officeKey)->first();
        if (!$forceRefresh && $tokenRecord && $tokenRecord->isValid()) {
            return [
                'token' => (string) $tokenRecord->token,
                'server' => $this->toEhrServer((string) $tokenRecord->webserver),
            ];
        }

        $login = $this->login($loginUrl, $officeCode, $username, $password, $appName);
        if (empty($login['token'])) {
            return [];
        }

        $tokenModel = new AdvancedmdToken();
        $table = $tokenModel->getTable();
        $connection = $tokenModel->getConnectionName();

        $updateData = [
            'token' => $login['token'],
            'webserver' => $login['webserver'],
        ];

        if (Schema::connection($connection)->hasColumn($table, 'created_at_timestamp')) {
            $updateData['created_at_timestamp'] = time();
        }
        if (Schema::connection($connection)->hasColumn($table, 'updated_at')) {
            $updateData['updated_at'] = now();
        }
        if (Schema::connection($connection)->hasColumn($table, 'created_at') && !isset($updateData['created_at_timestamp'])) {
            $updateData['created_at'] = now();
        }

        AdvancedmdToken::updateOrCreate(['office_key' => $officeKey], $updateData);

        return [
            'token' => (string) $login['token'],
            'server' => $this->toEhrServer((string) $login['webserver']),
        ];
    }

    private function login(string $loginUrl, string $officeCode, string $username, string $password, string $appName): array
    {
        $msgtime = date('n/j/Y g:i:s A');
        $xml = "<?xml version='1.0' encoding='ISO-8859-1'?>"
            . "<ppmdmsg action='login' class='login' msgtime='{$msgtime}' "
            . "username='" . htmlspecialchars($username, ENT_QUOTES) . "' "
            . "psw='" . htmlspecialchars($password, ENT_QUOTES) . "' "
            . "officecode='" . htmlspecialchars($officeCode, ENT_QUOTES) . "' "
            . "appname='" . htmlspecialchars($appName, ENT_QUOTES) . "' />";

        $resp = Http::withHeaders(['Content-Type' => 'text/xml; charset=ISO-8859-1'])->withBody($xml, 'text/xml')->post($loginUrl);
        $raw = (string) $resp->body();
        $webserver = $this->extractAttr($raw, 'webserver');

        if ($webserver !== null && $webserver !== '') {
            $redirect = rtrim($webserver, '/') . '/xmlrpc/processrequest.aspx';
            $resp = Http::withHeaders(['Content-Type' => 'text/xml; charset=ISO-8859-1'])->withBody($xml, 'text/xml')->post($redirect);
            $raw = (string) $resp->body();
            return ['token' => $this->extractToken($raw), 'webserver' => $webserver];
        }

        return ['token' => $this->extractToken($raw), 'webserver' => $loginUrl];
    }

    private function extractToken(string $xml): ?string
    {
        if (preg_match('/<usercontext[^>]*>(.*?)<\/usercontext>/is', $xml, $m)) {
            return trim((string) $m[1]);
        }
        return null;
    }

    private function extractAttr(string $xml, string $attr): ?string
    {
        if (preg_match('/' . preg_quote($attr, '/') . '="([^"]+)"/', $xml, $m)) {
            return trim((string) $m[1]);
        }
        return null;
    }

    private function toEhrServer(string $webserver): string
    {
        $base = rtrim($webserver, '/');
        $converted = preg_replace('#/processrequest/.*$#', '/ehr-api', $base);
        return is_string($converted) ? $converted : $base;
    }
}
