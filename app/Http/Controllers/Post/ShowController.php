<?php

namespace App\Http\Controllers\Post;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Image;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Request;
// use Illuminate\Http\Request;

class ShowController extends Controller
{

    // public function getPost(Post $post) {
    //     return $post;
    // }
    public function __invoke(Post $post) {
        // dd($post);

        
        $images = Image::all()->where('post_id', $post->path);
        $i = 0;
        $new_images = [];
        foreach ($images as $image) {
            array_push($new_images, $image);
            $i++;
        }
        $url = Request::url();
        preg_match('([^\/]+$)', $url, $matches);
        $postId = $matches[0]; //Получаем path из адресной строки
        $currentPost = Post::where('path', $postId)->first(); // текущий пост
        
        $categories = Category::all();
        $category_plan = "wrong";
        foreach ($categories as $category) {
            if ($category->title == "Планы по обществознанию") {
                $category_plan = $category->id;
            } 
        }
        // dd($category_plan);
        
        $posts = Post::where('category_id', $currentPost->category_id)->where('path', '!=' , $postId)->where('category_id', '!=', $category_plan)->paginate(4);
        // Передаются все посты, кроме текущего, чтобы не повторять его в рекомендациях 
        
        
        return view('post.show', compact('post', 'posts', 'images', 'new_images'));
    }
}
