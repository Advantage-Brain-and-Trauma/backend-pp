<?php
namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\Funnel;
use App\Models\FormSubmission;
use App\Models\UserFunnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $from     = $request->input('from', now()->subDays(30)->format('Y-m-d'));
        $to       = $request->input('to',   now()->format('Y-m-d'));
        $fromDate = \Carbon\Carbon::parse($from)->startOfDay();
        $toDate   = \Carbon\Carbon::parse($to)->endOfDay();

        // All-time summary stats
        $allFunnelSubs   = FormSubmission::whereNotNull('funnel_id')->count();
        $activeFunnels   = Funnel::where('status', 'active')->count();

        // In-progress = users assigned to any funnel who submitted at least 1 form but not all forms
        $allFunnels = Funnel::all(['id', 'form_ids']);
        $totalInProgress = 0;
        $totalCompleted  = 0;
        foreach ($allFunnels as $f) {
            $fIds = is_array($f->form_ids) ? $f->form_ids : (json_decode($f->form_ids ?? '[]', true) ?: []);
            $totalForms = count($fIds);
            if ($totalForms === 0) continue;
            $assignedUserIds = UserFunnel::where('funnel_id', $f->id)->whereNotNull('user_id')->pluck('user_id');
            foreach ($assignedUserIds as $uid) {
                $submitted = FormSubmission::where('funnel_id', $f->id)->where('user_id', $uid)->distinct('form_id')->count('form_id');
                if ($submitted >= $totalForms) $totalCompleted++;
                elseif ($submitted > 0)        $totalInProgress++;
            }
        }

        $summary = [
            'total_funnels'     => Funnel::count(),
            'active_funnels'    => $activeFunnels,
            'total_submissions' => $allFunnelSubs,
            'in_progress'       => $totalInProgress,
            'completed'         => $totalCompleted,
            'period_submissions'=> FormSubmission::whereNotNull('funnel_id')
                                    ->whereBetween('created_at', [$fromDate, $toDate])->count(),
            'completion_rate'   => ($totalCompleted + $totalInProgress) > 0
                                    ? round(($totalCompleted / ($totalCompleted + $totalInProgress)) * 100)
                                    : ($allFunnelSubs > 0 ? 100 : 0),
        ];

        $search = $request->input('search', '');
        $funnels = Funnel::withCount('submissions')
            ->when($search, fn($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('submissions_count', 'desc')->paginate(5)->withQueryString();

        foreach ($funnels as $funnel) {
            $formIds = is_array($funnel->form_ids) ? $funnel->form_ids
                     : (json_decode($funnel->form_ids ?? '[]', true) ?: []);
            $funnel->form_count = count($formIds);

            // Per-funnel patient assignment stats
            $submissions  = FormSubmission::with('user')->where('funnel_id', $funnel->id)->get();
            $assignedUserIds = UserFunnel::where('funnel_id', $funnel->id)->whereNotNull('user_id')->pluck('user_id')->unique();
            $totalPatientAssign = $assignedUserIds->count();
            $totalCompleted = 0;
            foreach ($assignedUserIds as $uid) {
                $submitted = FormSubmission::where('funnel_id', $funnel->id)->where('user_id', $uid)->distinct('form_id')->count('form_id');
                if ($submitted >= $funnel->form_count && $funnel->form_count > 0) $totalCompleted++;
            }
            $totalPending = max(0, $totalPatientAssign - $totalCompleted);

            // Fallback: if no user assignments, use submission count
            if ($assignedUserIds->isEmpty()) {
                $totalPatientAssign = $submissions->count();
                $totalCompleted     = $totalPatientAssign;
                $totalPending       = 0;
            }

            // Period stats
            $periodSubs   = FormSubmission::where('funnel_id', $funnel->id)
                            ->whereBetween('created_at', [$fromDate, $toDate])->count();

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

        return view('analytics.funnels', compact('funnels', 'summary', 'from', 'to', 'search'));
    }

    /**
     * Form Analytics — per-form submission stats
     */
    public function forms(Request $request)
    {
        $from     = $request->input('from', now()->subDays(30)->format('Y-m-d'));
        $to       = $request->input('to',   now()->format('Y-m-d'));
        $fromDate = \Carbon\Carbon::parse($from)->startOfDay();
        $toDate   = \Carbon\Carbon::parse($to)->endOfDay();

        // Build a map: form_id => [funnel_ids that contain this form]
        $allFunnels = Funnel::all(['id', 'form_ids']);
        $formFunnelMap = []; // form_id => [funnel_id, ...]
        foreach ($allFunnels as $f) {
            $fIds = is_array($f->form_ids) ? $f->form_ids : (json_decode($f->form_ids ?? '[]', true) ?: []);
            foreach ($fIds as $fid) {
                $formFunnelMap[$fid][] = $f->id;
            }
        }

        // ── Summary stats — all date-range filtered ──────────────────────────────

        // Total Forms & Active Forms within date range
        $totalForms  = Form::whereBetween('created_at', [$fromDate, $toDate])->count();
        $activeForms = Form::where('is_active', 1)->whereBetween('created_at', [$fromDate, $toDate])->count();

        // Total Patient Assign: distinct patients (patient_id or user_id) assigned within date range
        // Use COALESCE(patient_id, user_id) to avoid counting same patient twice
        $assignedRows = DB::table('user_funnels')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->select('id', 'user_id', 'funnel_id', 'patient_id')
            ->get();

        // Distinct patients (prefer patient_id, fallback to user_id)
        $distinctPatients = $assignedRows->map(fn($r) => $r->patient_id ?? $r->user_id)
            ->filter()->unique()->count();
        $totalPatientAssign = $distinctPatients;

        // Total Completed & Total Pending:
        // For each (user_funnel) assignment, check if patient submitted ALL forms in that funnel.
        // Total Completed = count of assignments where submitted_forms >= total_forms_in_funnel
        // Total Pending   = total assignments - Total Completed
        $totalAssignments = $assignedRows->count();
        $completedCount   = 0;

        foreach ($assignedRows as $uf) {
            // Get form count for this funnel
            $funnel = $allFunnels->firstWhere('id', $uf->funnel_id);
            if (!$funnel) continue;
            $fIds = is_array($funnel->form_ids)
                ? $funnel->form_ids
                : (json_decode($funnel->form_ids ?? '[]', true) ?: []);
            $totalFormsInFunnel = count($fIds);
            if ($totalFormsInFunnel === 0) continue;

            // Count distinct forms submitted by this patient/user for this funnel
            $subQuery = DB::table('form_submissions')
                ->where('funnel_id', $uf->funnel_id)
                ->whereNull('deleted_at');
            if ($uf->user_id) {
                $subQuery->where('user_id', $uf->user_id);
            } elseif ($uf->patient_id) {
                // match by patient_id via users table if needed — fallback: no user_id means 0 subs
                $subQuery->whereNull('user_id'); // won't match any real submission
            }
            $submittedForms = $subQuery->distinct('form_id')->count('form_id');

            if ($submittedForms >= $totalFormsInFunnel) {
                $completedCount++;
            }
        }

        $totalCompleted = $completedCount;
        $totalPending   = max(0, $totalAssignments - $totalCompleted);

        // Completion Rate = Total Completed / Total Assignments * 100, capped at 100%
        $avgCompletionRate = $totalAssignments > 0
            ? min(100, round(($totalCompleted / $totalAssignments) * 100))
            : 0;

        $summary = [
            'total_forms'         => $totalForms,
            'active_forms'        => $activeForms,
            'total_completed'     => $totalCompleted,
            'total_pending'       => $totalPending,
            'total_patient_assign'=> $totalPatientAssign,
            'avg_completion_rate' => $avgCompletionRate,
        ];

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

            // Total Patient Assign: distinct users assigned to funnels that contain this form
            $funnelIdsForForm = $formFunnelMap[$form->id] ?? [];
            if (!empty($funnelIdsForForm)) {
                $totalPatientAssignForm = DB::table('user_funnels')
                    ->whereIn('funnel_id', $funnelIdsForForm)
                    ->whereNull('deleted_at')
                    ->distinct('user_id')
                    ->count('user_id');
            } else {
                // Form not in any funnel — count distinct users from form_submissions
                $totalPatientAssignForm = FormSubmission::where('form_id', $form->id)
                    ->whereNull('deleted_at')
                    ->distinct('user_id')->count('user_id');
            }

            // Total Completed: count of submissions with status 'completed' for this form
            $submissions  = FormSubmission::where('form_id', $form->id)->get();
            $completed    = $submissions->where('status', 'completed')->count();
            $total        = $submissions->count();

            // Total Pending = Total Patient Assign - Total Completed
            $pending = max(0, $totalPatientAssignForm - $completed);

            // Period submissions
            $periodSubs = FormSubmission::where('form_id', $form->id)
                ->whereBetween('created_at', [$fromDate, $toDate])->count();

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
                ->orderBy('created_at', 'desc')->take(5)->get();
        }

        return view('analytics.forms', compact('forms', 'summary', 'from', 'to', 'search'));
    }

    /**
     * Reports Overview — high-level summary
     */
    public function reports(Request $request)
    {
        // ── Date range ──────────────────────────────────────────────────────────
        $from = $request->input('from', now()->subDays(30)->format('Y-m-d'));
        $to   = $request->input('to',   now()->format('Y-m-d'));
        $fromDate = \Carbon\Carbon::parse($from)->startOfDay();
        $toDate   = \Carbon\Carbon::parse($to)->endOfDay();

        // Previous period of equal length (for % change comparison)
        $periodDays  = max(1, $fromDate->diffInDays($toDate));
        $prevFrom    = $fromDate->copy()->subDays($periodDays);
        $prevTo      = $fromDate->copy()->subSecond();

        // ── All-time totals (not date-filtered — shown in stat cards) ───────────
        $totalForms   = Form::count();
        $activeForms  = Form::where('is_active', 1)->count();
        $totalFunnels = Funnel::count();
        $activeFunnels = Funnel::where('status', 'active')->count();

        // ── Submissions within selected date range ──────────────────────────────
        $periodSubs      = FormSubmission::whereBetween('created_at', [$fromDate, $toDate]);
        $periodTotal     = (clone $periodSubs)->count();
        $periodCompleted = (clone $periodSubs)->where('status', 'completed')->count();

        // Previous period submissions (for trend arrow)
        $prevTotal = FormSubmission::whereBetween('created_at', [$prevFrom, $prevTo])->count();
        $periodChange = $periodTotal - $prevTotal;

        // All-time totals for the status breakdown card
        $allSubs      = FormSubmission::count();
        $allCompleted = FormSubmission::where('status', 'completed')->count();

        // Total Patient Assign (all-time) across all funnels via user_funnels
        $totalPatientAssign = DB::table('user_funnels')->whereNull('deleted_at')->count();
        $totalPending       = max(0, $totalPatientAssign - $allCompleted);

        // Completion rate: completed / total patient assign
        $completionRate = $totalPatientAssign > 0
            ? round(($allCompleted / $totalPatientAssign) * 100)
            : ($allSubs > 0 ? round(($allCompleted / $allSubs) * 100) : 0);

        // ── Submission status breakdown ─────────────────────────────────────────
        $submissionsByStatus = [
            'total'                => max($totalPatientAssign, $allSubs, 1),
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

        // Fall back to all-time if no period submissions exist
        if ($topForms->sum('submissions_count') === 0) {
            $topForms = Form::withCount('submissions')
                ->orderBy('submissions_count', 'desc')
                ->take(5)
                ->get();
        }

        // ── Top funnels — filtered by period ───────────────────────────────────
        $topFunnels = Funnel::withCount(['submissions' => function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('created_at', [$fromDate, $toDate]);
            }])
            ->orderBy('submissions_count', 'desc')
            ->take(5)
            ->get();

        if ($topFunnels->sum('submissions_count') === 0) {
            $topFunnels = Funnel::withCount('submissions')
                ->orderBy('submissions_count', 'desc')
                ->take(5)
                ->get();
        }

        // ── Recent submissions (last 10 in period) ──────────────────────────────
        $recentSubmissions = FormSubmission::with('form')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        if ($recentSubmissions->isEmpty()) {
            $recentSubmissions = FormSubmission::with('form')
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();
        }

        $stats = [
            'total_forms'           => $totalForms,
            'active_forms'          => $activeForms,
            'total_funnels'         => $totalFunnels,
            'active_funnels'        => $activeFunnels,
            'total_submissions'     => $allSubs,
            'period_submissions'    => $periodTotal,
            'period_change'         => $periodChange,
            'completion_rate'       => $completionRate,
            'submissions_by_status' => $submissionsByStatus,
            'daily_trend'           => $dailyTrend,
            'top_forms'             => $topForms,
            'top_funnels'           => $topFunnels,
            'recent_submissions'    => $recentSubmissions,
        ];

        return view('analytics.reports', compact('stats', 'from', 'to'));
    }
}
