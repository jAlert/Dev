<?php

namespace App\Listeners;

use App\Events\RecordSaved;
use App\Models\Workflow;
use App\Models\WorkflowAction;
use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Models\User;
use App\Notifications\DynamicNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use App\Mail\StageNotificationMail;

class ProcessWorkflows
{
    public function handle(RecordSaved $event): void
    {
        $this->fireWebhooks($event);

        $workflows = Workflow::where('module_id', $event->record->module_id)
            ->where('trigger', $event->trigger)
            ->with('actions')
            ->get();

        foreach ($workflows as $workflow) {
            if (!$this->evaluateConditions($workflow, $event)) {
                continue;
            }

            foreach ($workflow->actions as $action) {
                match ($action->type) {
                    'notify_user'  => $this->handleNotifyUser($action, $event, $workflow),
                    'notify_role'  => $this->handleNotifyRole($action, $event, $workflow),
                    'assign_to'    => $this->handleAssignTo($action, $event),
                    'set_field'    => $this->handleSetField($action, $event),
                    'send_email'   => $this->handleSendEmail($action, $event, $workflow),
                    default        => null,
                };
            }
        }
    }

    private function evaluateConditions(Workflow $workflow, RecordSaved $event): bool
    {
        $conditionsJson = $workflow->conditions_json;
        if (empty($conditionsJson['conditions'])) {
            return true; // No conditions = always run
        }

        $conditions = $conditionsJson['conditions'];
        $logic = $conditionsJson['logic'] ?? 'and';
        $record = $event->record;
        $data = $record->data ?? [];

        $results = array_map(function ($condition) use ($record, $data) {
            $field    = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? '=';
            $expected = $condition['value'] ?? '';

            // Get field value — support 'status' as a special field
            $actual = $field === 'status' ? $record->status : ($data[$field] ?? null);

            return match ($operator) {
                '='         => (string) $actual === (string) $expected,
                '!='        => (string) $actual !== (string) $expected,
                'contains'  => str_contains((string) $actual, (string) $expected),
                'not_empty' => !empty($actual),
                'empty'     => empty($actual),
                default     => false,
            };
        }, $conditions);

        return $logic === 'or'
            ? in_array(true, $results, true)
            : !in_array(false, $results, true);
    }

    private function fireWebhooks(RecordSaved $event): void
    {
        $webhooks = Webhook::where('is_active', true)
            ->where(function ($q) use ($event) {
                $q->whereNull('module_id')
                  ->orWhere('module_id', $event->record->module_id);
            })
            ->get();

        foreach ($webhooks as $webhook) {
            $events = $webhook->events ?? [];
            if (!in_array($event->trigger, $events) && !in_array('*', $events)) {
                continue;
            }

            $payload = [
                'event'     => $event->trigger,
                'record_id' => $event->record->id,
                'module'    => $event->record->module?->slug,
                'status'    => $event->record->status,
                'data'      => $event->record->data,
                'timestamp' => now()->toIso8601String(),
            ];

            try {
                $headers = ['Content-Type' => 'application/json', 'X-PRMS-Event' => $event->trigger];
                if ($webhook->secret) {
                    $headers['X-PRMS-Signature'] = hash_hmac('sha256', json_encode($payload), $webhook->secret);
                }

                $response = Http::timeout(10)->withHeaders($headers)->post($webhook->url, $payload);

                WebhookLog::create([
                    'webhook_id'    => $webhook->id,
                    'event'         => $event->trigger,
                    'payload'       => $payload,
                    'response_code' => $response->status(),
                    'response_body' => substr($response->body(), 0, 1000),
                    'success'       => $response->successful(),
                ]);
            } catch (\Throwable $e) {
                WebhookLog::create([
                    'webhook_id'    => $webhook->id,
                    'event'         => $event->trigger,
                    'payload'       => $payload,
                    'response_code' => null,
                    'response_body' => $e->getMessage(),
                    'success'       => false,
                ]);
            }
        }
    }

    private function handleNotifyUser(WorkflowAction $action, RecordSaved $event, Workflow $workflow): void
    {
        $userId  = $action->config_json['user_id'] ?? null;
        $message = $action->config_json['message'] ?? "Workflow: {$workflow->name} triggered in {$event->record->module?->name}";
        if ($userId) {
            User::find($userId)?->notify(new DynamicNotification($message, $event->record->id, $event->record->module?->slug));
        }
    }

    private function handleNotifyRole(WorkflowAction $action, RecordSaved $event, Workflow $workflow): void
    {
        $roleName = $action->config_json['role_name'] ?? null;
        $message  = $action->config_json['message'] ?? "Workflow: {$workflow->name} triggered in {$event->record->module?->name}";
        if (!$roleName) return;

        foreach (User::role($roleName)->get() as $user) {
            $user->notify(new DynamicNotification($message, $event->record->id, $event->record->module?->slug));
        }
    }

    private function handleAssignTo(WorkflowAction $action, RecordSaved $event): void
    {
        $userId = $action->config_json['user_id'] ?? null;
        if ($userId && User::where('id', $userId)->exists()) {
            $event->record->update(['assigned_to' => $userId]);
        }
    }

    private function handleSetField(WorkflowAction $action, RecordSaved $event): void
    {
        $field = $action->config_json['field'] ?? null;
        $value = $action->config_json['value'] ?? null;
        if ($field !== null) {
            $data         = $event->record->data ?? [];
            $data[$field] = $value;
            $event->record->update(['data' => $data]);
        }
    }

    private function handleSendEmail(WorkflowAction $action, RecordSaved $event, Workflow $workflow): void
    {
        $subject    = $action->config_json['subject'] ?? "Workflow Notification: {$workflow->name}";
        $message    = $action->config_json['message'] ?? "Workflow {$workflow->name} was triggered for Record #{$event->record->id}.";
        $recipients = $action->config_json['recipients'] ?? [];
        $moduleSlug = $event->record->module?->slug;
        $recordUrl  = $moduleSlug ? url("/app/{$moduleSlug}/{$event->record->id}") : null;

        // Legacy fallback: old single `to` field
        if (empty($recipients) && !empty($action->config_json['to'])) {
            $to = $action->config_json['to'];
            if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($to)->send(new StageNotificationMail($message, $subject, $recordUrl));
                } catch (\Throwable $e) {
                    Log::warning('[send_email workflow] Failed: ' . $e->getMessage());
                }
            }
            return;
        }

        foreach ($recipients as $recipient) {
            $type  = $recipient['type'] ?? '';
            $value = $recipient['value'] ?? '';

            try {
                if ($type === 'submitter') {
                    $user = User::find($event->record->created_by);
                    $user?->notify(new DynamicNotification($message, $event->record->id, $moduleSlug, $subject, true));
                } elseif ($type === 'role' && $value) {
                    foreach (User::role($value)->get() as $user) {
                        $user->notify(new DynamicNotification($message, $event->record->id, $moduleSlug, $subject, true));
                    }
                } elseif ($type === 'specific_user' && $value) {
                    $user = User::find($value);
                    $user?->notify(new DynamicNotification($message, $event->record->id, $moduleSlug, $subject, true));
                } elseif ($type === 'specific_email' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($value)->send(new StageNotificationMail($message, $subject, $recordUrl));
                }
            } catch (\Throwable $e) {
                Log::warning('[send_email workflow] Failed type=' . $type . ': ' . $e->getMessage());
            }
        }
    }
}
