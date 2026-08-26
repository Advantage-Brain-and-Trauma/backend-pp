<?php

namespace App\Services;

use App\Models\AdvancedmdToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PatientFormAmdSyncService
{
    protected string $officeCode;
    protected string $username;
    protected string $password;
    protected string $appName;
    protected string $loginUrl;

    public function __construct()
    {
        $this->officeCode = (string) config('services.advancedmd.office_code', env('AMD_OFFICE_CODE', ''));
        $this->username = (string) config('services.advancedmd.username', env('AMD_USERNAME', ''));
        $this->password = (string) config('services.advancedmd.password', env('AMD_PASSWORD', ''));
        $this->appName = (string) config('services.advancedmd.app_name', env('AMD_APP_NAME', ''));
        $this->loginUrl = (string) config('services.advancedmd.login_url', env('AMD_LOGIN_URL', ''));
    }

    public function syncDemographics(int $patientId, int $caseId, array $patientUpdateData, array $existingPatient): array
    {
        if (!$this->isConfigured()) {
            return ['status' => 'skipped', 'message' => 'AMD credentials are not configured'];
        }

        $mapping = DB::connection('ahcs')
            ->table('ahcs_advancedmd_patient')
            ->where('medhiwa_patient_id', $patientId)
            ->where('medhiwa_case_id', $caseId)
            ->first();

        if (!$mapping || empty($mapping->advancedmd_patient_id)) {
            return ['status' => 'skipped', 'message' => 'No AMD patient mapping found'];
        }

        $amdPatientId = (string) $mapping->advancedmd_patient_id;

        $merged = array_merge($existingPatient, $patientUpdateData);
        $payloadPatient = $this->buildPatientPayload($amdPatientId, $merged, $existingPatient);

        $tokenData = $this->getToken();
        $url = rtrim($tokenData['webserver'], '/') . '/xmlrpc/processrequest.aspx';
        $msgtime = date('n/j/Y g:i:s A');

        $payload = [
            'ppmdmsg' => [
                '@action' => 'updatepatient',
                '@class' => 'api',
                '@msgtime' => $msgtime,
                '@nocookie' => '0',
                'patientlist' => [
                    'patient' => $payloadPatient,
                ],
            ],
        ];

        $response = $this->postJson($url, $payload, $tokenData['token']);
        $fault = $this->extractAmdFault((string) ($response['body'] ?? ''));
        $httpOk = $response['http_code'] >= 200 && $response['http_code'] < 300;

        return [
            'status' => ($httpOk && $fault === null) ? 'success' : 'failed',
            'http_code' => $response['http_code'],
            'error' => $response['error'],
            'fault' => $fault,
            'response_snippet' => substr((string) ($response['body'] ?? ''), 0, 500),
        ];
    }

    protected function isConfigured(): bool
    {
        return $this->officeCode !== ''
            && $this->username !== ''
            && $this->password !== ''
            && $this->appName !== ''
            && $this->loginUrl !== '';
    }

    protected function getToken(): array
    {
        $tokenRecord = AdvancedmdToken::where('office_key', $this->officeCode)->first();

        if ($tokenRecord && $tokenRecord->isValid()) {
            return [
                'token' => (string) $tokenRecord->token,
                'webserver' => (string) $tokenRecord->webserver,
            ];
        }

        $newToken = $this->login();

        $updateData = [
            'token' => $newToken['token'],
            'webserver' => $newToken['webserver'],
        ];

        $table = (new AdvancedmdToken())->getTable();
        $connection = (new AdvancedmdToken())->getConnectionName();

        if (Schema::connection($connection)->hasColumn($table, 'created_at_timestamp')) {
            $updateData['created_at_timestamp'] = time();
        }

        if (Schema::connection($connection)->hasColumn($table, 'updated_at')) {
            $updateData['updated_at'] = now();
        }

        AdvancedmdToken::updateOrCreate(
            ['office_key' => $this->officeCode],
            $updateData
        );

        return $newToken;
    }

    protected function login(): array
    {
        $msgtime = date('n/j/Y g:i:s A');
        $xml = "<?xml version='1.0' encoding='ISO-8859-1'?>"
            . "<ppmdmsg action='login' class='login' "
            . "msgtime='{$msgtime}' "
            . "username='" . htmlspecialchars($this->username, ENT_QUOTES) . "' "
            . "psw='" . htmlspecialchars($this->password, ENT_QUOTES) . "' "
            . "officecode='" . htmlspecialchars($this->officeCode, ENT_QUOTES) . "' "
            . "appname='" . htmlspecialchars($this->appName, ENT_QUOTES) . "' />";

        $response1 = $this->postXml($this->loginUrl, $xml);
        $raw = (string) ($response1['body'] ?? '');
        $webserver = $this->extractAttr($raw, 'webserver');

        if ($webserver) {
            $redirectUrl = rtrim($webserver, '/') . '/xmlrpc/processrequest.aspx';
            $response2 = $this->postXml($redirectUrl, $xml);
            $raw = (string) ($response2['body'] ?? '');

            $token = $this->extractToken($raw);
            if ($token === null || $token === '') {
                throw new \RuntimeException('AMD redirect login did not return token');
            }

            return ['token' => $token, 'webserver' => $webserver];
        }

        $token = $this->extractToken($raw);
        if ($token === null || $token === '') {
            throw new \RuntimeException('AMD login failed - no token found');
        }

        return ['token' => $token, 'webserver' => $this->loginUrl];
    }

    protected function postXml(string $url, string $xml): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=ISO-8859-1'],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $body = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'body' => $body,
            'http_code' => (int) ($info['http_code'] ?? 0),
            'error' => $error,
        ];
    }

    protected function postJson(string $url, array $data, string $token): array
    {
        $jsonString = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($jsonString === false) {
            return ['body' => '', 'http_code' => 0, 'error' => 'JSON encoding failed: ' . json_last_error_msg()];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonString,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Cookie: token=' . $token,
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 90,
        ]);

        $body = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'body' => $body,
            'http_code' => (int) ($info['http_code'] ?? 0),
            'error' => $error,
        ];
    }

    protected function extractToken(string $xml): ?string
    {
        if (preg_match('/<usercontext[^>]*>(.*?)<\/usercontext>/is', $xml, $m)) {
            return trim((string) $m[1]);
        }

        return null;
    }

    protected function extractAttr(string $xml, string $attr): ?string
    {
        if (preg_match('/' . preg_quote($attr, '/') . '="([^"]+)"/', $xml, $m)) {
            return trim((string) $m[1]);
        }

        return null;
    }

    protected function buildPatientPayload(string $amdPatientId, array $data, array $oldData): array
    {
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));

        $payload = [
            '@id' => $amdPatientId,
            '@name' => trim($lastName . ', ' . $firstName),
            '@preferredfirstname' => $firstName,
            '@preferredlastname' => $lastName,
            '@relationship' => '4',
            '@hipaarelationship' => '18',
        ];

        $newDob = trim((string) ($data['dob'] ?? ''));
        $oldDob = trim((string) ($oldData['dob'] ?? ''));
        if ($newDob !== '' && $newDob !== $oldDob) {
            $payload['@dob'] = $newDob;
        }

        // Only send the SSN when it actually changed, exactly as @dob above.
        //
        // $data is the patient record merged with the submitted answers, so a form
        // that left SSN blank still carries the stored value here. Re-sending it
        // makes AMD run DuplicatePatientCheck against a number it already holds,
        // and if any other chart shares it the whole updatepatient call is
        // rejected ("Duplicate SSN found.") — losing the name, address and phone
        // updates in the same request, not just the SSN.
        $normalizedSsn    = $this->normalizeSsn((string) ($data['ssn'] ?? ''));
        $normalizedOldSsn = $this->normalizeSsn((string) ($oldData['ssn'] ?? ''));
        if ($normalizedSsn !== '' && $normalizedSsn !== $normalizedOldSsn) {
            $payload['@ssn'] = $normalizedSsn;
        }

        $sexValue = $this->normalizeSexForAmd((string) ($data['sex'] ?? ''));
        if ($sexValue !== '') {
            // AMD expects a compact gender value (M/F/U), not full words.
            $payload['@sex'] = $sexValue;
        }

        $payload['address'] = [
            '@address2' => (string) ($data['address1'] ?? ''),
            '@address1' => (string) ($data['address2'] ?? ''),
            '@city' => strtoupper((string) ($data['city'] ?? '')),
            '@state' => $this->normalizeUsState((string) ($data['state'] ?? '')),
            '@zip' => (string) ($data['zip'] ?? ''),
        ];

        $payload['contactinfo'] = [
            '@homephone' => (string) ($data['home_ph'] ?? ''),
            '@officephone' => (string) ($data['work_ph'] ?? ''),
            '@officeext' => substr((string) ($data['work_ext'] ?? ''), 0, 4),
            '@otherphone' => (string) ($data['cell_no'] ?? ''),
            '@email' => (string) ($data['email'] ?? ''),
        ];

        if (!empty($data['marital_status'])) {
            $maritalMap = [
                'single' => 1,
                'married' => 2,
                'divorced' => 3,
                'separated' => 4,
                'widowed' => 5,
            ];
            $maritalKey = strtolower(trim((string) $data['marital_status']));
            if (isset($maritalMap[$maritalKey])) {
                $payload['@maritalstatus'] = $maritalMap[$maritalKey];
            }
        }

        return $payload;
    }

    protected function normalizeSexForAmd(string $sex): string
    {
        $value = strtoupper(trim($sex));
        if ($value === '') {
            return '';
        }

        if (in_array($value, ['M', 'MALE'], true)) {
            return 'M';
        }

        if (in_array($value, ['F', 'FEMALE'], true)) {
            return 'F';
        }

        if (in_array($value, ['U', 'UNKNOWN', 'OTHER'], true)) {
            return 'U';
        }

        return '';
    }

    protected function normalizeSsn(string $ssn): string
    {
        $digits = preg_replace('/\D+/', '', $ssn) ?? '';
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) > 9) {
            $digits = substr($digits, 0, 9);
        }

        return $digits;
    }

    protected function normalizeUsState(string $state): string
    {
        $raw = strtoupper(trim($state));
        if ($raw === '') {
            return '';
        }

        $map = [
            'ALABAMA' => 'AL', 'ALASKA' => 'AK', 'ARIZONA' => 'AZ', 'ARKANSAS' => 'AR',
            'CALIFORNIA' => 'CA', 'COLORADO' => 'CO', 'CONNECTICUT' => 'CT', 'DELAWARE' => 'DE',
            'FLORIDA' => 'FL', 'GEORGIA' => 'GA', 'HAWAII' => 'HI', 'IDAHO' => 'ID',
            'ILLINOIS' => 'IL', 'INDIANA' => 'IN', 'IOWA' => 'IA', 'KANSAS' => 'KS',
            'KENTUCKY' => 'KY', 'LOUISIANA' => 'LA', 'MAINE' => 'ME', 'MARYLAND' => 'MD',
            'MASSACHUSETTS' => 'MA', 'MICHIGAN' => 'MI', 'MINNESOTA' => 'MN', 'MISSISSIPPI' => 'MS',
            'MISSOURI' => 'MO', 'MONTANA' => 'MT', 'NEBRASKA' => 'NE', 'NEVADA' => 'NV',
            'NEW HAMPSHIRE' => 'NH', 'NEW JERSEY' => 'NJ', 'NEW MEXICO' => 'NM', 'NEW YORK' => 'NY',
            'NORTH CAROLINA' => 'NC', 'NORTH DAKOTA' => 'ND', 'OHIO' => 'OH', 'OKLAHOMA' => 'OK',
            'OREGON' => 'OR', 'PENNSYLVANIA' => 'PA', 'RHODE ISLAND' => 'RI', 'SOUTH CAROLINA' => 'SC',
            'SOUTH DAKOTA' => 'SD', 'TENNESSEE' => 'TN', 'TEXAS' => 'TX', 'UTAH' => 'UT',
            'VERMONT' => 'VT', 'VIRGINIA' => 'VA', 'WASHINGTON' => 'WA', 'WEST VIRGINIA' => 'WV',
            'WISCONSIN' => 'WI', 'WYOMING' => 'WY', 'DISTRICT OF COLUMBIA' => 'DC',
        ];

        if (isset($map[$raw])) {
            return $map[$raw];
        }

        $lettersOnly = preg_replace('/[^A-Z]/', '', $raw) ?? '';
        if (strlen($lettersOnly) >= 2) {
            return substr($lettersOnly, 0, 2);
        }

        return $raw;
    }

    protected function extractAmdFault(string $xml): ?array
    {
        if ($xml === '' || stripos($xml, '<Fault>') === false) {
            return null;
        }

        $get = static function (string $pattern) use ($xml): ?string {
            if (preg_match($pattern, $xml, $m)) {
                return trim((string) $m[1]);
            }

            return null;
        };

        return [
            'faultcode' => $get('/<faultcode>(.*?)<\/faultcode>/is'),
            'faultstring' => $get('/<faultstring>(.*?)<\/faultstring>/is'),
            'code' => $get('/<code>(.*?)<\/code>/is'),
            'description' => $get('/<description>(.*?)<\/description>/is'),
            'class' => $get('/<class>(.*?)<\/class>/is'),
            'method' => $get('/<method>(.*?)<\/method>/is'),
        ];
    }
}
