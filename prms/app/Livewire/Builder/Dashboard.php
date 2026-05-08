<?php

namespace App\Livewire\Builder;

use Livewire\Component;
use App\Models\Record;
use App\Models\Module;
use App\Models\RecordHistory;
use App\Models\WorkflowStage;
use Livewire\Attributes\Layout;

class Dashboard extends Component
{
    public bool $showPastTrc = false;

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function togglePastTrc(): void
    {
        $this->showPastTrc = !$this->showPastTrc;
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $user = auth()->user();

        // Total records excluding Draft and Archived
        $totalRecords = Record::whereNotIn('status', ['Draft', 'Archived'])->count();

        // Status sub-counts
        $statusCounts = Record::whereNotIn('status', ['Draft', 'Archived'])
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $completedCount   = $statusCounts->get('Completed', 0);
        $underReviewCount = $statusCounts->get('Under Review', 0);
        $submittedCount   = $statusCounts->get('Submitted', 0);
        $returnedCount    = $statusCounts->get('Returned', 0);

        // Chart: status breakdown (donut)
        $statusChartData = [
            'labels' => ['Completed', 'Under Review', 'Submitted', 'Returned'],
            'data'   => [$completedCount, $underReviewCount, $submittedCount, $returnedCount],
            'colors' => ['#22c55e', '#a855f7', '#eab308', '#f97316'],
        ];

        // Chart: records created over last 30 days — single grouped query
        $trendRaw = Record::where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw("DATE(created_at) as date, COUNT(*) as count")
            ->groupBy('date')
            ->pluck('count', 'date');

        $trendData = collect();
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $trendData->push([
                'date'  => $day->format('M d'),
                'count' => $trendRaw->get($day->format('Y-m-d'), 0),
            ]);
        }
        $trendChartData = [
            'labels' => $trendData->pluck('date')->toArray(),
            'data'   => $trendData->pluck('count')->toArray(),
        ];

        // Per-module stats — single aggregated query instead of 3N queries
        $modules = Module::whereNull('source_module_id')->orderBy('sort_order')->get();
        $moduleIds = $modules->pluck('id');
        $statRows = Record::whereIn('module_id', $moduleIds)
            ->selectRaw("module_id,
                SUM(CASE WHEN status NOT IN ('Draft','Archived') THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN current_stage_id IS NOT NULL THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as approved")
            ->groupBy('module_id')
            ->get()
            ->keyBy('module_id');

        $moduleStats = $modules->map(fn($m) => [
            'name'     => $m->name,
            'slug'     => $m->slug,
            'total'    => $statRows->get($m->id)?->total ?? 0,
            'pending'  => $statRows->get($m->id)?->pending ?? 0,
            'approved' => $statRows->get($m->id)?->approved ?? 0,
        ]);

        // TRC Schedule — records with date_scheduled set (filled after TRC Scheduling stage submission)
        $trcSchedule = Record::whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.date_scheduled')) IS NOT NULL")
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.date_scheduled')) != ''")
            ->with(['module'])
            ->get()
            ->when(!$this->showPastTrc, fn($c) => $c->filter(fn($r) => !now()->startOfDay()->gt(\Carbon\Carbon::parse($r->data['date_scheduled']))))
            ->sortBy(fn($r) => $r->data['date_scheduled'])
            ->values();

        // Recent activity (last 10 history entries)
        $recentActivity = RecordHistory::with(['user', 'record.module'])
            ->latest()
            ->limit(10)
            ->get();

        // Unread notifications
        $notifications = $user->unreadNotifications()->latest()->limit(5)->get();
        $unreadCount   = $user->unreadNotifications()->count();

        return view('livewire.builder.dashboard', compact(
            'totalRecords', 'completedCount', 'underReviewCount', 'submittedCount', 'returnedCount',
            'statusChartData', 'trendChartData', 'moduleStats',
            'trcSchedule', 'recentActivity', 'notifications', 'unreadCount'
        ));
    }
}
