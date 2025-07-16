<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $user = auth()->user();
        $color = null;

        if($user->hasRole('admin')){
            $color = 'bg-koromiko-300';
        } elseif($user->hasRole('company')) {
            $color = 'bg-blue-500';
        } elseif($user->hasRole('branch')) {
            $color = 'bg-brown-500';
        } elseif($user->hasRole('agent')) {
            $color = 'bg-purple-500';
        }

        return view('components.layouts.app', [
            'color' => $color
        ]);
    }
}
