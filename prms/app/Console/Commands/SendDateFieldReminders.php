<?php

namespace App\Console\Commands;

use App\Mail\StageNotificationMail;
use App\Models\Record;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Notifications\DynamicNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDateFieldReminders extends Command
{
    protected $signature = 'prms:send-date-field-reminders';
    protected $description = 'Send email reminders N days before date field values configured on workflow stages.';

    public function handle(): void
    {
        $stages = WorkflowStage::whereNotNull('date_reminders_json')->with('module')->get();

        if ($stages->isEmpty()) {
            $this->info('No stages with date field reminders configured.');
            return;
        }

        $sent = 0;

        foreach ($stages as $stage) {
            foreach ($stage->date_reminders_json as $reminder) {
                $fieldSlug  = $reminder['field_slug'] ?? '';
                $daysBefore = max(1, (int) ($reminder['days_before'] ?? 1));
                $recipients = $reminder['recipients'] ?? [];

                if (!$fieldSlug || empty($recipients)) continue;

                $targetDate = Carbon::today()->addDays($daysBefore)->format('Y-m-d');

                $records = Record::whereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(data, '$.{$fieldSlug}')) = ?",
                    [$targetDate]
                )->with('module')->get();

                foreach ($records as $record) {
                    $moduleSlug = $record->module?->slug;
                    $recordUrl  = $moduleSlug ? url("/app/{$moduleSlug}/{$record->id}") : null;
                    $title      = $record->data['title'] ?? "Record #{$record->id}";
                    $subject    = "TRC Schedule Reminder: {$title}";
                    $message    = "This is a reminder that \"{$title}\" is scheduled for " . Carbon::parse($targetDate)->format('F d, Y') . " ({$daysBefore} day(s) from today).";

                    foreach ($recipients as $recipient) {
                        $type  = $recipient['type'] ?? '';
                        $value = $recipient['value'] ?? '';

                        try {
                            if ($type === 'submitter') {
                                $user = User::find($record->created_by);
                                $user?->notify(new DynamicNotification($message, $record->id, $moduleSlug, $subject, true));
                            } elseif ($type === 'role' && $value) {
                                foreach (User::role($value)->get() as $user) {
                                    $user->notify(new DynamicNotification($message, $record->id, $moduleSlug, $subject, true));
                                }
                            } elseif ($type === 'specific_user' && $value) {
                                $user = User::find($value);
                                $user?->notify(new DynamicNotification($message, $record->id, $moduleSlug, $subject, true));
                            } elseif ($type === 'specific_email' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                Mail::to($value)->send(new StageNotificationMail($message, $subject, $recordUrl));
                            }
                            $sent++;
                        } catch (\Throwable $e) {
                            Log::warning('[date-field-reminders] Failed type=' . $type . ' record=' . $record->id . ': ' . $e->getMessage());
                        }
                    }

                    $this->line("  ✉ Record #{$record->id} ({$title}): reminder sent, scheduled {$targetDate}");
                }
            }
        }

        $this->info("Sent {$sent} date field reminder(s).");
    }
}
