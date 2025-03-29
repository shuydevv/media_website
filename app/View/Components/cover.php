<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class cover extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $title1,
        public string $title2,
        public string $description,
        public string $img,
        public string $isTherePlans,
        public $tags,
    )
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.cover');
    }
}
