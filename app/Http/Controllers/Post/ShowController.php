<?php

namespace App\Http\Controllers\Post;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Post;
use App\Support\PostContent\ContentRenderer;

class ShowController extends Controller
{
    public function __invoke(Post $post, ContentRenderer $renderer)
    {
        // Планы переехали на /plans/{path} — старые ссылки (в т.ч. из поиска)
        // ведём туда постоянным редиректом.
        if ($post->isPlan()) {
            return redirect()->route('plan.show', ['post' => $post->path], 301);
        }

        // Раньше "текущий пост" для похожих статей заново находился регуляркой
        // по Request::url() вместо использования $post, который Laravel уже
        // резолвил через route model binding — ломалось на любом отклонении
        // URL (конечный слэш и т.п.) и требовало лишнего запроса в БД.
        $images = Image::where('post_id', $post->id)->orderBy('id')->get();
        $content = $renderer->render($post->content, $images, ['post_id' => $post->id]);

        $posts = Post::query()
            ->articles()
            ->with(['tags', 'category'])
            ->where('category_id', $post->category_id)
            ->where('path', '!=', $post->path)
            ->paginate(4);

        return view('post.show', [
            'post' => $post,
            'posts' => $posts,
            'content' => $content,
            'isPlan' => false,
        ]);
    }
}
