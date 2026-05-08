<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\DynamicRecordController;
use App\Livewire\Builder\ModuleIndex;
use App\Livewire\Builder\ModuleForm;
use App\Livewire\Builder\Dashboard;
use App\Livewire\Admin\UserManagement;
use App\Livewire\Admin\RoleManagement;
use App\Livewire\Builder\DynamicRecordIndex;
use App\Livewire\Builder\DynamicRecordForm;
use App\Livewire\Builder\DynamicRecordShow;
use App\Livewire\Builder\ApprovalQueue;
use App\Livewire\Builder\WorkflowStageManager;
use App\Livewire\Builder\WorkflowManager;
use App\Livewire\Builder\NotificationCenter;
use App\Livewire\Builder\AuditLog;
use App\Livewire\Builder\WebhookManager;
use App\Livewire\Builder\ApiManager;

Route::redirect('/', '/login');

Route::get('dashboard', Dashboard::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/admin/users', UserManagement::class)
    ->middleware(['auth', 'verified', 'role:super admin'])
    ->name('admin.users');

Route::get('/admin/roles', RoleManagement::class)
    ->middleware(['auth', 'verified', 'role:super admin'])
    ->name('admin.roles');

Route::get('/admin/login-slides', \App\Livewire\Admin\LoginSlideManager::class)
    ->middleware(['auth', 'verified', 'role:super admin'])
    ->name('admin.login-slides');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';

Route::get('/auth/google/redirect', [SocialController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [SocialController::class, 'callback'])->name('google.callback');

Route::middleware(['auth', 'verified'])->prefix('builder')->name('builder.')->group(function () {
    Route::get('/modules', ModuleIndex::class)->name('modules.index');
    Route::get('/modules/create', ModuleForm::class)->name('modules.create');
    Route::get('/modules/{module}/edit', ModuleForm::class)->name('modules.edit');
    Route::get('/approval-queue', ApprovalQueue::class)->name('approval.queue');
    Route::get('/workflow-stages/{module}', WorkflowStageManager::class)->name('workflow.stages');
    Route::get('/workflows/{module}', WorkflowManager::class)->name('workflow.manager');
    Route::get('/audit', AuditLog::class)->name('audit');
    Route::get('/webhooks', WebhookManager::class)->name('webhooks');
    Route::get('/api-manager', ApiManager::class)->name('api.manager');
});

Route::get('/notifications', NotificationCenter::class)
    ->middleware(['auth', 'verified'])
    ->name('builder.notifications');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/notifications/{id}/read', function ($id) {
        auth()->user()->notifications()->where('id', $id)->update(['read_at' => now()]);
        return response()->noContent();
    })->name('notifications.markRead');

    Route::get('/notifications/{id}/open', function ($id) {
        $notif = auth()->user()->notifications()->where('id', $id)->first();
        if ($notif) {
            $notif->markAsRead();
            $data = $notif->data;
            if (!empty($data['record_id']) && !empty($data['module_slug'])) {
                return redirect()->route('dynamic.show', ['moduleSlug' => $data['module_slug'], 'record' => $data['record_id']]);
            }
        }
        return redirect()->route('builder.approval.queue');
    })->name('notifications.open');

    Route::post('/notifications/read-all', function () {
        auth()->user()->unreadNotifications->markAsRead();
        return back();
    })->name('notifications.markAllRead');

    Route::get('/app/{moduleSlug}', DynamicRecordIndex::class)->name('dynamic.index');
    Route::get('/app/{moduleSlug}/create', DynamicRecordForm::class)->name('dynamic.create');
    Route::get('/app/{moduleSlug}/export-csv', [DynamicRecordController::class, 'exportCsv'])->name('dynamic.export-csv');
    Route::get('/app/{moduleSlug}/{record}', DynamicRecordShow::class)->name('dynamic.show');
    Route::get('/app/{moduleSlug}/{record}/edit', DynamicRecordForm::class)->name('dynamic.edit');
});
