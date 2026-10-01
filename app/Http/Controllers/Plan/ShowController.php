<?php

namespace App\Http\Controllers\Plan;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Post;
use App\Service\PlanCatalog;
use App\Support\PostContent\ContentRenderer;

class ShowController extends Controller
{
    public function __invoke(Post $post, ContentRenderer $renderer, PlanCatalog $catalog)
    {
        // Обычная статья по адресу /plans/… — отправляем на её настоящий адрес.
        if (!$post->isPlan()) {
            return redirect()->route('post.show', ['post' => $post->path], 301);
        }

        $images = Image::where('post_id', $post->id)->orderBy('id')->get();
        $content = $renderer->render($post->content, $images, ['post_id' => $post->id]);

        // «Другие планы» — из того же раздела; если у плана нет темы — все.
        $otherPlans = $catalog->grouped($post->topic?->section_id, $post->id);

        return view('post.show', [
            'post' => $post,
            'content' => $content,
            'isPlan' => true,
            'otherPlans' => $otherPlans,
        ]);
    }
}
