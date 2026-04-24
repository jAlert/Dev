<?php

namespace App\Livewire\Builder;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

class NotificationCenter extends Component
{
    use WithPagination;

    public string $filter = 'unread'; // unread | all

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
        $this->resetPage();
    }

    public function markRead($id): void
    {
        auth()->user()->notifications()->where('id', $id)->update(['read_at' => now()]);
    }

    public function deleteNotification($id): void
    {
        auth()->user()->notifications()->where('id', $id)->delete();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $query = $this->filter === 'unread'
            ? auth()->user()->unreadNotifications()
            : auth()->user()->notifications();

        $notifications = $query->latest()->paginate(20);
        $unreadCount   = auth()->user()->unreadNotifications()->count();

        return view('livewire.builder.notification-center', compact('notifications', 'unreadCount'));
    }
}
