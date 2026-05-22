<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        Log::channel('admin_billing')->info('Billing index requested');
        $billingRecords = $this->fetchBillingRecords($request);
        $stats = $this->computeStats($billingRecords);
        return view('billing.index', compact('billingRecords', 'stats'));
    }

    public function show($id)
    {
        Log::channel('admin_billing')->info('Billing record requested', ['record_id' => $id]);
        $record = $this->fetchRecord($id);
        return view('billing.show', compact('record'));
    }

    private function computeStats(array $records): array
    {
        $outstanding = array_sum(array_map(fn($r) => $r['status'] !== 'paid' ? $r['patient_responsibility'] : 0, $records));
        $paidThisMonth = array_sum(array_map(fn($r) => $r['status'] === 'paid' ? $r['patient_responsibility'] : 0, $records));
        $pendingClaims = count(array_filter($records, fn($r) => $r['status'] === 'due'));
        $insurancePending = count(array_filter($records, fn($r) => $r['insurance_pending'] > 0));
        return [
            'total_outstanding' => $outstanding,
            'pending_claims'    => $pendingClaims,
            'paid_this_month'   => $paidThisMonth,
            'insurance_pending' => $insurancePending,
        ];
    }

    private function fetchRecord($id): array
    {
        foreach ($this->getMockBillingRecords() as $r) {
            if ($r['id'] == $id) return $r;
        }
        return ['id' => $id, 'patient_name' => 'Unknown', 'statement_id' => '#'.$id, 'date' => now()->format('Y-m-d'), 'patient_responsibility' => 0, 'insurance_pending' => 0, 'due_date' => null, 'status' => 'unknown', 'insurance_provider' => null, 'insurance_plan' => null, 'member_id' => null];
    }

    private function fetchBillingRecords(Request $request): array
    {
        $apiUrl = config('services.billing.url');
        $apiKey = config('services.billing.key');

        if (!$apiUrl) {
            return $this->getMockBillingRecords();
        }

        try {
            $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])
                ->get($apiUrl . '/billing', $request->only(['patient_id', 'date_from', 'date_to']));

            return $response->successful() ? $response->json('data', []) : $this->getMockBillingRecords();
        } catch (\Exception $e) {
            return $this->getMockBillingRecords();
        }
    }

    private function getMockBillingRecords(): array
    {
        return [
            ['id' => 1, 'patient_name' => 'Sarah Jenkins', 'statement_id' => '#4921', 'date' => '2024-10-01', 'patient_responsibility' => 45.00, 'insurance_pending' => 120.00, 'due_date' => '2024-10-30', 'status' => 'due', 'insurance_provider' => 'BlueCross BlueShield', 'insurance_plan' => 'PPO Gold Plan', 'member_id' => 'XYZ123456789'],
            ['id' => 2, 'patient_name' => 'Michael Johnson', 'statement_id' => '#4890', 'date' => '2024-09-01', 'patient_responsibility' => 25.00, 'insurance_pending' => 0, 'due_date' => '2024-09-30', 'status' => 'paid', 'insurance_provider' => 'Aetna', 'insurance_plan' => 'HMO Silver', 'member_id' => 'AET987654321'],
            ['id' => 3, 'patient_name' => 'Emily Davis', 'statement_id' => '#4855', 'date' => '2024-08-01', 'patient_responsibility' => 0.00, 'insurance_pending' => 0, 'due_date' => '2024-08-30', 'status' => 'paid', 'insurance_provider' => 'United Health', 'insurance_plan' => 'PPO Bronze', 'member_id' => 'UH112233445'],
            ['id' => 4, 'patient_name' => 'Robert Wilson', 'statement_id' => '#4930', 'date' => '2024-10-05', 'patient_responsibility' => 150.00, 'insurance_pending' => 80.00, 'due_date' => '2024-11-05', 'status' => 'due', 'insurance_provider' => 'Cigna', 'insurance_plan' => 'PPO Gold', 'member_id' => 'CIG556677889'],
        ];
    }
}



