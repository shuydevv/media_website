<?php

namespace App\View\Components;

use App\Http\Controllers\Admin\Post\ShowController;
use App\Models\Image;
use App\Models\Post;
use Closure;
use Illuminate\Contracts\View\View;
use Request;
use Illuminate\View\Component;

class img extends Component
{
    // public $posta;
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $img,
        public string $description,
        // public string $posta,
    )
    {
    //     $this->posta = $posta;
    }

    /**
     * Get the view / contents that represent the component.
     */
    

    
    public function render():View|Closure|string
    {
        // dd(1);

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
        return view('components.img', compact('images'));
    }
}
