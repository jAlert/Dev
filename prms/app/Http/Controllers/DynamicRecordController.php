<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\Record;

class DynamicRecordController extends Controller
{
    public function exportCsv(string $moduleSlug)
    {
        $module = Module::with(['fields' => fn($q) => $q->orderBy('sort_order')])
            ->where('slug', $moduleSlug)
            ->firstOrFail();

        if (!auth()->user()->can("view-{$moduleSlug}")) abort(403);

        // Merge source module fields if mirrored
        if ($module->source_module_id) {
            $sourceFields = Module::find($module->source_module_id)->fields()->orderBy('sort_order')->get();
            $module->setRelation('fields', $sourceFields->merge($module->fields));
        }

        $targetModuleId = $module->source_module_id ?? $module->id;

        $query = Record::where('module_id', $targetModuleId)
            ->with(['currentStage', 'creator']);

        if ($module->source_module_id) {
            $query->where('status', '!=', 'Draft');
        }

        $records = $query->orderBy('created_at', 'desc')->get();
        $fields  = $module->fields;
        $filename = $module->slug . '-export-' . now()->format('Y-m-d') . '.csv';

        $handle = fopen('php://temp', 'r+');

        $headers = $fields->pluck('name')->toArray();
        array_push($headers, 'Status', 'Stage', 'Created By', 'Created At');
        fputcsv($handle, $headers);

        foreach ($records as $record) {
            $row = [];
            foreach ($fields as $field) {
                $value = $record->data[$field->slug] ?? '';
                if (is_array($value)) $value = implode(', ', $value);
                $row[] = $value;
            }
            $row[] = $record->status;
            $row[] = $record->currentStage?->name ?? '';
            $row[] = $record->creator?->name ?? '';
            $row[] = $record->created_at->format('Y-m-d H:i');
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
