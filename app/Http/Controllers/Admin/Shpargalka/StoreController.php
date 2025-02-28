<?php

namespace App\Http\Controllers\Admin\Shpargalka;

use App\Http\Controllers\Admin\Shpargalka\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Shpargalka\StoreRequest;
use App\Models\Image;
use App\Models\Shpargalka;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoreController extends BaseController
{
    public function __invoke(StoreRequest $request) {
        $data = $request->validated();
        $this->service->store($data);
        

        return redirect()->route('admin.shpargalka.index');
    }
}

// class StoreController extends Controller
// {
//     public function __invoke(StoreRequest $request) {
//         $data = $request->validated();
//         if (isset($data['main_image'])) {
//             $data['main_image'] = Storage::disk('public')->put('/images', $data['main_image']);
//         }


//             $multi_images = [];

//             $data_without_multi = $data;

//             if (isset($data['multi_images'])) {
//                 foreach ($data['multi_images'] as $image) {
//                     $multi_images[] = Storage::disk('public')->put('/images', $image);
//                 }
//                 unset($data_without_multi['multi_images']);
//                 $post = Shpargalka::firstOrCreate($data_without_multi);            

//                 $i = 0;
//                 foreach ($data['multi_images'] as $image) {
//                     $image_name = $image->getClientOriginalName(); 
//                     $images = Image::firstOrCreate(
//                         ['post_id' => $post->id,
//                                     'name' => $multi_images[$i],
//                                     'original_name' => $image_name],
//                     );
//                     $i++;
//                 }
//             } else {
//                 unset($data_without_multi['multi_images']);
//                 $post = Shpargalka::firstOrCreate($data_without_multi);   
//             }
//         return redirect()->route('admin.shpargalka.index');
//     }
// }
