<?php

namespace App\Http\Requests\Admin\Post;

use App\Models\Image;
use App\Models\Post;
use App\Service\PostImageService;
use App\Support\PostContent\ContentRenderer;
use App\Support\PostContent\PostImages;
use Illuminate\Validation\Validator;

/**
 * Проверка контента поста при сохранении: все картинки, на которые
 * ссылается текст, должны быть среди картинок поста ПОСЛЕ этого сохранения
 * (с учётом удаляемых и загружаемых сейчас), а теги — разбираться и быть
 * из известного набора. Иначе на живой странице молча пропадёт картинка или
 * блок, и узнаём мы об этом от читателей.
 */
trait ValidatesPostContent
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Если сами файлы не прошли валидацию, итоговый набор картинок
            // посчитать нельзя — сначала пусть исправят файлы.
            if ($validator->errors()->hasAny(['content', 'multi_images', 'multi_images.*', 'delete_images', 'delete_images.*'])) {
                return;
            }

            foreach ($this->contentProblems() as $message) {
                $validator->errors()->add('content', $message);
            }
        });
    }

    public function messages(): array
    {
        return [
            'multi_images.*.uploaded' => 'Один из файлов не загрузился — скорее всего, он больше лимита сервера. Уменьшите картинку и попробуйте снова.',
            'multi_images.*.image' => 'Один из файлов не является картинкой.',
            'multi_images.*.max' => 'Картинка в посте должна быть не больше 5 МБ.',
            'main_image.uploaded' => 'Обложка не загрузилась — скорее всего, файл больше лимита сервера.',
            'main_image.max' => 'Обложка должна быть не больше 5 МБ.',
        ];
    }

    /** @return list<string> */
    private function contentProblems(): array
    {
        $post = $this->route('post');
        $existing = $post instanceof Post
            ? Image::where('post_id', $post->id)->orderBy('id')->get()
            : collect();

        $plan = app(PostImageService::class)->plan(
            $existing,
            array_values(array_filter((array) $this->file('multi_images', []))),
            (array) $this->input('delete_images', []),
        );
        $names = array_column($plan, 'original_name');

        $inspected = app(ContentRenderer::class)->inspect($this->input('content'));
        $problems = [];

        foreach ($inspected['malformed'] as $fragment) {
            $problems[] = "Не удалось разобрать тег: {$fragment}… Проверьте кавычки и закрывающее «/>».";
        }

        foreach ($inspected['unknown'] as $tag) {
            $problems[] = "Неизвестный компонент <x-{$tag}> — на странице он не выведется.";
        }

        $available = $names === []
            ? 'у поста нет картинок'
            : 'загружены: ' . implode(', ', array_map([PostImages::class, 'srcFor'], $names));

        foreach ($inspected['images'] as $ref) {
            $src = trim((string) $ref['src']);
            $index = trim((string) $ref['img']);

            if ($src !== '') {
                $found = collect($names)->contains(fn ($name) => PostImages::matches($src, $name));
                if (!$found) {
                    $problems[] = "Картинка «{$src}» в <x-{$ref['tag']}> не найдена ({$available}).";
                }
            } elseif ($index !== '') {
                if (!ctype_digit($index) || (int) $index >= count($names)) {
                    $problems[] = "Картинка img=\"{$index}\" в <x-{$ref['tag']}> не найдена (картинок у поста: "
                        . count($names) . ', нумерация с 0). Лучше указать src="имя файла".';
                }
            } elseif ($ref['tag'] === 'img') {
                $problems[] = 'В <x-img> не указана картинка — добавьте src="имя файла".';
            }
        }

        return $problems;
    }
}
