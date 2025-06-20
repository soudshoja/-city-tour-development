<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class SearchableDropdown extends Component
{

    public $label;
    public $name;
    public $items;
    public $placeholder;
    public $maxResults;

    public function __construct($label = '', $name = '', $items = '', $placeholder = '', $maxResults = '')
    {
        $this->label = $label;
        $this->name = $name;
        $this->items = $items;     
        $this->placeholder = $placeholder;
        $this->maxResults = $maxResults;
    }

    public function render(): View|Closure|string
    {
        return view('components.searchable-dropdown');
    }
}
