<?php

namespace App\Console\Commands;

use App\Models\Record;
use App\Models\RecordApproval;
use App\Models\RecordHistory;
use App\Models\WorkflowStage;
use App\Notifications\DynamicNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AdvanceDeadlineStages extends Command
{
    protected $signature = 'prms:advance-deadline-stages';
    protected $description = 'Auto-advance records that have exceeded their stage deadline (in working days).';

    public function handle(): void
    {
        $stages = WorkflowStage::whereNotNull('auto_advance_days')->get();

        if ($stages->isEmpty()) {
            $this->info('No stages with deadlines configured.');
            return;
        }

        $advanced = 0;

        foreach ($stages as $stage) {
            $records = Record::where('current_stage_id', $stage->id)
                ->whereNotNull('stage_entered_at')
                ->whereIn('status', ['Submitted', 'Under Review'])
                ->get();

            foreach ($records as $record) {
                $workingDaysElapsed = $this->countWorkingDays($record->stage_entered_at, now());

                if ($workingDaysElapsed < $stage->auto_advance_days) {
                    continue;
                }

                $this->advanceRecord($record, $stage);
                $advanced++;
            }
        }

        $this->info("Auto-advanced {$advanced} record(s).");
    }

    private function advanceRecord(Record $record, WorkflowStage $currentStage): void
    {
        $isFinal = $currentStage->is_final_approval;

        if (!$isFinal) {
            $nextStage = WorkflowStage::where('module_id', $currentStage->module_id)
                ->where('order', '>', $currentStage->order)
                ->orderBy('order')
                ->first();

            if ($nextStage) {
                $record->update([
                    'status'          => $nextStage->default_status ?? 'Under Review',
                    'current_stage_id' => $nextStage->id,
                    'stage_entered_at' => now(),
                ]);

                RecordApproval::create([
                    'record_id' => $record->id,
                    'stage_id'  => $currentStage->id,
                    'user_id'   => null,
                    'action'    => 'auto_advanced',
                    'comment'   => "Auto-advanced after {$currentStage->auto_advance_days} working day(s) with no action.",
                ]);

                RecordHistory::create([
                    'record_id'    => $record->id,
                    'user_id'      => null,
                    'action'       => 'auto_advanced',
                    'changes_json' => [
                        'from_stage' => $currentStage->name,
                        'to_stage'   => $nextStage->name,
                        'reason'     => "Deadline of {$currentStage->auto_advance_days} working day(s) exceeded.",
                    ],
                ]);

                // Notify approvers of new stage
                $this->notifyStageApprovers($nextStage, $record,
                    "A record has been auto-advanced to your stage after the previous stage deadline expired.");

                $this->line("  ↑ Record #{$record->id} advanced: {$currentStage->name} → {$nextStage->name}");
                return;
            }
        }

        // No next stage — treat as approved
        $record->update([
            'status'          => 'Completed',
            'current_stage_id' => $currentStage->id,
            'stage_entered_at' => null,
        ]);

        RecordApproval::create([
            'record_id' => $record->id,
            'stage_id'  => $currentStage->id,
            'user_id'   => null,
            'action'    => 'auto_approved',
            'comment'   => "Auto-approved after {$currentStage->auto_advance_days} working day(s) with no action.",
        ]);

        RecordHistory::create([
            'record_id'    => $record->id,
            'user_id'      => null,
            'action'       => 'auto_approved',
            'changes_json' => [
                'reason' => "Deadline of {$currentStage->auto_advance_days} working day(s) exceeded at final stage.",
            ],
        ]);

        $submitter = User::find($record->created_by);
        $module    = $record->module;
        $submitter?->notify(new DynamicNotification(
            "Your record in {$module->name} has been auto-approved (deadline expired).",
            $record->id,
            $module->slug
        ));

        $this->line("  ✓ Record #{$record->id} auto-approved (final stage deadline exceeded).");
    }

    private function notifyStageApprovers(WorkflowStage $stage, Record $record, string $message): void
    {
        if (!$stage->approver_role_id) return;

        $users = User::role($stage->approverRole->name)->get();
        $module = $record->module;
        foreach ($users as $user) {
            $user->notify(new DynamicNotification($message, $record->id, $module->slug));
        }
    }

    /**
     * Count working days (Mon–Fri) between two dates, inclusive of start, exclusive of end.
     */
    private function countWorkingDays(Carbon $from, Carbon $to): int
    {
        $count = 0;
        $current = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($current->lt($end)) {
            if ($current->isWeekday()) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }
}
