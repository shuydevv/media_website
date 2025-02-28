<?php

namespace App\Service;

use App\Models\Image;
use App\Models\Shpargalka;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class ShpargalkaService 
{
    public function store($data) {
            // if (isset($data['tag_ids'])) {
            //     $tagIds = $data['tag_ids'];
            //     unset($data['tag_ids']);
            // } else $tagIds = [];

            if (isset($data['main_image'])) {
                $data['main_image'] = Storage::disk('public')->put('/images', $data['main_image']);
            }
            
            $latestPost = Shpargalka::latest()->first();
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
            // dd($data);
            $multi_images = [];

            $data_without_multi = $data;

            if (isset($data['multi_images'])) {
                foreach ($data['multi_images'] as $image) {
                    // dd($image);
                    $multi_images[] = Storage::disk('public')->put('/images', $image);
                }
                unset($data_without_multi['multi_images']);
                $post = Shpargalka::firstOrCreate($data_without_multi);
                // dd($post);            

                $i = 0;
                foreach ($data['multi_images'] as $image) {
                    $image_name = $image->getClientOriginalName(); 
                    // dd($image_name);
                    $images = Image::firstOrCreate(
                        ['shpargalka_id' => $post->path,
                                    'name' => $multi_images[$i],
                                    'original_name' => $image_name],
                    );
                    $i++;
                }
            } else {
                unset($data_without_multi['multi_images']);
                $post = Shpargalka::firstOrCreate($data_without_multi);   
            }
            
            
            
            // dd($data_without_multi);

            // $data['multi_images'][0]->getClientOriginalName()

            // if (isset($tagIds)) {
            //     $post->tags()->attach($tagIds);
            // }

        //     DB::commit();
        // } catch (Exception $exception) {
        //     abort(404);
        // }
    }

    public function update($data, $post) {
        // dd($data);
        // try {
        //     DB::beginTransaction();
            // if(isset($data['tag_ids'])) {
            //     $tagIds = $data['tag_ids'];
            //     unset($data['tag_ids']); 
            // } else {
            //     $tagIds = [];
            // }
    
            // if (isset($data['tag_ids'])) {
            //     $tagIds = $data['tag_ids'];
            //     unset($data['tag_ids']);
            // } else $tagIds = [];
            // $post->tags()->sync($tagIds);

            if( array_key_exists('main_image', $data)) {
                $data['main_image'] = Storage::disk('public')->put('/images', $data['main_image']);
            }

            $multi_images = [];
            if( array_key_exists('multi_images', $data)) {
                foreach ($data['multi_images'] as $image) {
                    $multi_images[] = Storage::disk('public')->put('/images', $image);
                }
            }

            $data_without_multi = $data;
            unset($data_without_multi['multi_images']);
            $post->update($data_without_multi);

            // dd($data['multi_images']);
            $toDelete = Image::where('shpargalka_id', $post->path)->get();
            
            if (isset($data['multi_images'])) {
                $arrayLenght = count($data['multi_images']);
                if ($arrayLenght > 0) {
                    foreach ($toDelete as $item) {
                        Storage::disk('public')->delete($item->name);
                    }
                    $deleted = Image::where('shpargalka_id', $post->path)->delete();
                
                    $i = 0;
                    foreach ($data['multi_images'] as $image) { 
                        $image_name = $image->getClientOriginalName(); 
                        $images = Image::firstOrCreate(
                            ['shpargalka_id' => $post->path,
                                        'name' => $multi_images[$i],
                                        'original_name' => $image_name],
                        );
                        $i++;
                    }
                }
            }
            // $deletedFromDB = 


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