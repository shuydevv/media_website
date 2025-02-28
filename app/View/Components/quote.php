<?php

namespace App\View\Components;

use App\Models\Image;
use App\Models\Post;
use Closure;
use Request;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class quote extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $text,
        public string $name,
        public string $description,
        public string $img
    )
    {
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $url = Request::url();
        preg_match('([^\/]+$)', $url, $matches);
        $postId = $matches[0];
        $post = Post::all()->where('path', $postId)->first();
        // dd($post);        
        $imagesDB = Image::all()->where('post_id', $post->path);
        // dd($imagesDB);
        $i = 0;
        $images = [];
        foreach ($imagesDB as $image) {
            array_push($images, $image);
            $i++;
        }
        return view('components.quote', compact('images'));
    }
}
