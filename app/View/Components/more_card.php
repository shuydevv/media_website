<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class more_card extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $title,
        public string $title2,
        public string $description,
        public string $img,
        public array $tags
    )
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.more_card');
    }
}
