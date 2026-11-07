"""Сборка базы тренажёра «Верные суждения» из выгрузки ege-hub.

Запуск (из main-app):
    python -I tools/statements/build_from_egehub.py ../data_ege_obshhestvoznanie/all.json

Пишет:
    docs/statements/statements.json  — база суждений (её дальше правит автор)
    docs/statements/Отчёт сборки.md  — что не прошло проверку и почему

Берём задания «Выберите верные суждения…»: каждое из 4–6 пронумерованных
суждений становится карточкой. Верно/неверно — по ключу ответа задания
(цифра есть в ответе — суждение верное). Пояснение — пункт разбора с тем же
номером, без вердикта в начале («Да, верно, …»): вердикт экран показывает сам.

Статусы (ученику выдаются только active):
    active          — есть ключ, есть пояснение, разбор не спорит с ключом;
    no_explanation  — в разборе только «да/нет, верно» — пояснение допишем позже
                      (ИИ-черновик + вычитка автора, решение от 06.11.2026);
    check           — разбор говорит обратное ключу — проверить руками;
    conflict        — одно и то же суждение в разных заданиях с разным ответом.

ВНИМАНИЕ: повторная сборка перезапишет statements.json. После того как автор
начал править файл, сборку не запускать — правки делаются в самом JSON.
"""

import json
import re
import sys
from collections import Counter, defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUT_JSON = ROOT / 'docs' / 'statements' / 'statements.json'
OUT_REPORT = ROOT / 'docs' / 'statements' / 'Отчёт сборки.md'

BLOCKS = {
    'celovek-i-obshhestvo-po-ege': 'society',
    'ekonomika-po-ege': 'economy',
    'socialnye-otnoseniia-po-ege': 'social',
    'politika-po-ege': 'politics',
    'pravo-po-ege': 'law',
}

ITEM_RE = re.compile(r'^\s*(\d)\s*[.)]\s*(.+?)\s*$', re.M)
JUDGMENT_RE = re.compile(r'верн\w*\s+суждени', re.I)
ABOUT_RE = re.compile(r'суждени[яйе]\s+(обо|об|о)\s+(.+?)(?:\s+и\s+запишите|[.?:]|$)', re.I)
# Начало вердикта внутри пункта разбора: «… – Да,верно, …», «… –Нет, неверно».
# Ищем именно его, а не первое тире: тире бывает и в самом суждении
# («Цель Банка России – защита рубля»).
VERDICT_MARK = re.compile(r'\s*[—–-]\s*(?=(?:да|нет)\b|(?:не\s*)?верно\b|неверно\b)', re.I)
# Вердикт в начале пункта разбора: «— Да, верно, …», «Нет,неверно.», «верно:»
VERDICT_RE = re.compile(
    # \b обязательны: без них из «дано верное определение» вырезалось «да».
    r'^\s*(?:(?:да|нет)\b\s*[,.!]?\s*)?(?:(?:не\s*)?верно\b|неверно\b|ошибочно\b)?\s*[,.:;!—–-]*\s*',
    re.I,
)
DASH_SPLIT = re.compile(r'\s+[—–-]\s+|\s*[—–]\s*')


def clean(text):
    if text is None:
        return ''
    text = text.replace('​', '').replace('﻿', '')
    text = text.replace(' ', ' ').replace(' ', ' ').replace('&nbsp;', ' ')
    # Перенос из PDF: «распре- деление» → «распределение».
    text = re.sub(r'([а-яё])- ([а-яё])', r'\1\2', text)
    text = re.sub(r'[ \t]+', ' ', text)
    return text.strip()


def norm(text):
    return re.sub(r'[^0-9a-zа-я]', '', text.lower().replace('ё', 'е'))


def clean_statement(text):
    """Суждение без хвостов источника: подсказок с ответом в скобках
    («(подсказка: нет, физическими тоже…)») и заглушки редактора."""
    text = re.sub(r'\s*\((?:подсказка|да|нет)\b[^)]*\)?\s*$', '', text, flags=re.I)
    text = re.sub(r'\s*Вставьте текст задания\.?', '', text, flags=re.I)
    return text.strip()


def finish_sentence(text):
    text = text.rstrip(' ;,')
    if text and text[-1] not in '.!?…»"':
        text += '.'
    return text


def capitalize(text):
    return text[:1].upper() + text[1:] if text else text


def stance(raw):
    """Что говорит разбор о суждении: True / False / None (не понять)."""
    head = raw[:40].lower()
    if re.search(r'\bнет\b|неверн|не\s+верн|ошибочн|ложн', head):
        return False
    if re.search(r'\bда\b|верн|правильн', head):
        return True
    return None


def explanation_for(statement, decision_items):
    """Пункт разбора к суждению: (пояснение | None, позиция разбора | None)."""
    if statement['n'] not in decision_items:
        return None, None
    raw = decision_items[statement['n']]

    # Разбор обычно повторяет суждение, затем тире и вердикт с причиной.
    mark = VERDICT_MARK.search(raw)
    if mark:
        head, tail = raw[:mark.start()], raw[mark.end():]
    else:
        parts = DASH_SPLIT.split(raw, maxsplit=1)
        head, tail = parts if len(parts) == 2 else ('', raw)
    # Убедимся, что пункт разбора — про то же суждение (иначе нумерация
    # разбора могла уехать, и пояснение будет к другому пункту).
    if norm(head)[:20] and norm(head)[:20] != norm(statement['text'])[:20]:
        return None, None
    if not head and norm(raw).startswith(norm(statement['text'])[:30]):
        return None, None  # пункт разбора — просто повтор суждения

    said = stance(tail)
    # Вердикт бывает сдвоенным: «Нет,неверно, неверно постоянные издержки…».
    text = tail
    for _ in range(3):
        text = VERDICT_RE.sub('', text, count=1).strip()
    text = capitalize(finish_sentence(clean(text)))
    if len(norm(text)) < 15 or norm(text)[:40] == norm(statement['text'])[:40]:
        return None, said
    return text, said


def build(source):
    data = json.loads(Path(source).read_text(encoding='utf-8'))
    statements = []
    skipped = Counter()

    for task in data['tasks']:
        text = clean(task.get('text'))
        if not JUDGMENT_RE.search(text):
            continue
        answer = re.sub(r'\D', '', task.get('answer') or '')
        if not answer:
            skipped['нет ключа ответа'] += 1
            continue
        block = BLOCKS.get(task['section']['slugCode'])
        if block is None:
            skipped['неизвестный раздел'] += 1
            continue

        items = [{'n': n, 'text': clean_statement(clean(t))} for n, t in ITEM_RE.findall(text)]
        if len(items) < 4:
            skipped['меньше 4 суждений в условии'] += 1
            continue

        about_match = ABOUT_RE.search(text)
        about = clean(about_match.group(1) + ' ' + about_match.group(2)) if about_match else None

        decision_items = {n: clean(t) for n, t in ITEM_RE.findall(clean(task.get('decision')))}
        line = int(task['line']['name'].split()[-1])

        for item in items:
            is_true = item['n'] in answer
            explanation, said = explanation_for(item, decision_items)
            status = 'active'
            issue = None
            if said is not None and said != is_true:
                status, issue = 'check', 'разбор говорит «{}», ключ — «{}»'.format(
                    'верно' if said else 'неверно', 'верно' if is_true else 'неверно')
            elif explanation is None:
                status = 'no_explanation'

            statements.append({
                'id': 'eh-{}-{}'.format(task['id'], item['n']),
                'block': block,
                'about': about,
                'text': capitalize(finish_sentence(item['text'])),
                'is_true': is_true,
                'explanation': explanation,
                'correction': None,
                'status': status,
                'issue': issue,
                'source': {
                    'task': task['id'],
                    'line': line,
                    'theme': task['theme']['name'],
                },
            })

    return dedupe(statements), skipped


def dedupe(statements):
    """Одинаковые суждения из разных заданий склеиваем в одно.

    Остаётся то, у которого есть пояснение (и оно не на проверке); ids
    остальных — в also. Если ответы расходятся — все копии в conflict:
    суждение могло зависеть от контекста задания, решает автор.
    """
    groups = defaultdict(list)
    for s in statements:
        groups[norm(s['text'])].append(s)

    rank = {'active': 0, 'no_explanation': 1, 'check': 2, 'conflict': 3}
    result = []
    for copies in groups.values():
        if len({s['is_true'] for s in copies}) > 1:
            for s in copies:
                s['status'] = 'conflict'
                s['issue'] = 'то же суждение в других заданиях с другим ответом: ' + ', '.join(
                    o['id'] for o in copies if o is not s)
                result.append(s)
            continue
        copies.sort(key=lambda s: (rank[s['status']], -len(s['explanation'] or '')))
        keep = copies[0]
        if len(copies) > 1:
            keep['also'] = [s['id'] for s in copies[1:]]
        result.append(keep)

    result.sort(key=lambda s: (s['source']['task'], s['id']))
    return result


def report(statements, skipped, source):
    by_status = Counter(s['status'] for s in statements)
    by_block = defaultdict(Counter)
    for s in statements:
        by_block[s['block']][s['status']] += 1
    true_share = Counter(s['is_true'] for s in statements if s['status'] == 'active')

    lines = [
        '# Верные суждения — отчёт сборки',
        '',
        'Источник: `{}`. Собрано скриптом `tools/statements/build_from_egehub.py`.'.format(Path(source).name),
        '',
        '| Статус | Суждений | Что значит |',
        '|---|---|---|',
        '| active | {} | идёт в тренажёр |'.format(by_status['active']),
        '| no_explanation | {} | нет пояснения — допишем позже |'.format(by_status['no_explanation']),
        '| check | {} | разбор спорит с ключом — проверить |'.format(by_status['check']),
        '| conflict | {} | одно суждение, разные ответы в разных заданиях |'.format(by_status['conflict']),
        '| **всего** | **{}** | |'.format(len(statements)),
        '',
        'Среди active верных — {}, неверных — {}.'.format(true_share[True], true_share[False]),
        '',
        '| Блок | active | no_explanation | check | conflict |',
        '|---|---|---|---|---|',
    ]
    for block in BLOCKS.values():
        c = by_block[block]
        lines.append('| {} | {} | {} | {} | {} |'.format(block, c['active'], c['no_explanation'], c['check'], c['conflict']))

    if skipped:
        lines += ['', 'Пропущено заданий: ' + ', '.join('{} — {}'.format(k, v) for k, v in skipped.items()) + '.']

    for status, title in (('check', 'Разбор спорит с ключом'), ('conflict', 'Конфликты')):
        rows = [s for s in statements if s['status'] == status]
        if not rows:
            continue
        lines += ['', '## {} ({})'.format(title, len(rows)), '']
        for s in rows:
            lines.append('- `{}` — {} {}'.format(s['id'], s['text'][:120], '— ' + s['issue'] if s['issue'] else ''))

    return '\n'.join(lines) + '\n'


def main():
    if len(sys.argv) != 2:
        print(__doc__)
        sys.exit(1)
    source = sys.argv[1]
    statements, skipped = build(source)

    OUT_JSON.parent.mkdir(parents=True, exist_ok=True)
    payload = {
        '_comment': 'База тренажёра «Верные суждения». Правит автор; заливка — php artisan statements:import, '
                    'проверка — php artisan statements:import --dry-run. Ученику выдаются только status = active. '
                    'Статусы и поля — docs/statements/Верные суждения — механика.md.',
        'statements': statements,
    }
    OUT_JSON.write_text(json.dumps(payload, ensure_ascii=False, indent=1) + '\n', encoding='utf-8')
    OUT_REPORT.write_text(report(statements, skipped, source), encoding='utf-8')
    print('statements:', len(statements), dict(Counter(s['status'] for s in statements)))


if __name__ == '__main__':
    main()
