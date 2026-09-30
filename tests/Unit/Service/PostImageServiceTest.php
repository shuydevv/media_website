<?php

namespace Tests\Unit\Service;

use App\Models\Image;
use App\Service\PostImageService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PostImageServiceTest extends TestCase
{
    private function image(int $id, string $original): Image
    {
        $image = new Image(['name' => "images/{$id}.jpg", 'original_name' => $original]);
        $image->id = $id;

        return $image;
    }

    private function plannedNames(array $existing, array $uploads, array $deleteIds = []): array
    {
        $plan = (new PostImageService())->plan(
            collect($existing),
            array_map(fn ($name) => UploadedFile::fake()->create($name, 1, 'image/jpeg'), $uploads),
            $deleteIds,
        );

        return array_column($plan, 'original_name');
    }

    /** @test */
    public function new_files_are_appended_after_existing_ones_in_id_order()
    {
        $names = $this->plannedNames([$this->image(2, 'b.jpg'), $this->image(1, 'a.jpg')], ['c.jpg']);

        $this->assertSame(['a.jpg', 'b.jpg', 'c.jpg'], $names);
    }

    /** @test */
    public function same_name_replaces_in_place_regardless_of_extension_and_case()
    {
        $names = $this->plannedNames([$this->image(1, 'a.jpg'), $this->image(2, 'b.jpg')], ['A.PNG']);

        $this->assertSame(['A.PNG', 'b.jpg'], $names);
    }

    /** @test */
    public function duplicate_names_within_one_upload_get_a_suffix()
    {
        $names = $this->plannedNames([], ['a.jpg', 'a.png', 'a.jpg']);

        $this->assertSame(['a.jpg', 'a-2.png', 'a-3.jpg'], $names);
    }

    /** @test */
    public function deleted_images_are_left_out_and_their_name_can_be_reused()
    {
        $names = $this->plannedNames([$this->image(1, 'a.jpg'), $this->image(2, 'b.jpg')], ['a.jpg'], ['1']);

        $this->assertSame(['b.jpg', 'a.jpg'], $names);
    }
}
