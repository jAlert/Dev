<div>
    {{-- Page header --}}
    <div class="px-6 pt-6 pb-4 border-b border-gray-200 bg-white flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Login Slides</h1>
            <p class="text-sm text-gray-500 mt-0.5">Manage the hero carousel shown on the login page.</p>
        </div>
        @if(!$editingId && $title === '')
            <button wire:click="resetForm"
                    onclick="document.getElementById('slide-form').scrollIntoView({behavior:'smooth'})"
                    class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Slide
            </button>
        @endif
    </div>

    <div class="p-6 flex flex-col lg:flex-row gap-6 items-start">

        {{-- ── Slide List ──────────────────────────────────── --}}
        <div class="w-full lg:flex-1 bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
            @if($slides->isEmpty())
                <div class="px-6 py-16 text-center text-gray-400 text-sm italic">
                    No slides yet. Add your first slide using the form.
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Slide</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wide w-20">Active</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wide w-24">Order</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wide w-28">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($slides as $slide)
                            <tr class="hover:bg-gray-50 transition-colors {{ $editingId === $slide->id ? 'bg-blue-50' : '' }}">
                                <td class="px-4 py-3 flex items-center gap-3">
                                    {{-- Thumbnail --}}
                                    <div class="w-16 h-12 rounded-lg overflow-hidden bg-blue-100 flex-shrink-0 flex items-center justify-center">
                                        @if($slide->image_path)
                                            <img src="{{ Storage::url($slide->image_path) }}"
                                                 alt="{{ $slide->title }}"
                                                 class="w-full h-full object-cover" />
                                        @else
                                            <svg class="w-6 h-6 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-800 truncate">{{ $slide->title }}</p>
                                        @if($slide->subtitle)
                                            <p class="text-xs text-gray-500 truncate">{{ $slide->subtitle }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button wire:click="toggleActive({{ $slide->id }})"
                                            class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $slide->is_active ? 'bg-blue-600' : 'bg-gray-300' }}"
                                            title="{{ $slide->is_active ? 'Deactivate' : 'Activate' }}">
                                        <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $slide->is_active ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button wire:click="moveUp({{ $slide->id }})"
                                                class="p-1 rounded hover:bg-gray-200 text-gray-500 transition" title="Move up">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                            </svg>
                                        </button>
                                        <span class="text-xs text-gray-400 w-5 text-center">{{ $slide->sort_order }}</span>
                                        <button wire:click="moveDown({{ $slide->id }})"
                                                class="p-1 rounded hover:bg-gray-200 text-gray-500 transition" title="Move down">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button wire:click="edit({{ $slide->id }})"
                                                onclick="document.getElementById('slide-form').scrollIntoView({behavior:'smooth'})"
                                                class="p-1.5 rounded hover:bg-blue-50 text-blue-600 transition" title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                        <button wire:click="delete({{ $slide->id }})"
                                                wire:confirm="Delete this slide?"
                                                class="p-1.5 rounded hover:bg-red-50 text-red-500 transition" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- ── Slide Form ───────────────────────────────────── --}}
        <div id="slide-form" class="w-full lg:w-80 flex-shrink-0 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-sm font-bold text-gray-800 mb-4 uppercase tracking-wide">
                {{ $editingId ? 'Edit Slide' : 'Add New Slide' }}
            </h2>

            <form wire:submit="save" class="flex flex-col gap-4">

                {{-- Title --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                    <input wire:model="title" type="text" placeholder="e.g. Connect with every application"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition" />
                    @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Subtitle --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Subtitle</label>
                    <input wire:model="subtitle" type="text" placeholder="Optional supporting text"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition" />
                    @error('subtitle') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Image upload --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Image {{ $editingId ? '(leave blank to keep existing)' : '' }}
                    </label>
                    <label class="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
                        @if($image)
                            <img src="{{ $image->temporaryUrl() }}" class="h-24 object-contain rounded" />
                        @else
                            <svg class="w-8 h-8 text-gray-300 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-xs text-gray-400">Click to upload (JPG, PNG, GIF, WebP · max 5 MB)</span>
                        @endif
                        <input type="file" wire:model="image" accept="image/*" class="hidden" />
                    </label>
                    @error('image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="image" class="text-xs text-blue-500 mt-1">Uploading…</div>
                    <p class="text-[11px] text-gray-400 mt-1.5 leading-snug">
                        Recommended: <span class="font-medium text-gray-500">800 × 600 px</span> (4:3 ratio) · PNG or JPG · max 5 MB
                    </p>
                </div>

                {{-- Active toggle --}}
                <div class="flex items-center gap-3">
                    <button type="button"
                            @click="$wire.is_active = !$wire.is_active"
                            :class="$wire.is_active ? 'bg-blue-600' : 'bg-gray-300'"
                            x-data
                            class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none">
                        <span :class="$wire.is_active ? 'translate-x-4' : 'translate-x-0'"
                              class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                    </button>
                    <span class="text-sm text-gray-700">Active (visible on login page)</span>
                </div>

                {{-- Actions --}}
                <div class="flex gap-2 pt-1">
                    <button type="submit"
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold py-2 rounded-lg transition">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update Slide' : 'Add Slide' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                    @if($editingId)
                        <button type="button" wire:click="resetForm"
                                class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
