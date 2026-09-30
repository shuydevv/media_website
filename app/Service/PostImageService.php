<?php

namespace App\Service;

use App\Models\Image;
use App\Models\Post;
use App\Support\PostContent\PostImages;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Картинки внутри поста (на которые ссылаются <x-img src="..."> и т.п.).
 *
 * Раньше любая загрузка файлов при сохранении удаляла ВСЕ картинки поста и
 * заливала только выбранные — дозагрузить одну картинку было нельзя, а
 * заменить одну значило перевыбрать все. Теперь:
 *   - новые файлы добавляются к существующим;
 *   - файл с тем же именем (без расширения, без учёта регистра), что у уже
 *     загруженной картинки, заменяет её — запись и её id сохраняются, так что
 *     старые img="N" тоже не съезжают;
 *   - удаляются только отмеченные галочкой.
 *
 * plan() — чистый расчёт итогового списка, его же использует валидация
 * контента при сохранении (ValidatesPostContent), apply() — применяет.
 */
class PostImageService
{
    /**
     * @param  Collection<int, Image>  $existing
     * @param  UploadedFile[]  $uploads
     * @param  array<int|string>  $deleteIds
     * @return list<array{image: ?Image, upload: ?UploadedFile, original_name: string}>
     *         итоговые картинки поста в порядке id (новые — в конце)
     */
    public function plan(Collection $existing, array $uploads, array $deleteIds): array
    {
        $deleteIds = array_map('intval', $deleteIds);
        $result = [];
        $byKey = [];

        foreach ($existing->sortBy('id') as $image) {
            if (in_array((int) $image->id, $deleteIds, true)) {
                continue;
            }
            $byKey[PostImages::key($image->original_name)] = count($result);
            $result[] = ['image' => $image, 'upload' => null, 'original_name' => (string) $image->original_name];
        }

        foreach ($uploads as $upload) {
            if (!$upload instanceof UploadedFile) {
                continue;
            }

            $name = $upload->getClientOriginalName();
            $key = PostImages::key($name);

            // Замена существующей картинки с тем же именем.
            if (isset($byKey[$key]) && $result[$byKey[$key]]['image'] !== null && $result[$byKey[$key]]['upload'] === null) {
                $result[$byKey[$key]]['upload'] = $upload;
                $result[$byKey[$key]]['original_name'] = $name;
                continue;
            }

            // Имя уже занято файлом из этой же загрузки — добавляем суффикс.
            if (isset($byKey[$key])) {
                $name = $this->uniqueName($name, $byKey);
                $key = PostImages::key($name);
            }

            $byKey[$key] = count($result);
            $result[] = ['image' => null, 'upload' => $upload, 'original_name' => $name];
        }

        return $result;
    }

    /**
     * @param  UploadedFile[]  $uploads
     * @param  array<int|string>  $deleteIds
     */
    public function apply(Post $post, array $uploads, array $deleteIds = []): void
    {
        $existing = Image::where('post_id', $post->id)->orderBy('id')->get();
        $plan = $this->plan($existing, $uploads, $deleteIds);

        $keptIds = collect($plan)->pluck('image')->filter()->pluck('id')->all();
        foreach ($existing as $image) {
            if (!in_array($image->id, $keptIds, true)) {
                Storage::disk('public')->delete($image->name);
                $image->delete();
            }
        }

        foreach ($plan as $item) {
            if ($item['upload'] === null) {
                continue;
            }

            $path = ImageCompressor::forContent()->storeAs($item['upload'], 'images');

            if ($item['image'] !== null) {
                $oldPath = $item['image']->name;
                $item['image']->update(['name' => $path, 'original_name' => $item['original_name']]);
                Storage::disk('public')->delete($oldPath);
            } else {
                Image::create([
                    'post_id' => $post->id,
                    'name' => $path,
                    'original_name' => $item['original_name'],
                ]);
            }
        }
    }

    private function uniqueName(string $name, array $takenKeys): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext = pathinfo($name, PATHINFO_EXTENSION);

        for ($i = 2; ; $i++) {
            $candidate = $base . '-' . $i . ($ext !== '' ? '.' . $ext : '');
            if (!isset($takenKeys[PostImages::key($candidate)])) {
                return $candidate;
            }
        }
    }
}
