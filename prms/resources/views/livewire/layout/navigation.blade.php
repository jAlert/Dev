<?php
use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component {
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }

    public function getUnreadCountProperty(): int
    {
        return auth()->user()?->unreadNotifications()->count() ?? 0;
    }
}; ?>

<div class="h-full">
    <style>
        .prms-nav {
            background-color: var(--sidebar-bg);
        }

        .prms-nav-header {
            background-color: var(--sidebar-header-bg);
            border-color: var(--sidebar-active-bg);
        }

        .prms-nav-divider {
            border-color: var(--sidebar-active-bg);
        }

        .prms-nav-item {
            color: rgba(209, 213, 219, 1);
        }

        .prms-nav-item:hover {
            background-color: var(--sidebar-active-bg);
            color: white;
        }

        .prms-nav-item.nav-active {
            background-color: var(--sidebar-active-bg);
            color: white;
            font-weight: 500;
        }

        .prms-nav-item.nav-active-border {
            border-left-color: var(--accent) !important;
        }

        .prms-nav-accent {
            color: var(--accent) !important;
            opacity: 0.9;
        }

        .prms-nav-badge {
            background-color: var(--accent);
        }
    </style>

    <nav class="prms-nav w-64 text-white flex-shrink-0 flex flex-col h-full shadow-lg z-20">
        <!-- Logo area -->
        <div class="prms-nav-header h-16 flex items-center px-6 border-b">
            <a href="{{ route('dashboard') }}" wire:navigate
                class="text-xl font-bold tracking-wider text-white flex items-center">
                <svg class="w-6 h-6 mr-2 prms-nav-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                    </path>
                </svg>
                PRMS
            </a>
        </div>

        <!-- Nav Links -->
        <div class="flex-1 overflow-y-auto py-4 flex flex-col gap-1 px-3">
            <!-- Dashboard -->
            <a href="{{ route('dashboard') }}" wire:navigate
                class="prms-nav-item flex items-center px-3 py-2.5 rounded-md transition-colors {{ request()->routeIs('dashboard') ? 'nav-active' : '' }}">
                <svg class="w-5 h-5 mr-3 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                    </path>
                </svg>
                Dashboard
            </a>

            <!-- Approval Queue (approvers only) -->
            @php
                $canSeeQueue = auth()->user()->hasRole('super admin')
                    || auth()->user()->permissions->contains(fn($p) => str_starts_with($p->name, 'approve-') || str_starts_with($p->name, 'review-'))
                    || \App\Models\WorkflowStage::whereIn('approver_role_id', auth()->user()->roles->pluck('id'))->exists();
            @endphp
            @if($canSeeQueue)
                <a href="{{ route('builder.approval.queue') }}" wire:navigate
                    class="prms-nav-item flex items-center px-3 py-2.5 rounded-md transition-colors {{ request()->routeIs('builder.approval.queue') ? 'nav-active' : '' }}">
                    <svg class="w-5 h-5 mr-3 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                    Approval Queue
                    @php
                        $userRoleIds = auth()->user()->roles->pluck('id');
                        $stageIds = \App\Models\WorkflowStage::whereIn('approver_role_id', $userRoleIds)->pluck('id');
                        $pending = auth()->user()->hasRole('super admin')
                            ? \App\Models\Record::whereNotNull('current_stage_id')->count()
                            : \App\Models\Record::whereIn('current_stage_id', $stageIds)->count();
                    @endphp
                    @if($pending > 0)
                        <span
                            class="prms-nav-badge ml-auto text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ $pending > 99 ? '99+' : $pending }}</span>
                    @endif
                </a>
            @endif

            <div class="my-3 border-t prms-nav-divider"></div>

            <div class="px-3 mb-2 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Modules</div>

            <!-- Modules List -->
            @foreach(\App\Models\Module::orderBy('sort_order')->get() as $navModule)
                @if(auth()->user()->can("view-{$navModule->slug}"))
                    @php $modActive = request()->is('app/' . $navModule->slug . '*'); @endphp
                    <a href="{{ route('dynamic.index', $navModule->slug) }}" wire:navigate
                        class="prms-nav-item flex items-center px-3 py-2.5 mb-1 rounded-md transition-colors border-l-2 {{ $modActive ? 'nav-active nav-active-border' : 'border-transparent' }}">
                        <svg class="w-5 h-5 mr-3 prms-nav-accent opacity-75" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                            </path>
                        </svg>
                        {{ $navModule->name }}
                    </a>
                @endif
            @endforeach

            @if(\App\Models\Module::count() === 0)
                <div class="px-3 py-2 text-sm text-gray-500 italic">No modules found</div>
            @endif

            @if(auth()->user() && auth()->user()->hasRole('super admin'))
                <div class="my-3 border-t prms-nav-divider"></div>
                <div class="px-3 mb-2 pt-1 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Administration
                </div>

                <a href="{{ route('builder.modules.index') }}" wire:navigate
                    class="prms-nav-item flex items-center px-3 py-2.5 mb-1 rounded-md transition-colors {{ request()->routeIs('builder.modules.*') ? 'nav-active' : '' }}">
                    <svg class="w-5 h-5 mr-3 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                        </path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Module Management
                </a>

                <a href="{{ route('admin.roles') }}" wire:navigate
                    class="prms-nav-item flex items-center px-3 py-2.5 mb-1 rounded-md transition-colors {{ request()->routeIs('admin.roles') ? 'nav-active' : '' }}">
                    <svg class="w-5 h-5 mr-3 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                        </path>
                    </svg>
                    Roles & Permissions
                </a>

                <a href="{{ route('admin.users') }}" wire:navigate
                    class="prms-nav-item flex items-center px-3 py-2.5 mb-1 rounded-md transition-colors {{ request()->routeIs('admin.users') ? 'nav-active' : '' }}">
                    <svg class="w-5 h-5 mr-3 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                        </path>
                    </svg>
                    User Management
                </a>

                <a href="{{ route('builder.audit') }}" wire:navigate
                    class="prms-nav-item flex items-center px-3 py-2.5 mb-1 rounded-md transition-colors {{ request()->routeIs('builder.audit') ? 'nav-active' : '' }}">
                    <svg class="w-5 h-5 mr-3 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12l2 2 4-4" />
                    </svg>
                    Audit Log
                </a>

                <a href="{{ route('builder.webhooks') }}" wire:navigate
                    class="prms-nav-item flex items-center px-3 py-2.5 mb-1 rounded-md transition-colors {{ request()->routeIs('builder.webhooks') ? 'nav-active' : '' }}">
                    <svg class="w-5 h-5 mr-3 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                    Webhooks
                </a>

                <a href="{{ route('builder.api.manager') }}" wire:navigate
                    class="prms-nav-item flex items-center px-3 py-2.5 mb-1 rounded-md transition-colors {{ request()->routeIs('builder.api.manager') ? 'nav-active' : '' }}">
                    <svg class="w-5 h-5 mr-3 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    API Manager
                </a>
            @endif
        </div>

    </nav>
</div>