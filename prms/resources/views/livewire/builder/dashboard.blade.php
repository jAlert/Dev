<x-slot name="header">
    <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
        @if(auth()->user()->hasRole('super admin'))
            <a href="{{ route('builder.modules.index') }}" wire:navigate class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">Manage Modules</a>
        @endif
    </div>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        {{-- KPI Row --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="bg-white rounded-lg shadow-sm p-4 border-t-4 border-blue-500">
                <p class="text-xs font-bold uppercase text-gray-500 mb-1">Total Active</p>
                <p class="text-4xl font-black text-blue-600">{{ $totalRecords }}</p>
                <p class="text-xs text-gray-400 mt-1">Excl. drafts & archived</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-4 border-t-4 border-yellow-500">
                <p class="text-xs font-bold uppercase text-gray-500 mb-1">Submitted</p>
                <p class="text-4xl font-black text-yellow-600">{{ $submittedCount }}</p>
            </div>   
            <div class="bg-white rounded-lg shadow-sm p-4 border-t-4 border-orange-500">
                <p class="text-xs font-bold uppercase text-gray-500 mb-1">Returned</p>
                <p class="text-4xl font-black text-orange-600">{{ $returnedCount }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-4 border-t-4 border-purple-500">
                <p class="text-xs font-bold uppercase text-gray-500 mb-1">Under Review</p>
                <p class="text-4xl font-black text-purple-600">{{ $underReviewCount }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-4 border-t-4 border-green-500">
                <p class="text-xs font-bold uppercase text-gray-500 mb-1">Completed</p>
                <p class="text-4xl font-black text-green-600">{{ $completedCount }}</p>
            </div>
        </div>

        {{-- Charts Row --}}
        <div class="grid grid-cols-1 {{ auth()->user()->hasRole('super admin') ? 'lg:grid-cols-2' : '' }} gap-6">

            {{-- Status Donut --}}
            <div class="bg-white rounded-lg shadow-sm p-5">
                <h3 class="text-sm font-bold text-gray-700 mb-4 uppercase tracking-wide">Status Breakdown</h3>
                <div class="flex justify-center">
                    <canvas id="statusChart" width="220" height="220"></canvas>
                </div>
            </div>

            {{-- 30-Day Trend (super admin only) --}}
            @if(auth()->user()->hasRole('super admin'))
            <div class="bg-white rounded-lg shadow-sm p-5">
                <h3 class="text-sm font-bold text-gray-700 mb-4 uppercase tracking-wide">30-Day Trend</h3>
                <canvas id="trendChart" height="220"></canvas>
            </div>
            @endif
        </div>

        {{-- Per-Module Stats Table (super admin only) --}}
        @if(auth()->user()->hasRole('super admin') && $moduleStats->isNotEmpty())
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Module Overview</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                        <tr>
                            <th class="px-5 py-3 text-left">Module</th>
                            <th class="px-5 py-3 text-right">Total</th>
                            <th class="px-5 py-3 text-right">Pending Approval</th>
                            <th class="px-5 py-3 text-right">Completed</th>
                            <th class="px-5 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($moduleStats as $ms)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-medium text-gray-800">{{ $ms['name'] }}</td>
                            <td class="px-5 py-3 text-right text-gray-600">{{ $ms['total'] }}</td>
                            <td class="px-5 py-3 text-right">
                                @if($ms['pending'] > 0)
                                    <span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 font-semibold text-xs">{{ $ms['pending'] }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <span class="text-green-600 font-semibold">{{ $ms['approved'] }}</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('dynamic.index', $ms['slug']) }}" wire:navigate class="text-xs text-indigo-600 hover:underline font-medium">View →</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Activity + Notifications --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-4">Recent Activity</h3>
                @if($recentActivity->isEmpty())
                    <p class="text-gray-400 italic text-sm">No activity yet.</p>
                @else
                    <ol class="relative border-l border-gray-200 space-y-4 ml-3">
                        @foreach($recentActivity as $h)
                        <li class="ml-4">
                            <div class="absolute w-2 h-2 bg-indigo-400 rounded-full mt-1.5 -left-1 border border-white"></div>
                            <p class="text-sm">
                                <span class="font-semibold text-gray-800">{{ $h->user?->name ?? 'System' }}</span>
                                <span class="text-gray-500"> {{ $h->action }}</span>
                                @if($h->record?->module)
                                    a <span class="font-medium">{{ $h->record->module->name }}</span> record
                                @endif
                            </p>
                            <p class="text-xs text-gray-400">{{ $h->created_at->diffForHumans() }}</p>
                        </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Recent Notifications</h3>
                    @if($unreadCount > 0)
                        <button wire:click="markAllRead" class="text-xs text-indigo-600 hover:underline font-medium">Mark all read</button>
                    @endif
                </div>
                @if($notifications->isEmpty())
                    <p class="text-gray-400 italic text-sm">No unread notifications.</p>
                @else
                    <div class="space-y-3">
                        @foreach($notifications as $notif)
                            <a href="{{ route('notifications.open', $notif->id) }}" class="flex gap-3 bg-indigo-50 rounded p-3 text-sm hover:bg-indigo-100 transition-colors">
                                <div class="w-2 h-2 bg-indigo-500 rounded-full mt-1.5 flex-shrink-0"></div>
                                <div>
                                    <p class="text-gray-800">{{ $notif->data['message'] ?? 'Notification' }}</p>
                                    <p class="text-xs text-gray-400 mt-1">{{ $notif->created_at->diffForHumans() }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
                @if($unreadCount > 5)
                    <a href="{{ route('builder.notifications') }}" wire:navigate class="mt-3 block text-center text-xs text-indigo-600 hover:underline font-medium">View all {{ $unreadCount }} notifications →</a>
                @endif
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
function initDashboardCharts() {
    ['statusChart', 'trendChart'].forEach(id => {
        const existing = Chart.getChart(id);
        if (existing) existing.destroy();
    });

    // Status Donut
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: @json($statusChartData['labels']),
                datasets: [{
                    data: @json($statusChartData['data']),
                    backgroundColor: @json($statusChartData['colors']),
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: false,
                plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 10 } } },
                cutout: '65%',
            }
        });
    }

    // Trend Line
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: @json($trendChartData['labels']),
                datasets: [{
                    label: 'New Records',
                    data: @json($trendChartData['data']),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.08)',
                    tension: 0.3,
                    fill: true,
                    pointRadius: 2,
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
                    x: {
                        ticks: {
                            font: { size: 9 },
                            maxTicksLimit: 8,
                            maxRotation: 0,
                        },
                        grid: { display: false }
                    },
                }
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', initDashboardCharts);
document.addEventListener('livewire:navigated', initDashboardCharts);
</script>
@endpush
