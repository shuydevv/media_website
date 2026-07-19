<?php

namespace App\View\Components;

use App\View\Components\Concerns\ResolvesCurrentPostImages;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class quote extends Component
{
    use ResolvesCurrentPostImages;

    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $text,
        public string $name,
        public string $description,
        public string $img,
    ) {
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.quote', [
            'images' => $this->currentPostImages(),
        ]);
    }
}
