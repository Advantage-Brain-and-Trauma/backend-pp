<?php
namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\Funnel;
use App\Models\FormSubmission;
use Illuminate\Http\Request;

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
        $allFunnelDrafts = FormSubmission::whereNotNull('funnel_id')->where('status', 'draft')->count();
        $activeFunnels   = Funnel::where('status', 'active')->count();

        $summary = [
            'total_funnels'     => Funnel::count(),
            'active_funnels'    => $activeFunnels,
            'total_submissions' => $allFunnelSubs,
            'in_progress'       => $allFunnelDrafts,
            'completed'         => $allFunnelSubs - $allFunnelDrafts,
            'period_submissions'=> FormSubmission::whereNotNull('funnel_id')
                                    ->whereBetween('created_at', [$fromDate, $toDate])->count(),
            'completion_rate'   => $allFunnelSubs > 0
                                    ? round((($allFunnelSubs - $allFunnelDrafts) / $allFunnelSubs) * 100)
                                    : 0,
        ];

        $search = $request->input('search', '');
        $funnels = Funnel::withCount('submissions')
            ->when($search, fn($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('submissions_count', 'desc')->paginate(5)->withQueryString();

        foreach ($funnels as $funnel) {
            $formIds = is_array($funnel->form_ids) ? $funnel->form_ids
                     : (json_decode($funnel->form_ids ?? '[]', true) ?: []);
            $funnel->form_count = count($formIds);

            // All-time stats
            $submissions  = FormSubmission::where('funnel_id', $funnel->id)->get();
            $completed    = $submissions->where('status', '!=', 'draft')->count();
            $inProgress   = $submissions->where('status', 'draft')->count();
            $total        = $submissions->count();

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
                'total'        => $total,
                'completed'    => $completed,
                'in_progress'  => $inProgress,
                'period_subs'  => $periodSubs,
                'rate'         => $total > 0 ? round(($completed / $total) * 100) : 0,
                'daily_trend'  => $dailyTrend,
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

        // All-time summary
        $allSubs   = FormSubmission::count();
        $allDrafts = FormSubmission::where('status', 'draft')->count();
        $allCompleted = $allSubs - $allDrafts;

        $summary = [
            'total_forms'         => Form::count(),
            'active_forms'        => Form::where('is_active', 1)->count(),
            'total_submissions'   => $allSubs,
            'total_completed'     => $allCompleted,
            'total_drafts'        => $allDrafts,
            'period_submissions'  => FormSubmission::whereBetween('created_at', [$fromDate, $toDate])->count(),
            'avg_completion_rate' => $allSubs > 0
                ? round(($allCompleted / $allSubs) * 100)
                : 0,
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

            // All-time submission stats
            $submissions  = FormSubmission::where('form_id', $form->id)->get();
            $completed    = $submissions->where('status', '!=', 'draft')->count();
            $drafts       = $submissions->where('status', 'draft')->count();
            $total        = $submissions->count();

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

            $form->stats = [
                'total_submissions' => $total,
                'completed'         => $completed,
                'drafts'            => $drafts,
                'period_subs'       => $periodSubs,
                'rate'              => $total > 0 ? round(($completed / $total) * 100) : 0,
                'daily_trend'       => $dailyTrend,
            ];

            $form->recentSubmissions = FormSubmission::where('form_id', $form->id)
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
        $periodSubs     = FormSubmission::whereBetween('created_at', [$fromDate, $toDate]);
        $periodTotal    = (clone $periodSubs)->count();
        $periodDrafts   = (clone $periodSubs)->where('status', 'draft')->count();
        $periodCompleted = $periodTotal - $periodDrafts;   // anything that is NOT draft

        // Previous period submissions (for trend arrow)
        $prevTotal = FormSubmission::whereBetween('created_at', [$prevFrom, $prevTo])->count();
        $periodChange = $periodTotal - $prevTotal;

        // All-time totals for the status breakdown card
        $allSubs      = FormSubmission::count();
        $allDrafts    = FormSubmission::where('status', 'draft')->count();
        $allCompleted = $allSubs - $allDrafts;

        // Completion rate for selected period
        $completionRate = $periodTotal > 0
            ? round(($periodCompleted / $periodTotal) * 100)
            : ($allSubs > 0 ? round(($allCompleted / $allSubs) * 100) : 0);

        // ── Submission status breakdown (period-filtered) ───────────────────────
        $submissionsByStatus = [
            'total'     => $periodTotal > 0 ? $periodTotal : $allSubs,
            'completed' => $periodTotal > 0 ? $periodCompleted : $allCompleted,
            'drafts'    => $periodTotal > 0 ? $periodDrafts   : $allDrafts,
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
