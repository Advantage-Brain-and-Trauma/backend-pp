<?php

namespace App\Http\Middleware;

use App\Models\ProxyAccess;
use App\Models\ProxyAccessHistory;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LogProxyAccessMiddleware
{
    // Map route names / URI prefixes to human-readable action labels
    private const ROUTE_LABELS = [
        'clinical-note'              => ['action' => 'viewed "Lab Results"',    'resource_type' => 'clinical_note'],
        'get-patient-appointments'   => ['action' => 'viewed "Appointments"',   'resource_type' => 'appointment'],
        'get-patient-details'        => ['action' => 'viewed "Patient Details"','resource_type' => 'patient'],
        'get-patient-form-data'      => ['action' => 'viewed "Forms"',          'resource_type' => 'form'],
        'get-patient-funnels'        => ['action' => 'viewed "Funnels"',        'resource_type' => 'funnel'],
        'recent-activity'            => ['action' => 'viewed "Recent Activity"','resource_type' => 'activity'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = Auth::guard('api')->user();
        if (!$user) {
            return $response;
        }

        // Only log when the user is acting as a proxy (session has proxy_patient_user_id set)
        $patientUserId = session('proxy_patient_user_id');
        if (!$patientUserId) {
            return $response;
        }

        $proxyAccess = ProxyAccess::where('proxy_user_id', $user->id)
            ->where('patient_user_id', $patientUserId)
            ->where('status', 'active')
            ->first();

        if (!$proxyAccess) {
            return $response;
        }

        $uri    = ltrim($request->path(), 'api/');
        $label  = $this->resolveLabel($uri, $request);

        ProxyAccessHistory::create([
            'proxy_access_id' => $proxyAccess->id,
            'proxy_user_id'   => $user->id,
            'action'          => $label['action'],
            'resource_type'   => $label['resource_type'],
            'resource_id'     => $request->route('noteId') ?? $request->route('formId') ?? null,
            'ip_address'      => $request->ip(),
            'accessed_at'     => now(),
        ]);

        return $response;
    }

    private function resolveLabel(string $uri, Request $request): array
    {
        foreach (self::ROUTE_LABELS as $segment => $label) {
            if (str_contains($uri, $segment)) {
                return $label;
            }
        }

        return [
            'action'        => 'accessed "' . $uri . '"',
            'resource_type' => 'unknown',
        ];
    }
}
