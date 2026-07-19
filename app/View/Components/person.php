<?php

namespace App\View\Components;

use App\View\Components\Concerns\ResolvesCurrentPostImages;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class person extends Component
{
    use ResolvesCurrentPostImages;

    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $img,
        public string $title,
        public string $description,
    ) {
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.person', [
            'images' => $this->currentPostImages(),
        ]);
    }
}
