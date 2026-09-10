@foreach($activeAnnouncements ?? [] as $announcement)
    <div id="announcement-banner-{{ $announcement->id }}" class="w-full bg-sky-50 text-sky-800">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-3 text-sm">
            <span>{{ $announcement->message }}</span>
            <button type="button"
                    hx-post="{{ route('student.announcements.dismiss', $announcement) }}"
                    hx-target="#announcement-banner-{{ $announcement->id }}"
                    hx-swap="outerHTML"
                    class="text-sky-600 hover:text-sky-900 text-base leading-none shrink-0"
                    aria-label="Закрыть">
                <x-icon name="x-close" width="16" height="16" />
            </button>
        </div>
    </div>
@endforeach
