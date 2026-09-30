<?php

namespace App\Service;

use App\Models\Image;
use App\Models\Post;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Service\ImageCompressor;


class PostService
{
    public function __construct(private PostImageService $images)
    {
    }

    public function store($data) {
            if (isset($data['tag_ids'])) {
                $tagIds = $data['tag_ids'];
                unset($data['tag_ids']);
            } else $tagIds = [];

            if (isset($data['main_image'])) {
                $data['main_image'] = ImageCompressor::forContent()->storeAs($data['main_image'], 'images');
            }

            $latestPost = Post::latest()->first();
            if (isset($latestPost)) {
                $newPostId = $latestPost->toArray()['id'] + 1;
                if (!isset($data['path'])) {
                    $data['path'] = $newPostId;
                } 
            } else {
                $newPostId = 1;
                if (!isset($data['path'])) {
                    $data['path'] = $newPostId;
                } 
            }

            $data_without_multi = $data;
            unset($data_without_multi['multi_images']);
            $post = Post::firstOrCreate($data_without_multi);

            $this->images->apply($post, $data['multi_images'] ?? []);

            if (isset($tagIds)) {
                $post->tags()->attach($tagIds);
            }

        //     DB::commit();
        // } catch (Exception $exception) {
        //     abort(404);
        // }
    }

    public function update($data, $post) {
        // try {
        //     DB::beginTransaction();
            // if(isset($data['tag_ids'])) {
            //     $tagIds = $data['tag_ids'];
            //     unset($data['tag_ids']); 
            // } else {
            //     $tagIds = [];
            // }
    
            if (isset($data['tag_ids'])) {
                $tagIds = $data['tag_ids'];
                unset($data['tag_ids']);
            } else $tagIds = [];
            $post->tags()->sync($tagIds);

            if( array_key_exists('main_image', $data)) {
                $data['main_image'] = ImageCompressor::forContent()->storeAs($data['main_image'], 'images');
            }

            $data_without_multi = $data;
            unset($data_without_multi['multi_images'], $data_without_multi['delete_images']);
            $post->update($data_without_multi);

            // Картинки в посте: добавить новые, заменить одноимённые,
            // удалить отмеченные (раньше любая загрузка стирала все старые).
            $this->images->apply($post, $data['multi_images'] ?? [], $data['delete_images'] ?? []);


            // if (isset($tagIds)) {
            //     $post->tags()->attach($tagIds);
            // }
            if (isset($tagIds)) {
                $post->tags()->sync($tagIds);
            }



        //     DB::commit();

        // } catch (Exception $exception) {
        //     DB::rollBack();
        //     abort(500);
        // }

        return $post;
    }
}