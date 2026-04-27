<?php

namespace App\Livewire\Builder;

use Livewire\Component;
use App\Models\Module;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;

class ModuleForm extends Component
{
    public ?Module $module = null;
    public $name = '';
    public $description = '';
    public $default_status = 'Submitted';
    public $my_records_only = false;
    public $has_submit_button = false;
    public $has_return_button = false;
    public $has_draft_button = false;
    public $source_module_id = null;
    public $allModules = [];
    public $fields = [];

    public function mount(Module $module = null)
    {
        $this->allModules = Module::whereNull('source_module_id')->where('id', '!=', $module?->id)->get();

        if ($module && $module->exists) {
            $this->module = $module;
            $this->name = $module->name;
            $this->description = $module->description;
            $this->default_status = $module->default_status ?? 'Submitted';
            $this->my_records_only = (bool) $module->my_records_only;
            $this->has_submit_button = (bool) $module->has_submit_button;
            $this->has_return_button = (bool) $module->has_return_button;
            $this->has_draft_button = (bool) $module->has_draft_button;
            $this->source_module_id = $module->source_module_id;
            $this->fields = $module->fields->sortBy('sort_order')->values()->map(function ($f) {
                $arr = $f->toArray();
                $arr['options_raw'] = ($f->type === 'text_editor' || !is_array($f->options_json))
                    ? ''
                    : implode("\n", $f->options_json);
                $arr['show_in_index'] = $f->show_in_index ?? true;
                $arr['col_span'] = $f->col_span ?? 1;
                $arr['versioning'] = $f->versioning ?? false;
                $arr['visibility_conditions'] = $f->visibility_conditions ?? ['field' => '', 'operator' => '=', 'value' => ''];
                $arr['has_visibility'] = !empty($f->visibility_conditions['field']);
                $arr['options_raw_template'] = $f->options_json['template'] ?? '';
                $arr['require_review'] = $f->options_json['require_review'] ?? false;
                $arr['log_history'] = $f->options_json['log_history'] ?? false;
                return $arr;
            })->toArray();
        } else {
            $this->addField();
        }
    }

    public function addField()
    {
        $this->fields[] = [
            'name' => '', 'type' => 'text', 'is_required' => false,
            'options_json' => [], 'options_raw' => '', 'description' => '',
            'sort_order' => count($this->fields),
            'show_in_index' => true,
            'col_span' => 1,
            'versioning' => false,
            'visibility_conditions' => ['field' => '', 'operator' => '=', 'value' => ''],
            'has_visibility' => false,
            'options_raw_template' => '',
            'require_review' => false,
            'log_history' => false,
        ];
    }

    public function removeField($index)
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
        $this->reindexSortOrder();
    }

    public function moveFieldUp($index)
    {
        if ($index === 0) return;
        $temp = $this->fields[$index - 1];
        $this->fields[$index - 1] = $this->fields[$index];
        $this->fields[$index] = $temp;
        $this->reindexSortOrder();
    }

    public function moveFieldDown($index)
    {
        if ($index >= count($this->fields) - 1) return;
        $temp = $this->fields[$index + 1];
        $this->fields[$index + 1] = $this->fields[$index];
        $this->fields[$index] = $temp;
        $this->reindexSortOrder();
    }

    private function reindexSortOrder()
    {
        foreach ($this->fields as $i => &$field) {
            $field['sort_order'] = $i;
        }
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'default_status' => 'required|in:Draft,Submitted,Under Review,Completed,Returned,Archived',
            'my_records_only' => 'boolean',
            'has_submit_button' => 'boolean',
            'has_return_button' => 'boolean',
            'has_draft_button' => 'boolean',
            'source_module_id' => 'nullable|exists:modules,id',
        ];

        if (!empty($this->fields)) {
            $rules['fields.*.name'] = 'required|string|max:255';
            $rules['fields.*.type'] = 'required|in:text,textarea,email,phone,url,number,date,select,multi_select,boolean,attachment,user,currency,text_editor';
        }

        $this->validate($rules);

        // Preserve existing slug on edit — changing it would break permissions, records, and routes.
        // Only generate a new slug (with underscore separator) when creating a new module.
        $slug = $this->module?->exists
            ? $this->module->slug
            : Str::slug($this->name, '_');

        $module = Module::updateOrCreate(
            ['id' => $this->module?->id],
            [
                'name' => $this->name,
                'slug' => $slug,
                'description' => $this->description,
                'default_status' => $this->default_status,
                'my_records_only' => $this->my_records_only,
                'has_submit_button' => $this->has_submit_button,
                'has_return_button' => $this->has_return_button,
                'has_draft_button' => $this->has_draft_button,
                'source_module_id' => $this->source_module_id ?: null,
            ]
        );

        // Only create permissions when the module is new.
        // On edit the slug is frozen, so permissions are unchanged — don't touch them.
        if (!$this->module?->exists) {
            $permissions = ["view-{$slug}", "create-{$slug}", "edit-{$slug}", "delete-{$slug}", "change-status-{$slug}", "review-{$slug}", "approve-{$slug}"];
            foreach ($permissions as $perm) {
                \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            }
        }

        $module->fields()->delete();
        foreach ($this->fields as $i => $field) {
            if ($field['type'] === 'text_editor') {
                $optionsJson = [
                    'template'       => $field['options_raw_template'] ?? '',
                    'require_review' => !empty($field['require_review']),
                    'log_history'    => !empty($field['log_history']),
                ];
            } else {
                $optionsJson = [];
                if (!empty($field['options_raw'])) {
                    $optionsJson = array_values(array_filter(
                        array_map('trim', explode("\n", $field['options_raw']))
                    ));
                }
            }

            $visibilityConditions = null;
            if (!empty($field['has_visibility']) && !empty($field['visibility_conditions']['field'])) {
                $visibilityConditions = $field['visibility_conditions'];
            }

            $module->fields()->create([
                'name'                   => $field['name'],
                'slug'                   => Str::slug($field['name'], '_'),
                'type'                   => $field['type'],
                'is_required'            => $field['is_required'] ?? false,
                'options_json'           => $optionsJson ?: null,
                'description'            => $field['description'] ?? null,
                'sort_order'             => $i,
                'show_in_index'          => $field['show_in_index'] ?? true,
                'col_span'               => in_array($field['col_span'] ?? 1, [1, 2]) ? ($field['col_span'] ?? 1) : 1,
                'versioning'             => ($field['type'] === 'attachment') ? ($field['versioning'] ?? false) : false,
                'visibility_conditions'  => $visibilityConditions,
            ]);
        }

        return redirect()->route('builder.modules.index');
    }

    /**
     * Upload a base64 data URI image (from DOCX import) to public storage.
     * Returns a public URL so the template HTML uses file paths, not inline base64.
     */
    public function uploadTemplateImage(string $dataUri): string
    {
        if (!str_starts_with($dataUri, 'data:image/')) {
            return '';
        }

        [$meta, $b64] = explode(',', $dataUri, 2) + ['', ''];
        $decoded = base64_decode($b64, strict: true);
        if ($decoded === false) return '';

        preg_match('/data:image\/(\w+)/', $meta, $m);
        $ext = match($m[1] ?? 'png') { 'jpeg' => 'jpg', default => $m[1] ?? 'png' };

        $path = 'template-images/' . uniqid('tpl_', true) . '.' . $ext;
        Storage::disk('public')->put($path, $decoded);

        return Storage::disk('public')->url($path);
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.builder.module-form');
    }
}
