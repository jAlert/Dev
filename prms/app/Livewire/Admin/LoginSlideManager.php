<?php

namespace App\Livewire\Admin;

use App\Models\LoginSlide;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class LoginSlideManager extends Component
{
    use WithFileUploads;

    public $slides;
    public $editingId = null;
    public $title = '';
    public $subtitle = '';
    public $image = null;
    public $is_active = true;
    public $sort_order = 0;

    public function mount(): void
    {
        $this->loadSlides();
    }

    public function loadSlides(): void
    {
        $this->slides = LoginSlide::orderBy('sort_order')->get();
    }

    public function save(): void
    {
        $rules = [
            'title'      => 'required|string|max:120',
            'subtitle'   => 'nullable|string|max:200',
            'is_active'  => 'boolean',
            'sort_order' => 'integer|min:0',
        ];

        if ($this->image) {
            $rules['image'] = 'image|max:5120|mimes:jpg,jpeg,png,gif,webp';
        }

        $this->validate($rules);

        $data = [
            'title'      => $this->title,
            'subtitle'   => $this->subtitle ?: null,
            'is_active'  => $this->is_active,
            'sort_order' => $this->sort_order,
        ];

        if ($this->image) {
            $ext      = $this->image->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $ext;
            $path     = $this->image->storeAs('login-slides', $filename, 'public');
            $data['image_path'] = $path;

            // Delete old image if editing
            if ($this->editingId) {
                $old = LoginSlide::find($this->editingId);
                if ($old && $old->image_path) {
                    Storage::disk('public')->delete($old->image_path);
                }
            }
        }

        if ($this->editingId) {
            LoginSlide::find($this->editingId)?->update($data);
            $this->dispatch('notify', type: 'success', message: 'Slide updated.');
        } else {
            $data['sort_order'] = LoginSlide::max('sort_order') + 1;
            LoginSlide::create($data);
            $this->dispatch('notify', type: 'success', message: 'Slide added.');
        }

        $this->resetForm();
        $this->loadSlides();
    }

    public function edit(int $id): void
    {
        $slide = LoginSlide::findOrFail($id);
        $this->editingId  = $id;
        $this->title      = $slide->title;
        $this->subtitle   = $slide->subtitle ?? '';
        $this->is_active  = $slide->is_active;
        $this->sort_order = $slide->sort_order;
        $this->image      = null;
    }

    public function toggleActive(int $id): void
    {
        $slide = LoginSlide::findOrFail($id);
        $slide->update(['is_active' => ! $slide->is_active]);
        $this->loadSlides();
    }

    public function moveUp(int $id): void
    {
        $slide = LoginSlide::findOrFail($id);
        $prev  = LoginSlide::where('sort_order', '<', $slide->sort_order)
                           ->orderBy('sort_order', 'desc')
                           ->first();
        if ($prev) {
            [$slide->sort_order, $prev->sort_order] = [$prev->sort_order, $slide->sort_order];
            $slide->save();
            $prev->save();
        }
        $this->loadSlides();
    }

    public function moveDown(int $id): void
    {
        $slide = LoginSlide::findOrFail($id);
        $next  = LoginSlide::where('sort_order', '>', $slide->sort_order)
                           ->orderBy('sort_order')
                           ->first();
        if ($next) {
            [$slide->sort_order, $next->sort_order] = [$next->sort_order, $slide->sort_order];
            $slide->save();
            $next->save();
        }
        $this->loadSlides();
    }

    public function delete(int $id): void
    {
        $slide = LoginSlide::findOrFail($id);
        if ($slide->image_path) {
            Storage::disk('public')->delete($slide->image_path);
        }
        $slide->delete();
        if ($this->editingId === $id) {
            $this->resetForm();
        }
        $this->loadSlides();
        $this->dispatch('notify', type: 'success', message: 'Slide deleted.');
    }

    public function resetForm(): void
    {
        $this->editingId  = null;
        $this->title      = '';
        $this->subtitle   = '';
        $this->image      = null;
        $this->is_active  = true;
        $this->sort_order = 0;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.login-slide-manager')
            ->layout('layouts.app');
    }
}
