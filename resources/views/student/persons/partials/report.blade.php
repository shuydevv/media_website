{{-- «Сообщить об ошибке» — как в тренажёрах терминов и дат. --}}
<details class="{{ ($inline ?? false) ? 'prs-report' : 'mt-5' }} text-right">
  <summary class="inline-block cursor-pointer text-xs text-zinc-400 hover:text-zinc-600 list-none">Сообщить об ошибке</summary>
  <form class="mt-2 text-left" method="POST" action="{{ route('student.persons.session.report', $session) }}"
        hx-post="{{ route('student.persons.session.report', $session) }}" hx-swap="none"
        hx-on::after-request="if (event.detail.successful) this.closest('details').removeAttribute('open')">
    @csrf
    <textarea name="comment" rows="2" maxlength="500" placeholder="Что не так? Можно не писать — вопрос уже прикреплён."
              class="w-full border border-zinc-300 rounded-xl px-3 py-2 text-sm"></textarea>
    <x-ui.button type="submit" variant="game" size="sm" class="mt-2 text-sm">Отправить</x-ui.button>
  </form>
</details>
