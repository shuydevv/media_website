{{-- Плеер Kinescope с учётом просмотра. src у iframe намеренно пустой:
     его проставляет watch-tracker.blade.php — либо через IFrame Player API
     (тогда есть события плеера и просмотр считается), либо, если API не
     загрузился, обычной embed-ссылкой, как было раньше. Без JS видео
     показывает <noscript>.
     $kind — LessonView::KIND_* (recording | short | live). --}}
<iframe id="kinescope-{{ $kind }}"
        data-kinescope-id="{{ $videoId }}"
        data-kind="{{ $kind }}"
        allow="autoplay; fullscreen; picture-in-picture; encrypted-media; gyroscope; accelerometer; clipboard-write; screen-wake-lock;"
        frameborder="0" allowfullscreen
        style="position: absolute; width: 100%; height: 100%; top: 0; left: 0;"></iframe>
<noscript>
  <iframe src="https://kinescope.io/embed/{{ $videoId }}"
          allow="autoplay; fullscreen; picture-in-picture; encrypted-media; gyroscope; accelerometer; clipboard-write; screen-wake-lock;"
          frameborder="0" allowfullscreen
          style="position: absolute; width: 100%; height: 100%; top: 0; left: 0;"></iframe>
</noscript>
