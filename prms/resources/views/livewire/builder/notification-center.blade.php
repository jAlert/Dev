<x-slot name="header">
    <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Notifications
            @if($unreadCount > 0)
                <span class="ml-2 px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-sm font-bold">{{ $unreadCount }} unread</span>
            @endif
        </h2>
        @if($unreadCount > 0)
            <button wire:click="markAllRead" class="text-sm text-indigo-600 hover:text-indigo-900 font-medium border border-indigo-300 rounded px-3 py-1.5 hover:bg-indigo-50 transition">
                Mark all read
            </button>
        @endif
    </div>
</x-slot>

<div class="py-8">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

        {{-- Filter tabs --}}
        <div class="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit">
            <button wire:click="$set('filter', 'unread')"
                class="px-4 py-1.5 rounded-md text-sm font-medium transition {{ $filter === 'unread' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700' }}">
                Unread
            </button>
            <button wire:click="$set('filter', 'all')"
                class="px-4 py-1.5 rounded-md text-sm font-medium transition {{ $filter === 'all' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700' }}">
                All
            </button>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            @if($notifications->isEmpty())
                <div class="py-16 text-center">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-2.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <p class="text-gray-400 italic">No notifications</p>
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach($notifications as $notif)
                        @php $data = $notif->data; $isUnread = is_null($notif->read_at); @endphp
                        <div class="flex items-start gap-4 px-5 py-4 {{ $isUnread ? 'bg-indigo-50' : 'hover:bg-gray-50' }} transition-colors">
                            <div class="flex-shrink-0 mt-1">
                                @if($isUnread)
                                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 block"></span>
                                @else
                                    <span class="w-2.5 h-2.5 rounded-full bg-gray-300 block"></span>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-800 {{ $isUnread ? 'font-medium' : '' }}">
                                    {{ $data['message'] ?? 'Notification' }}
                                </p>
                                <p class="text-xs text-gray-400 mt-1">{{ $notif->created_at->diffForHumans() }}</p>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                @if(!empty($data['record_id']) && !empty($data['module_slug']))
                                    <a href="{{ route('dynamic.show', ['moduleSlug' => $data['module_slug'], 'record' => $data['record_id']]) }}"
                                       wire:navigate class="text-xs text-indigo-600 hover:underline font-medium">View</a>
                                @endif
                                @if($isUnread)
                                    <button wire:click="markRead('{{ $notif->id }}')" class="text-xs text-gray-400 hover:text-gray-600" title="Mark read">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                @endif
                                <button wire:click="deleteNotification('{{ $notif->id }}')" class="text-xs text-gray-300 hover:text-red-500 transition" title="Dismiss">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="px-5 py-4 border-t bg-gray-50">
                    {{ $notifications->links() }}
                </div>
            @endif
        </div>

    </div>
</div>
