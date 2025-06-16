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
    public $selected;
    public $placeholder;
    public $maxResults;

    public function __construct($label = '', $name = '', $items = '', $selected = '', $placeholder = '', $maxResults = '')
    {
        $this->label = $label;
        $this->name = $name;
        $this->items = $items;     
        $this->selected = $selected;
        $this->placeholder = $placeholder;
        $this->maxResults = $maxResults;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.searchable-dropdown');
    }
}
