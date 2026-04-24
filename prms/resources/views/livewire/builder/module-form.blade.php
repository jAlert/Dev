<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $module ? 'Edit' : 'Create' }} Module
    </h2>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <form wire:submit="save">

                    {{-- Basic Settings --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Module Name</label>
                            <input type="text" wire:model="name" placeholder="e.g. Permits, Requests, Tickets"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Default Status for New Records</label>
                            <select wire:model="default_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="Draft">Draft</option>
                                <option value="Submitted">Submitted</option>
                                <option value="Under Review">Under Review</option>
                                <option value="Completed">Completed</option>
                                <option value="Returned">Returned</option>
                                <option value="Archived">Archived</option>
                            </select>
                            @error('default_status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea wire:model="description" rows="2"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                    </div>

                    {{-- Module Options --}}
                    <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" wire:model="my_records_only" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <div>
                                <span class="text-sm font-medium text-gray-700">My Records Only</span>
                                <p class="text-xs text-gray-500">Users see only records they created.</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" wire:model="has_submit_button" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <div>
                                <span class="text-sm font-medium text-gray-700">Show Submit Button</span>
                                <p class="text-xs text-gray-500">Displays "Submit for Approval" on records.</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" wire:model="has_return_button" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <div>
                                <span class="text-sm font-medium text-gray-700">Show Return Button</span>
                                <p class="text-xs text-gray-500">Approvers can return records for revision.</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" wire:model="has_draft_button" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <div>
                                <span class="text-sm font-medium text-gray-700">Show Save as Draft</span>
                                <p class="text-xs text-gray-500">Allows saving without submitting.</p>
                            </div>
                        </label>
                    </div>

                    {{-- Module Mirroring --}}
                    <div class="mb-6 p-4 bg-indigo-50 border border-indigo-100 rounded-md">
                        <label class="block text-sm font-bold text-indigo-900 mb-1">Data Source (Mirroring)</label>
                        <p class="text-xs text-indigo-700 mb-3">Leave blank for a standard module. Select a source module to mirror its data.</p>
                        <select wire:model.live="source_module_id" class="mt-1 block w-full md:w-1/2 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Create Standard Module --</option>
                            @foreach($allModules as $am)
                                <option value="{{ $am->id }}">Mirror "{{ $am->name }}"</option>
                            @endforeach
                        </select>
                        @if($source_module_id)
                            <p class="mt-2 text-xs text-indigo-700">✅ Fields from the source module are inherited automatically. Add extra fields below.</p>
                        @endif
                    </div>

                    {{-- Fields --}}
                    <div class="flex items-center justify-between mb-3 border-b pb-2">
                        <h3 class="text-base font-semibold text-gray-800">{{ $source_module_id ? 'Additional Fields' : 'Fields Configuration' }}</h3>
                        <span class="text-xs text-gray-400">Drag rows or use ↑↓ to reorder</span>
                    </div>

                    <div class="space-y-3 mb-6">
                        @foreach($fields as $index => $field)
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <div class="flex items-start gap-3">

                                    {{-- Sort Buttons --}}
                                    <div class="flex flex-col gap-1 mt-1 flex-shrink-0">
                                        <button type="button" wire:click="moveFieldUp({{ $index }})"
                                            class="text-gray-400 hover:text-indigo-600 disabled:opacity-30 p-0.5 rounded hover:bg-indigo-50 transition"
                                            {{ $index === 0 ? 'disabled' : '' }} title="Move up">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                        </button>
                                        <button type="button" wire:click="moveFieldDown({{ $index }})"
                                            class="text-gray-400 hover:text-indigo-600 p-0.5 rounded hover:bg-indigo-50 transition"
                                            {{ $index === count($fields) - 1 ? 'disabled' : '' }} title="Move down">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <span class="text-[10px] text-center text-gray-300 font-mono">{{ $index + 1 }}</span>
                                    </div>

                                    {{-- Field Config --}}
                                    <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div class="md:col-span-2">
                                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Field Label</label>
                                            <input type="text" wire:model="fields.{{ $index }}.name"
                                                placeholder="e.g. Full Name, Amount, Status"
                                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            @error('fields.'.$index.'.name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                            <input type="text" wire:model="fields.{{ $index }}.description"
                                                placeholder="Help text / description (optional)"
                                                class="block w-full rounded-md border-gray-300 text-xs shadow-sm mt-1.5 text-gray-500 focus:border-indigo-300 focus:ring-indigo-300">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Input Type</label>
                                            <select wire:model.live="fields.{{ $index }}.type"
                                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="text">Text / String</option>
                                                <option value="textarea">Text Area (Long)</option>
                                                <option value="email">Email</option>
                                                <option value="phone">Phone</option>
                                                <option value="url">URL / Link</option>
                                                <option value="number">Number</option>
                                                <option value="currency">Currency</option>
                                                <option value="date">Date</option>
                                                <option value="boolean">Checkbox / Boolean</option>
                                                <option value="select">Dropdown (Single)</option>
                                                <option value="multi_select">Multi-Select</option>
                                                <option value="user">User (System User)</option>
                                                <option value="attachment">Attachment / File</option>
                                                <option value="text_editor">Text Editor (Collaborative)</option>
                                            </select>

                                            @if(in_array($fields[$index]['type'] ?? '', ['select', 'multi_select']))
                                                <div class="mt-2">
                                                    <label class="block text-xs text-gray-500 mb-1">Options (one per line)</label>
                                                    <textarea wire:model="fields.{{ $index }}.options_raw"
                                                        rows="3" placeholder="Option 1&#10;Option 2&#10;Option 3"
                                                        class="block w-full rounded-md border-gray-300 text-xs shadow-sm"></textarea>
                                                </div>
                                            @endif

                                            @if(($fields[$index]['type'] ?? '') === 'attachment')
                                                <div class="mt-2 p-2 bg-blue-50 border border-blue-100 rounded-md">
                                                    <label class="flex items-center gap-2 cursor-pointer">
                                                        <input type="checkbox" wire:model="fields.{{ $index }}.versioning"
                                                            class="rounded border-gray-300 text-indigo-600 text-sm">
                                                        <span class="text-xs font-semibold text-blue-800">Enable File Versioning</span>
                                                    </label>
                                                    <p class="text-xs text-blue-600 mt-0.5 ml-5">Keeps all uploaded versions instead of replacing the previous file.</p>
                                                </div>
                                            @endif

                                            {{-- Text Editor Options --}}
                                            @if(($fields[$index]['type'] ?? '') === 'text_editor')
                                                <div class="mt-3 space-y-3">
                                                    <div x-data="{ teImporting: false, teShowHtml: false }">
                                                        <div class="flex items-center justify-between mb-1">
                                                            <label class="block text-xs font-medium text-gray-500">Template (optional)</label>
                                                            @if(!empty($fields[$index]['options_raw_template']))
                                                            <button type="button" x-on:click="teShowHtml = !teShowHtml"
                                                                class="text-[10px] text-indigo-500 hover:text-indigo-700 font-medium">
                                                                <span x-show="!teShowHtml">Edit HTML</span>
                                                                <span x-show="teShowHtml">Show Preview</span>
                                                            </button>
                                                            @endif
                                                        </div>
                                                        {{-- Preview (default) --}}
                                                        <div x-show="!teShowHtml"
                                                            class="w-full min-h-[5rem] border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 text-sm text-gray-700 overflow-auto max-h-48 cursor-default [&_strong]:font-bold [&_em]:italic [&_p]:mb-1 [&_h1]:text-lg [&_h1]:font-bold [&_h2]:font-bold [&_img]:max-w-full"
                                                        >
                                                            @if(!empty($fields[$index]['options_raw_template']))
                                                                {!! $fields[$index]['options_raw_template'] !!}
                                                            @else
                                                                <span class="text-gray-400 italic">No template set. Import a .docx or switch to Edit HTML to paste content.</span>
                                                            @endif
                                                        </div>
                                                        {{-- Raw HTML editor (hidden by default; Alpine toggles it) --}}
                                                        <textarea
                                                            x-show="teShowHtml"
                                                            style="display:none"
                                                            wire:model="fields.{{ $index }}.options_raw_template"
                                                            rows="5"
                                                            placeholder="Paste default HTML content to pre-fill in the editor..."
                                                            class="w-full text-xs font-mono border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400 text-gray-700 bg-white resize-none"
                                                        ></textarea>
                                                        <div class="flex items-center gap-2 mt-1">
                                                            <p class="text-xs text-gray-400 flex-1">Users will see this content when a new record is created.</p>
                                                            <button type="button"
                                                                x-on:click="
                                                                    teImporting = true;
                                                                    const inp = document.createElement('input');
                                                                    inp.type = 'file'; inp.accept = '.docx';
                                                                    inp.onchange = async (e) => {
                                                                        const file = e.target.files[0];
                                                                        if (!file) { teImporting = false; return; }
                                                                        const buf = await file.arrayBuffer();
                                                                        const result = await window.mammoth.convertToHtml({ arrayBuffer: buf });

                                                                        // Upload embedded base64 images to server so HTML stays clean
                                                                        const tmpDoc = new DOMParser().parseFromString(result.value, 'text/html');
                                                                        for (const img of tmpDoc.querySelectorAll('img')) {
                                                                            const src = img.getAttribute('src') || '';
                                                                            if (!src.startsWith('data:image/')) continue;
                                                                            try {
                                                                                const url = await $wire.call('uploadTemplateImage', src);
                                                                                if (url) img.setAttribute('src', url);
                                                                                else (img.closest('p') || img).remove();
                                                                            } catch { (img.closest('p') || img).remove(); }
                                                                        }
                                                                        $wire.set('fields.{{ $index }}.options_raw_template', tmpDoc.body.innerHTML);
                                                                        teImporting = false;
                                                                        teShowHtml = false;
                                                                    };
                                                                    inp.click();
                                                                "
                                                                class="flex-shrink-0 text-xs border border-gray-300 rounded-md px-2 py-1 bg-white text-gray-600 hover:bg-gray-50 flex items-center gap-1.5 transition-colors"
                                                            >
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                                                <span x-show="!teImporting">Import from .docx</span>
                                                                <span x-show="teImporting">Converting…</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-6">
                                                        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                                                            <input
                                                                type="checkbox"
                                                                wire:model="fields.{{ $index }}.require_review"
                                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                            >
                                                            <span>Require "Review Done"</span>
                                                        </label>
                                                        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                                                            <input
                                                                type="checkbox"
                                                                wire:model="fields.{{ $index }}.log_history"
                                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                            >
                                                            <span>Log Input History</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Bottom row: required + visibility --}}
                                        <div class="md:col-span-3 flex flex-wrap items-start gap-4 pt-1">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" wire:model="fields.{{ $index }}.is_required"
                                                    class="rounded border-gray-300 text-indigo-600 text-sm">
                                                <span class="text-sm font-medium text-gray-700">Required</span>
                                            </label>

                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" wire:model="fields.{{ $index }}.show_in_index"
                                                    class="rounded border-gray-300 text-indigo-600 text-sm">
                                                <span class="text-sm font-medium text-gray-700">Show in table</span>
                                            </label>

                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" wire:model.live="fields.{{ $index }}.has_visibility"
                                                    class="rounded border-gray-300 text-indigo-600 text-sm">
                                                <span class="text-sm font-medium text-gray-700">Conditional Visibility</span>
                                            </label>

                                            @if(!empty($fields[$index]['has_visibility']))
                                                <div class="w-full flex flex-wrap items-center gap-2 mt-1 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                                                    <span class="text-xs font-semibold text-yellow-800">Show when</span>
                                                    <select wire:model="fields.{{ $index }}.visibility_conditions.field"
                                                        class="rounded border-gray-300 text-xs shadow-sm">
                                                        <option value="">-- Field --</option>
                                                        @foreach($fields as $fi => $f)
                                                            @if($fi !== $index && !empty($f['name']))
                                                                <option value="{{ \Illuminate\Support\Str::slug($f['name'], '_') }}">{{ $f['name'] }}</option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                    <select wire:model="fields.{{ $index }}.visibility_conditions.operator"
                                                        class="rounded border-gray-300 text-xs shadow-sm">
                                                        <option value="=">equals</option>
                                                        <option value="!=">not equals</option>
                                                        <option value="contains">contains</option>
                                                        <option value="not_empty">is not empty</option>
                                                    </select>
                                                    @if(($fields[$index]['visibility_conditions']['operator'] ?? '=') !== 'not_empty')
                                                        <input type="text" wire:model="fields.{{ $index }}.visibility_conditions.value"
                                                            placeholder="value"
                                                            class="rounded border-gray-300 text-xs shadow-sm w-28">
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Remove button --}}
                                    <button type="button" wire:click="removeField({{ $index }})"
                                        class="flex-shrink-0 mt-1 text-red-400 hover:text-red-600 p-1 rounded hover:bg-red-50 transition" title="Remove field">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" wire:click="addField"
                        class="mb-8 text-sm text-indigo-600 font-bold hover:text-indigo-900 border border-indigo-300 rounded-lg px-4 py-2 hover:bg-indigo-50 transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Field
                    </button>

                    <div class="flex justify-end pt-4 border-t gap-3">
                        <a href="{{ route('builder.modules.index') }}" wire:navigate
                            class="px-6 py-2 border rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
                        <button type="submit"
                            class="px-6 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 shadow-sm">Save Module</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
