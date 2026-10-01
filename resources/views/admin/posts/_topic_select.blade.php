{{-- Тема плана: по ней /plans группирует планы по разделам и темам. --}}
@php $selectedTopicId = (int) old('topic_id', $post?->topic_id); @endphp
<div class="mt-5">
    <label class="mr-5">Тема (для планов)</label>
    <select name="topic_id" class="@error('topic_id') border border-red-400 @enderror">
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
    </select>
    <p class="mt-1 text-sm text-zinc-500">Нужна постам с тегом «{{ \App\Models\Post::PLAN_TAG }}»: по ней план попадает в нужный раздел на странице /plans.</p>
</div>
