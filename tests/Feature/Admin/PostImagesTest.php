<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Image;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Картинки внутри поста в админке: дозагрузка вместо "стереть всё и залить
 * заново", замена по имени файла, удаление галочкой и проверка при
 * сохранении, что все картинки из текста существуют.
 *
 * БД реальная (см. TaskControllerValidationTest) — созданное удаляется в
 * tearDown(). Файлы — в Storage::fake.
 */
class PostImagesTest extends TestCase
{
    private ?Post $post = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        if ($this->post) {
            Image::where('post_id', $this->post->id)->delete();
            $this->post->tags()->detach();
            $this->post->forceDelete();
        }
        parent::tearDown();
    }

    private function admin(): User
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->assertNotNull($admin, 'В БД нет ни одного администратора — тест не может авторизоваться.');

        return $admin;
    }

    /** Пост с картинками kalka-1.jpg, kalka-2.jpg (файлы реально лежат в fake-хранилище). */
    private function postWithImages(array $originalNames = ['kalka-1.jpg', 'kalka-2.jpg']): Post
    {
        $category = Category::first();
        $this->assertNotNull($category, 'В БД нет ни одной категории.');

        $this->post = Post::create([
            'title' => 'Тест картинок',
            'content' => '<x-text text="x" />',
            'category_id' => $category->id,
            'path' => 'test-post-images-' . uniqid(),
        ]);

        foreach ($originalNames as $i => $original) {
            $path = "images/test-{$i}.jpg";
            Storage::disk('public')->put($path, 'old');
            Image::create(['post_id' => $this->post->id, 'name' => $path, 'original_name' => $original]);
        }

        return $this->post;
    }

    private function update(Post $post, array $data)
    {
        return $this->actingAs($this->admin())
            ->from(route('admin.post.edit', $post->id))
            ->patch(route('admin.post.update', $post->id), $data + [
                'title' => $post->title,
                'category_id' => $post->category_id,
                'path' => $post->path,
            ]);
    }

    private function names(Post $post): array
    {
        return Image::where('post_id', $post->id)->orderBy('id')->pluck('original_name')->all();
    }

    /** @test */
    public function uploading_adds_images_without_deleting_existing_ones()
    {
        $post = $this->postWithImages();

        $this->update($post, [
            'content' => '<x-img src="kalka-1" description="" /><x-img src="kalka-3" description="" />',
            'multi_images' => [UploadedFile::fake()->image('kalka-3.jpg')],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['kalka-1.jpg', 'kalka-2.jpg', 'kalka-3.jpg'], $this->names($post));
        Storage::disk('public')->assertExists(['images/test-0.jpg', 'images/test-1.jpg']);
    }

    /** @test */
    public function uploading_a_file_with_the_same_name_replaces_that_image_only()
    {
        $post = $this->postWithImages();
        $before = Image::where('post_id', $post->id)->orderBy('id')->get();

        $this->update($post, [
            'content' => '<x-img src="kalka-2" description="" />',
            'multi_images' => [UploadedFile::fake()->image('KALKA-2.png')],
        ])->assertSessionHasNoErrors();

        $after = Image::where('post_id', $post->id)->orderBy('id')->get();
        $this->assertSame($before->pluck('id')->all(), $after->pluck('id')->all(), 'id картинок не должны меняться');
        $this->assertSame('images/test-0.jpg', $after[0]->name);
        $this->assertNotSame('images/test-1.jpg', $after[1]->name);
        Storage::disk('public')->assertMissing('images/test-1.jpg');
        Storage::disk('public')->assertExists($after[1]->name);
    }

    /** @test */
    public function checked_images_are_deleted_with_their_files()
    {
        $post = $this->postWithImages();
        $second = Image::where('post_id', $post->id)->orderBy('id')->get()[1];

        $this->update($post, [
            'content' => '<x-img src="kalka-1" description="" />',
            'delete_images' => [$second->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['kalka-1.jpg'], $this->names($post));
        Storage::disk('public')->assertMissing('images/test-1.jpg');
    }

    /** @test */
    public function saving_is_rejected_when_content_references_a_missing_image()
    {
        $post = $this->postWithImages();

        $this->update($post, [
            'content' => '<x-img src="kalka-9" description="" />',
        ])->assertSessionHasErrors('content');

        $this->assertSame('<x-text text="x" />', $post->fresh()->content);
    }

    /** @test */
    public function saving_is_rejected_when_a_referenced_image_is_being_deleted()
    {
        $post = $this->postWithImages();
        $first = Image::where('post_id', $post->id)->orderBy('id')->first();

        $this->update($post, [
            'content' => '<x-img src="kalka-1" description="" />',
            'delete_images' => [$first->id],
        ])->assertSessionHasErrors('content');

        $this->assertSame(['kalka-1.jpg', 'kalka-2.jpg'], $this->names($post));
    }

    /** @test */
    public function legacy_index_out_of_range_and_unknown_components_are_rejected()
    {
        $post = $this->postWithImages();

        $response = $this->update($post, [
            'content' => '<x-img img="5" description="" /><x-footer />',
        ]);

        $response->assertSessionHasErrors('content');
        $errors = session('errors')->get('content');
        $this->assertCount(2, $errors);
    }

    /** @test */
    public function edit_and_create_forms_render_with_image_tags_to_copy()
    {
        $post = $this->postWithImages();

        $this->actingAs($this->admin())->get(route('admin.post.edit', $post->id))
            ->assertOk()
            ->assertSee('data-copy-tag="&lt;x-img src=&quot;kalka-1&quot; description=&quot;&quot; /&gt;"', false)
            ->assertSee('name="delete_images[]"', false);

        $this->actingAs($this->admin())->get(route('admin.post.create'))
            ->assertOk()
            ->assertSee('name="multi_images[]"', false);
    }

    /** @test */
    public function saving_without_files_keeps_images_untouched()
    {
        $post = $this->postWithImages();

        $this->update($post, [
            'content' => '<x-img img="1" description="" />',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['kalka-1.jpg', 'kalka-2.jpg'], $this->names($post));
    }
}
