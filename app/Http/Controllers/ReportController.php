<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /**
     * Show retention report dashboard
     */
    public function retentionDashboard(Request $request)
    {
        $period = (int) $request->get('period', 30);
        $today = today();
        $user = Auth::user();

        $baseQuery = Archive::query();

        // Get archives approaching active transition (Aktif -> Inaktif)
        $approachingInactive = (clone $baseQuery)->aktif()
            ->whereBetween('transition_active_due', [$today, $today->copy()->addDays($period)])
            ->with(['category', 'classification'])
            ->orderBy('transition_active_due')
            ->get();

        // Get archives approaching final transition (Inaktif -> Musnah)
        $approachingFinal = (clone $baseQuery)->inaktif()
            ->whereBetween('transition_inactive_due', [$today, $today->copy()->addDays($period)])
            ->with(['category', 'classification'])
            ->orderBy('transition_inactive_due')
            ->get();

        // Summary statistics
        $stats = [
            'total_archives' => (clone $baseQuery)->count(),
            'aktif' => (clone $baseQuery)->aktif()->count(),
            'inaktif' => (clone $baseQuery)->inaktif()->count(),
            'musnah' => (clone $baseQuery)->musnah()->count(),
            'approaching_inactive' => $approachingInactive->count(),
            'approaching_final' => $approachingFinal->count(),
        ];

        // Monthly transition trends (last 12 months)
        $monthlyTrends = DB::table('archives')
            ->select(
                DB::raw('EXTRACT(YEAR FROM created_at) as year'),
                DB::raw('EXTRACT(MONTH FROM created_at) as month'),
                DB::raw('COUNT(*) as total'),
                'status'
            )
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('year', 'month', 'status')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        // Archives by category for pie chart
        $archivesByCategory = Archive::select('categories.nama_kategori', DB::raw('COUNT(*) as count'))
            ->join('categories', 'archives.category_id', '=', 'categories.id')
            ->groupBy('categories.id', 'categories.nama_kategori')
            ->orderBy('count', 'desc')
            ->get();

        $viewPath = ($user && $user->role_type === 'admin')
            ? 'admin.reports.retention-dashboard'
            : 'intern.reports.retention-dashboard';

        return view($viewPath, compact(
            'approachingInactive',
            'approachingFinal',
            'stats',
            'period',
            'monthlyTrends',
            'archivesByCategory'
        ));
    }

    /**
     * Get retention alerts via AJAX
     */
    public function retentionAlerts(Request $request)
    {
        $period = (int) $request->get('period', 30);
        $type = $request->get('type', 'all');
        $today = today();

        $alerts = collect();

        if ($type === 'all' || $type === 'inactive') {
            $inactiveAlerts = Archive::aktif()
                ->whereBetween('transition_active_due', [$today, $today->copy()->addDays($period)])
                ->with(['category', 'classification'])
                ->get()
                ->map(function ($archive) use ($today) {
                    return [
                        'id' => $archive->id,
                        'type' => 'Transisi ke Inaktif',
                        'index_number' => $archive->index_number,
                        'uraian' => $archive->description,
                        'category' => $archive->category->nama_kategori,
                        'current_status' => 'Aktif',
                        'next_status' => 'Inaktif',
                        'due_date' => $archive->transition_active_due,
                        'days_remaining' => $today->diffInDays($archive->transition_active_due, false),
                        'priority' => $this->getPriority($today->diffInDays($archive->transition_active_due, false))
                    ];
                });

            $alerts = $alerts->merge($inactiveAlerts);
        }

        if ($type === 'all' || $type === 'final') {
            $finalAlerts = Archive::inaktif()
                ->whereBetween('transition_inactive_due', [$today, $today->copy()->addDays($period)])
                ->with(['category', 'classification'])
                ->get()
                ->map(function ($archive) use ($today) {
                    $finalStatus = str_starts_with($archive->category->nasib_akhir ?? '', 'Musnah') ? 'Musnah' : 'Musnah';

                    return [
                        'id' => $archive->id,
                        'type' => 'Transisi ke Musnah',
                        'index_number' => $archive->index_number,
                        'uraian' => $archive->description,
                        'category' => $archive->category->nama_kategori,
                        'current_status' => 'Inaktif',
                        'next_status' => $finalStatus,
                        'due_date' => $archive->transition_inactive_due,
                        'days_remaining' => $today->diffInDays($archive->transition_inactive_due, false),
                        'priority' => $this->getPriority($today->diffInDays($archive->transition_inactive_due, false)),
                        'nasib_akhir' => $archive->classification->nasib_akhir ?? $archive->category->nasib_akhir ?? 'N/A',
                    ];
                });

            $alerts = $alerts->merge($finalAlerts);
        }

        $alerts = $alerts->sortBy('days_remaining');

        return response()->json($alerts->values());
    }

    /**
     * Export retention report to Excel
     */
    public function exportRetentionReport(Request $request)
    {
        $alerts = $this->retentionAlerts($request)->getData();
        $period = (int) $request->get('period', 30);
        $fileName = 'laporan-retensi-' . $period . 'hari-' . date('Y-m-d') . '.json';

        return response()->json($alerts)
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }

    /**
     * Get priority level based on days remaining
     */
    private function getPriority($daysRemaining): string
    {
        if ($daysRemaining <= 7) {
            return 'critical';
        } elseif ($daysRemaining <= 30) {
            return 'high';
        } elseif ($daysRemaining <= 60) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get retention summary for dashboard widgets
     */
    public function retentionSummary()
    {
        $today = today();

        $summary = [
            'overdue' => Archive::where('transition_active_due', '<', $today)
                ->where('status', 'Aktif')
                ->count() +
                Archive::where('transition_inactive_due', '<', $today)
                ->where('status', 'Inaktif')
                ->count(),

            'due_this_week' => Archive::whereBetween('transition_active_due', [$today, $today->copy()->addDays(7)])
                ->where('status', 'Aktif')
                ->count() +
                Archive::whereBetween('transition_inactive_due', [$today, $today->copy()->addDays(7)])
                ->where('status', 'Inaktif')
                ->count(),

            'due_this_month' => Archive::whereBetween('transition_active_due', [$today, $today->copy()->addDays(30)])
                ->where('status', 'Aktif')
                ->count() +
                Archive::whereBetween('transition_inactive_due', [$today, $today->copy()->addDays(30)])
                ->where('status', 'Inaktif')
                ->count(),

            'manual_overrides' => Archive::where('manual_status_override', true)->count(),
        ];

        return response()->json($summary);
    }
}
