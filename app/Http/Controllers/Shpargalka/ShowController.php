<?php

namespace App\Http\Controllers\Shpargalka;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Image;
use App\Models\Post;
use App\Models\Shpargalka;
use Request;

class ShowController extends Controller
{

    public function __invoke(Post $post) {

        $url = Request::url();
        preg_match('([^\/]+$)', $url, $matches);
        $postId = $matches[0];
        $material = Shpargalka::where('id', $postId)->first();
        // dd($material->category_id);

        $images = Image::all()->where('shpargalka_id', $post->id); // ????? возможно тут ошибка
        $categories = Category::all();
        $i = 0;
        $new_images = [];
        foreach ($images as $image) {
            array_push($new_images, $image);
            $i++;
        }

        $postId = $matches[0]; //Получаем path из адресной строки
        // $currentPost = Category::where('path', $postId)->first();
        $posts = Shpargalka::where('category_id', $material->category_id)->where('id', '!=' , $postId)->paginate(4);
        // Передаются все посты, кроме текущего, чтобы не повторять его в рекомендациях 

        // $posts = Shpargalka::where('category_id', $material->category_id)->paginate(4);

        return view('shpargalka.show', compact('material', 'post', 'posts', 'images', 'new_images', 'categories'));
    }
}
