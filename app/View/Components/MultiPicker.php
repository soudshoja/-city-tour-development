<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class MultiPicker extends Component
{
    public $label;
    public $name;
    public $items;
    public $preselected;
    public $allLabel;
    public $placeholder;

    public function __construct(
        $label = '',
        $name = '',
        $items = '[]',
        $preselected = '[]',
        $allLabel = 'All',
        $placeholder = 'Select items'
    ) {
        $this->label = $label;
        $this->name = $name;
        $this->items = $items;
        $this->preselected = $preselected;
        $this->allLabel = $allLabel;
        $this->placeholder = $placeholder;
    }

    public function render(): View|Closure|string
    {
        return view('components.multi-picker');
    }
}
