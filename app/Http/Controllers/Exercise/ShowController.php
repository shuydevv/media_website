<?php

namespace App\Http\Controllers\Exercise;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Request;
// use Illuminate\Http\Request;

class ShowController extends Controller
{
    public function __invoke() {
        // dd($post);
        
        // $images = Image::all()->where('post_id', $exercise->path);
        // $i = 0;
        // $new_images = [];
        // foreach ($images as $image) {
        //     array_push($new_images, $image);
        //     $i++;
        // }
        $url = Request::url();
        preg_match('([^\/]+$)', $url, $matches);
        $postId = $matches[0]; //Получаем path из адресной строки
        // $currentPost = Exercise::where('path', $postId)->first();
        // текущий пост
        // $posts = Post::where('category_id', $currentPost->category_id)->where('path', '!=' , $postId)->paginate(4);
        // Передаются все посты, кроме текущего, чтобы не повторять его в рекомендациях 
        

        return view('exercise.show');
    }
}
