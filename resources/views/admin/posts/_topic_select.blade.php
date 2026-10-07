{{-- Тема плана: по ней /plans группирует планы по разделам и темам. --}}
@php $selectedTopicId = (int) old('topic_id', $post?->topic_id); @endphp
<x-ui.select name="topic_id" label="Тема" note="для планов"
             :hint="'Нужна постам с тегом «'.\App\Models\Post::PLAN_TAG.'»: по ней план попадает в нужный раздел на странице /plans.'">
    <option value="">— без темы —</option>
    @foreach ($sections as $section)
        @if ($section->topics->isNotEmpty())
            <optgroup label="{{ $section->title }}">
                @foreach ($section->topics as $topic)
                    <option value="{{ $topic->id }}" @selected($selectedTopicId === $topic->id)>{{ $topic->title }}</option>
                @endforeach
            </optgroup>
        @endif
    @endforeach
</x-ui.select>
