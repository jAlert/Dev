<?php

namespace App\Livewire\Builder;

use Livewire\Component;
use App\Models\Module;
use Livewire\Attributes\Layout;

class ModuleIndex extends Component
{
    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.builder.module-index', [
            'modules' => Module::withCount('fields')->orderBy('sort_order')->get(),
        ]);
    }

    public function reorder(array $order): void
    {
        foreach ($order as $index => $id) {
            Module::where('id', $id)->update(['sort_order' => $index]);
        }
    }

    public function delete(Module $module)
    {
        $module->delete();
    }
}
