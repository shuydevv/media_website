<?php

namespace App\Service\Diagnostic;

use App\Models\DiagnosticAttempt;
use App\Models\TelegramContact;

/**
 * Диагностика человека — текстом для админа: карточка в его теме
 * (AdminTopics::sendCard) и строка в «🧪 Диагностики». Админ начинает
 * разговор с конкретики, ничего не переспрашивая (ТЗ, раздел 8.1).
 * HTML для Telegram (parse_mode HTML).
 */
final class DiagnosticSummary
{
    public static function card(TelegramContact $contact): string
    {
        $attempt = $contact->latestDiagnostic();

        $lines = [
            '<b>Новый диалог</b> · 🧪 диагностика',
            '<a href="tg://user?id='.$contact->telegram_user_id.'">'.e($contact->displayName()).'</a>'
                .($contact->username ? ' · @'.e($contact->username) : ''),
            'Метка: '.($contact->source ? '<code>'.e($contact->source).'</code>' : 'без метки'),
            'Старт: '.$contact->started_at->format('d.m.Y H:i'),
            '',
        ];
        $lines = array_merge($lines, $attempt ? self::attemptLines($attempt) : ['Диагностику не открывал']);
        $lines[] = '';
        $lines[] = '<a href="'.e(route('admin.telegram.contacts.show', $contact)).'">Карточка в админке</a>';
        $lines[] = '<i>Ответ в этой теме уйдёт человеку от бота как есть. Сообщение, начатое с //, — заметка, не отправляется.</i>';

        return implode("\n", $lines);
    }

    /** @return string[] */
    public static function attemptLines(DiagnosticAttempt $attempt): array
    {
        $grade = $attempt->grade ? (config("diagnostic.grades.{$attempt->grade}") ?? $attempt->grade) : 'не указан';
        $lines = ['Класс: '.e($grade), 'Уровень: '.e(self::trackTitle($attempt))];

        if (! $attempt->isFinished()) {
            $total = count($attempt->block_order);
            $lines[] = 'Проходит: блок '.min($attempt->block_index + 1, $total).' из '.$total
                .($attempt->last_answer_at ? ', последний ответ '.$attempt->last_answer_at->format('d.m H:i') : ', ещё не отвечал');

            return $lines;
        }

        $r = $attempt->result;
        if (! $attempt->isBasic()) {
            $lines[] = 'Цель: '.($attempt->target_score !== null ? $attempt->target_score : 'не знает');
        }
        if ($attempt->isBasic()) {
            // Начальный тест не измеряет балл — только сколько верных.
            $lines[] = '<b>Верно '.self::correctLine($attempt).'</b>';
        } else {
            $lines[] = '<b>Сейчас ~'.$r['low'].'–'.$r['high'].'</b>, потенциал '.$r['potential'].'+';
        }
        $lines[] = ($attempt->isBasic() ? 'Темы (верно из 3): ' : 'Блоки (0–3): ').implode(', ', array_map(
            fn ($key) => e(config("diagnostic.blocks.{$key}.title")).' '.($attempt->levels[$key] ?? 0),
            array_keys(config('diagnostic.blocks')),
        ));
        if ($r['weak'] !== [] && ! $attempt->isBasic()) {
            $lines[] = 'Точки роста: '.e(self::weakTitles($attempt));
        }
        if (($r['skipped'] ?? 0) > 0) {
            $lines[] = '«Не знаю»: '.$r['skipped'];
        }
        $lines[] = '«Хочешь улучшить?»: '.match ($attempt->improve) {
            'yes' => '<b>да</b>'.($attempt->improve_source === 'bot' ? ' (в чате)' : ' (на странице)'),
            'no' => 'пока нет',
            default => 'не ответил',
        };

        return $lines;
    }

    /** Строка в «🧪 Диагностики» — закончил тест. */
    public static function feedLine(TelegramContact $contact, DiagnosticAttempt $attempt): string
    {
        $r = $attempt->result;
        $grade = $attempt->grade ? (config("diagnostic.grades.{$attempt->grade}") ?? $attempt->grade) : '—';

        return '✅ '.e($contact->displayName())
            .($contact->username && ! str_starts_with($contact->displayName(), '@') ? ' @'.e($contact->username) : '')
            .' · '.e($grade).' · '.($attempt->isBasic() ? '🌱 верно '.self::correctLine($attempt) : '~'.$r['low'].'–'.$r['high'])
            .' · '.($contact->source ? '<code>'.e($contact->source).'</code>' : 'без метки')
            .' · <a href="'.e(route('admin.telegram.contacts.show', $contact)).'">карточка</a>';
    }

    /** «9 из 15» — у прохождений до подсчёта — из уровней блоков. */
    public static function correctLine(DiagnosticAttempt $attempt): string
    {
        $r = $attempt->result ?? [];

        return ($r['correct'] ?? array_sum($attempt->levels ?? [])).' из '.($r['total'] ?? count($attempt->block_order) * 3);
    }

    public static function trackTitle(DiagnosticAttempt $attempt): string
    {
        return $attempt->track ? (config("diagnostic.tracks.{$attempt->track}.title") ?? $attempt->track) : 'не выбран';
    }

    /** Темы с уровнем от $min до $max через запятую — для итога начального теста в бот. */
    public static function titlesByLevel(DiagnosticAttempt $attempt, int $min, int $max): string
    {
        $titles = [];
        foreach ($attempt->block_order as $key) {
            $level = $attempt->levels[$key] ?? 0;
            if ($level >= $min && $level <= $max) {
                $titles[] = config("diagnostic.blocks.{$key}.title");
            }
        }

        return implode(', ', $titles);
    }

    /** Точки роста через запятую — для текстов бота ({weak}). */
    public static function weakTitles(DiagnosticAttempt $attempt): string
    {
        $titles = array_map(fn ($key) => config("diagnostic.blocks.{$key}.title"), $attempt->result['weak'] ?? []);

        return $titles !== [] ? implode(', ', $titles) : 'серьёзных пробелов нет';
    }
}
