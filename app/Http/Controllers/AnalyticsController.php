<?php
namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\Funnel;
use App\Models\FormSubmission;
use App\Models\UserFunnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Funnel Analytics — per-funnel stats with submission breakdown
     */
    public function funnels(Request $request)
    {
        Log::channel('admin_analytics')->info('Analytics funnels requested', $request->only(['from', 'to', 'search']));
        $from     = $request->input('from', now()->subDays(30)->format('Y-m-d'));
        $to       = $request->input('to',   now()->format('Y-m-d'));
        $fromDate = \Carbon\Carbon::parse($from)->startOfDay();
        $toDate   = \Carbon\Carbon::parse($to)->endOfDay();

        $search = $request->input('search', '');
        $funnels = Funnel::withCount('submissions')
            ->when($search, fn($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('submissions_count', 'desc')->paginate(5)->withQueryString();

        foreach ($funnels as $funnel) {
            $formIds = is_array($funnel->form_ids) ? $funnel->form_ids
                     : (json_decode($funnel->form_ids ?? '[]', true) ?: []);
            $funnel->form_count = count($formIds);

            // Per-funnel patient assignment stats — date-filtered
            $assignedUserIds = UserFunnel::where('funnel_id', $funnel->id)
                ->whereNotNull('user_id')
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->pluck('user_id')->unique();
            $totalPatientAssign = $assignedUserIds->count();
            $totalCompleted = 0;
            foreach ($assignedUserIds as $uid) {
                $submitted = FormSubmission::where('funnel_id', $funnel->id)
                    ->where('user_id', $uid)
                    ->whereBetween('created_at', [$fromDate, $toDate])
                    ->distinct('form_id')->count('form_id');
                if ($submitted >= $funnel->form_count && $funnel->form_count > 0) $totalCompleted++;
            }
            $totalPending = max(0, $totalPatientAssign - $totalCompleted);

            // Period submissions
            $submissions = FormSubmission::with('user')->where('funnel_id', $funnel->id)
                ->whereBetween('created_at', [$fromDate, $toDate])->get();
            $periodSubs  = $submissions->count();

            // Fallback: if no user assignments in period, use submission count
            if ($assignedUserIds->isEmpty()) {
                $totalPatientAssign = $periodSubs;
                $totalCompleted     = $submissions->where('status', 'completed')->count();
                $totalPending       = max(0, $totalPatientAssign - $totalCompleted);
            }

            // Daily trend for this funnel
            $dailyRaw = FormSubmission::where('funnel_id', $funnel->id)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->groupBy('date')->orderBy('date')
                ->pluck('count', 'date')->toArray();
            $dailyTrend = [];
            $cursor = $fromDate->copy();
            while ($cursor->lte($toDate)) {
                $key = $cursor->format('Y-m-d');
                $dailyTrend[$key] = $dailyRaw[$key] ?? 0;
                $cursor->addDay();
            }

            $funnel->stats = [
                'total_patient_assign' => $totalPatientAssign,
                'total_completed'      => $totalCompleted,
                'total_pending'        => $totalPending,
                'period_subs'          => $periodSubs,
                'rate'                 => $totalPatientAssign > 0 ? round(($totalCompleted / $totalPatientAssign) * 100) : 0,
                'daily_trend'          => $dailyTrend,
            ];
            $funnel->recentSubmissions = $submissions->sortByDesc('created_at')->take(5);
        }

        return view('analytics.funnels', compact('funnels', 'from', 'to', 'search'));
    }

    /**
     * Form Analytics — per-form submission stats
     */
    public function forms(Request $request)
    {
        Log::channel('admin_analytics')->info('Analytics forms requested', $request->only(['from', 'to', 'search']));
        $from     = $request->input('from', now()->subDays(30)->format('Y-m-d'));
        $to       = $request->input('to',   now()->format('Y-m-d'));
        $fromDate = \Carbon\Carbon::parse($from)->startOfDay();
        $toDate   = \Carbon\Carbon::parse($to)->endOfDay();

        // Build a map: form_id => [funnel_ids that contain this form]
        $allFunnels = Funnel::all();
        $formFunnelMap = []; // form_id => [funnel_id, ...]
        foreach ($allFunnels as $f) {
            $fIds = is_array($f->form_ids) ? $f->form_ids : (json_decode($f->form_ids ?? '[]', true) ?: []);
            foreach ($fIds as $fid) {
                $formFunnelMap[$fid][] = $f->id;
            }
        }

        $search = $request->input('search', '');
        $forms = Form::withCount('submissions')
            ->when($search, fn($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('submissions_count', 'desc')->paginate(5)->withQueryString();

        foreach ($forms as $form) {
            // Field count
            $fields = is_array($form->fields) ? $form->fields
                    : (json_decode($form->fields ?? '[]', true) ?: []);
            $rows = $fields['rows'] ?? (is_array($fields) ? $fields : []);
            $fieldCount = 0;
            foreach ($rows as $row) {
                foreach (($row['cols'] ?? []) as $col) {
                    $fieldCount += count($col['fields'] ?? []);
                }
            }
            $form->field_count = $fieldCount;

            // Total Patient Assign: distinct users assigned to funnels that contain this form (date-filtered)
            $funnelIdsForForm = $formFunnelMap[$form->id] ?? [];
            if (!empty($funnelIdsForForm)) {
                $totalPatientAssignForm = DB::table('user_funnels')
                    ->whereIn('funnel_id', $funnelIdsForForm)
                    ->whereNull('deleted_at')
                    ->whereBetween('created_at', [$fromDate, $toDate])
                    ->distinct('user_id')
                    ->count('user_id');
            } else {
                // Form not in any funnel — count distinct users from form_submissions in period
                $totalPatientAssignForm = FormSubmission::where('form_id', $form->id)
                    ->whereNull('deleted_at')
                    ->whereBetween('created_at', [$fromDate, $toDate])
                    ->distinct('user_id')->count('user_id');
            }

            // Total Completed: submissions with status 'completed' for this form in the date range
            $submissions  = FormSubmission::where('form_id', $form->id)
                ->whereBetween('created_at', [$fromDate, $toDate])->get();
            $completed    = $submissions->where('status', 'completed')->count();
            $total        = $submissions->count();

            // Total Pending = Total Patient Assign - Total Completed
            $pending = max(0, $totalPatientAssignForm - $completed);

            // Period submissions (same as $total now)
            $periodSubs = $total;

            // Daily trend for this form
            $dailyRaw = FormSubmission::where('form_id', $form->id)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->groupBy('date')->orderBy('date')
                ->pluck('count', 'date')->toArray();
            $dailyTrend = [];
            $cursor = $fromDate->copy();
            while ($cursor->lte($toDate)) {
                $key = $cursor->format('Y-m-d');
                $dailyTrend[$key] = $dailyRaw[$key] ?? 0;
                $cursor->addDay();
            }

            // Completion rate capped at 100%
            $formRate = $totalPatientAssignForm > 0
                ? min(100, round(($completed / $totalPatientAssignForm) * 100))
                : ($total > 0 ? min(100, round(($completed / $total) * 100)) : 0);

            $form->stats = [
                'total_patient_assign' => $totalPatientAssignForm,
                'total_submissions'    => $total,
                'completed'            => $completed,
                'pending'              => $pending,
                'period_subs'          => $periodSubs,
                'rate'                 => $formRate,
                'daily_trend'          => $dailyTrend,
            ];

            $form->recentSubmissions = FormSubmission::with('user')->where('form_id', $form->id)
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->orderBy('created_at', 'desc')->take(5)->get();
        }

        return view('analytics.forms', compact('forms', 'from', 'to', 'search'));
    }

    /**
     * Reports Overview — high-level summary
     */
    public function reports(Request $request)
    {
        Log::channel('admin_analytics')->info('Analytics reports requested', $request->only(['from', 'to']));
        // ── Date range ──────────────────────────────────────────────────────────
        $from = $request->input('from', now()->subDays(30)->format('Y-m-d'));
        $to   = $request->input('to',   now()->format('Y-m-d'));
        $fromDate = \Carbon\Carbon::parse($from)->startOfDay();
        $toDate   = \Carbon\Carbon::parse($to)->endOfDay();

        // ── Submission status breakdown — date-filtered ────────────────────────────
        $totalPatientAssign = DB::table('user_funnels')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->count();
        $allCompleted = FormSubmission::where('status', 'completed')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->count();
        $totalPending = max(0, $totalPatientAssign - $allCompleted);

        $submissionsByStatus = [
            'total'                => max($totalPatientAssign, 1),
            'total_patient_assign' => $totalPatientAssign,
            'completed'            => $allCompleted,
            'pending'              => $totalPending,
        ];

        // ── Daily submissions trend for the selected period ─────────────────────
        $dailyRaw = FormSubmission::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Fill every day in range with 0 if no submissions
        $dailyTrend = [];
        $cursor = $fromDate->copy();
        while ($cursor->lte($toDate)) {
            $key = $cursor->format('Y-m-d');
            $dailyTrend[$key] = $dailyRaw[$key] ?? 0;
            $cursor->addDay();
        }

        // ── Top forms — filtered by period ─────────────────────────────────────
        $topForms = Form::withCount(['submissions' => function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('created_at', [$fromDate, $toDate]);
            }])
            ->orderBy('submissions_count', 'desc')
            ->take(5)
            ->get();

        // ── Top funnels — filtered by period ───────────────────────────────────
        $topFunnels = Funnel::withCount(['submissions' => function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('created_at', [$fromDate, $toDate]);
            }])
            ->orderBy('submissions_count', 'desc')
            ->take(5)
            ->get();

        // ── Recent submissions (last 10 in period) ──────────────────────────────
        $recentSubmissions = FormSubmission::with('form')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        $stats = [
            'submissions_by_status' => $submissionsByStatus,
            'daily_trend'           => $dailyTrend,
            'top_forms'             => $topForms,
            'top_funnels'           => $topFunnels,
            'recent_submissions'    => $recentSubmissions,
        ];

        return view('analytics.reports', compact('stats', 'from', 'to'));
    }
}




