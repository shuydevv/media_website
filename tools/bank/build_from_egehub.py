"""Сборка банка заданий для станции «Задания» из выгрузки ege-hub.

    python -I tools/bank/build_from_egehub.py ../data_ege_obshhestvoznanie/all.json

Читает выгрузку и правки автора (docs/bank/rewrites.json), пишет
docs/bank/tasks.json (его заливает `php artisan bank:import`) и
docs/bank/Отчёт сборки.md, копирует нужные картинки в docs/bank/images/.

В отличие от «Верных суждений», пересобирать можно сколько угодно:
правки условий живут в rewrites.json, а правки автора в админке импорт
не перетирает (см. docs/bank/Задания — механика.md).
"""
import hashlib
import json
import re
import shutil
import sys
from collections import Counter, defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUT_JSON = ROOT / 'docs' / 'bank' / 'tasks.json'
OUT_REPORT = ROOT / 'docs' / 'bank' / 'Отчёт сборки.md'
REWRITES = ROOT / 'docs' / 'bank' / 'rewrites.json'
# Картинки лежат в репозитории рядом с JSON; bank:import копирует их в
# публичное хранилище (storage/app/public/bank/), как у остальных заданий.
IMG_DIR = ROOT / 'docs' / 'bank' / 'images'
IMG_URL = ''

BLOCKS = {
    'celovek-i-obshhestvo-po-ege': 'society',
    'ekonomika-po-ege': 'economy',
    'socialnye-otnoseniia-po-ege': 'social',
    'politika-po-ege': 'politics',
    'pravo-po-ege': 'law',
}

LETTERS = 'АБВГДЕЖ'
READ_TEXT = re.compile(r'^\s*Прочитайте текст и выполните задани[ея]\.?\s*', re.I)
LINE_PREFIX = re.compile(r'^\s*\d{1,2}\.\s+(?=[А-ЯЁA-Z])')  # «19. Автор…»
SOURCE_TAIL = re.compile(r'\n?\s*Источник\s*:\s*[^\n]*\s*$', re.I)
ITEM_LINE = re.compile(r'^\s*(\d)\s*[.)]\s+(.+?)\s*$')
REFS_TEXT = re.compile(r'\bавтор|в тексте|текст[еау]?\b', re.I)
# Начало вопросов после текста в 17/18: в выгрузке текст и вопросы — одним
# полем, вопросы — последние абзацы.
QSTART = re.compile(
    r'^(В тексте|Используя|Опираясь|На основе|Каки[ем]|Каков|Какой|Какая|Каким|Как |Что |Чем |'
    r'В ч[её]м|В како|Почему|Назовите|Укажите|Приведите|Объясните|Сформулируйте|Перечислите|'
    r'С какими|О ч[её]м|Для чего|Кто |При как|Каково|Сколько )'
)


def split_passage(text):
    """17/18 без отдельного поля вопроса → (текст, вопросы) или None."""
    lines = text.split('\n')
    # 0) бывает, что вопросы стоят перед текстом;
    first = LINE_PREFIX.sub('', lines[0].strip()) if lines else ''
    rest = '\n'.join(lines[1:]).strip()
    if QSTART.match(first) and first.count('?') >= 2 and len(rest) >= 400:
        return rest, first
    start = None
    # 1) после подписи автора текста: «(По А. Г. Спиркину)», «(М. Фридмен)»;
    for i in range(len(lines) - 1, max(-1, len(lines) - 8), -1):
        line = lines[i].strip()
        if line.startswith('(') and line.endswith(')') and len(line) < 120 and i + 1 < len(lines):
            start = i + 1
            break
    # 2) задание 18 начинается с «В тексте упомянуты…»;
    if start is None:
        for i in range(len(lines) - 1, max(-1, len(lines) - 8), -1):
            if lines[i].strip().startswith('В тексте упомянуты'):
                start = i
                break
    # 3) вопросы 17 — обычно одной последней строкой. Риторические вопросы
    #    внутри текста («Каким образом мы усваиваем роли?») так не попадут.
    if start is None and lines and QSTART.match(LINE_PREFIX.sub('', lines[-1].strip())):
        start = len(lines) - 1
    if start is None:
        return None
    passage = '\n'.join(lines[:start]).strip()
    if len(passage) < 400:
        return None
    return passage, LINE_PREFIX.sub('', '\n'.join(lines[start:]).strip())


def clean(text):
    if text is None:
        return ''
    text = text.replace('​', '').replace('﻿', '').replace('⁠', '')
    text = text.replace(' ', ' ').replace(' ', ' ').replace('&nbsp;', ' ')
    text = text.replace('\r\n', '\n').replace('\r', '\n')
    # Перенос из PDF: «распре- деление» → «распределение».
    text = re.sub(r'([а-яё])- ([а-яё])', r'\1\2', text)
    text = re.sub(r'[ \t]+', ' ', text)
    text = re.sub(r' *\n *', '\n', text)
    text = re.sub(r'\n{3,}', '\n\n', text)
    return text.strip()


def norm(text):
    return re.sub(r'[^0-9a-zа-я]', '', (text or '').lower().replace('ё', 'е'))


def line_of(task):
    return int(task['line']['name'].split()[-1])


TAIL_START = re.compile(r'\n\s*(?=(Найдите|Запишите|Выберите|Укажите|Источник|Ответ)\b)')


def split_options(text):
    """Условие с пронумерованным списком → (вступление, варианты, хвост).

    Варианты бывают столбиком («1) …» / «1.  …» с новой строки), в одну
    строку («1) прибыль; 2) рента; …») и вперемешку («5) … 6) …» в одной
    строке). Ищем метки 1, 2, 3… по порядку; хвост — инструкция после
    списка («Найдите два термина…»). Если список не распознан — None.
    """
    marks, pos = [], 0
    for k in range(1, 10):
        m = re.compile(rf'(?:^|(?<=[\s;:]))({k})\s*[.)](?:\s+|(?=[А-ЯЁа-яё]))', re.M).search(text, pos)
        if not m:
            break
        marks.append(m)
        pos = m.end()
    if len(marks) < 3:
        return None
    intro = text[:marks[0].start()].strip()
    items = [text[a.end():b.start()] for a, b in zip(marks, marks[1:])]
    last = text[marks[-1].end():]
    cut = TAIL_START.search(last)
    if not cut and '\n\n' in last:
        cut = re.search(r'\n\n', last)
    tail = ''
    if cut:
        last, tail = last[:cut.start()], last[cut.start():].strip()
    items.append(last)
    items = [re.sub(r'\s+', ' ', i).strip().rstrip(';').strip() for i in items]
    # «капитал.» → «капитал»: точку снимаем только у коротких вариантов-слов.
    items = [i[:-1] if i.endswith('.') and len(i) < 40 and i.count('.') == 1 else i for i in items]
    if any(not i for i in items):
        return None
    return intro, items, tail


def compliance(task):
    c = task.get('complience') or {}
    rows = c.get('rows') or []
    left = [clean(r['first']) for r in rows if r.get('first')]
    right_rows = sorted((r for r in rows if r.get('second') and r.get('number')), key=lambda r: r['number'])
    right = [clean(r['second']) for r in right_rows]
    pairs = dict(re.findall(r'([А-Я])\s*(\d)', task.get('answer') or ''))
    answer = ''.join(pairs.get(LETTERS[i], '') for i in range(len(left)))
    ok = left and right and len(answer) == len(left)
    return {
        'left': left, 'right': right, 'answer': answer if ok else '',
        'left_title': clean(c.get('firstLabel')).capitalize() or None,
        'right_title': clean(c.get('secondLabel')).capitalize() or None,
    }


def clean_decision(text):
    text = clean(text)
    text = SOURCE_TAIL.sub('', text).strip()
    return text


def build(source):
    data = json.loads(Path(source).read_text(encoding='utf-8'))
    rw = json.loads(REWRITES.read_text(encoding='utf-8')) if REWRITES.exists() else {}
    rewrites = {r['id']: r for r in rw.get('rewrites', [])}
    links = {r['id']: r['text_from'] for r in rw.get('text_links', [])}
    dup_of = {r['id']: r['same_as'] for r in rw.get('duplicates', [])}
    by_id = {t['id']: t for t in data['tasks']}
    src_dir = Path(source).resolve().parent

    out, issues = [], Counter()
    for t in sorted(data['tasks'], key=lambda t: t['id']):
        if t['id'] in dup_of:
            continue
        number = line_of(t)
        part = 2 if number >= 17 else 1
        form = t['form']['slugCode']
        text = clean(t['text'])
        body = clean(t['body'])
        row = {
            'id': f"eh-{t['id']}",
            'ext': t['id'],
            'number': number,
            'block': BLOCKS.get(t['section']['slugCode']),
            'theme': clean(t['theme']['name']).rstrip('.') if t.get('theme') else None,
            'max': t.get('maximumPoints') or 1,
            'passage': None, 'options': None, 'matches': None,
            'left_title': None, 'right_title': None,
            'explanation': None, 'image': None, 'group': None,
            'status': 'active', 'issue': None, 'also': [],
        }
        if t.get('images'):
            row['image'] = IMG_URL + Path(t['images'][0]).name

        rewrite = rewrites.get(t['id'])
        decision = clean_decision(rewrite['decision'] if rewrite and rewrite.get('decision') else t.get('decision'))

        if part == 1:
            if form == 'COMPLIANCE':
                c = compliance(t)
                row.update(type='matching', question=text, matches={'left': c['left'], 'right': c['right']},
                           left_title=c['left_title'], right_title=c['right_title'], answer=c['answer'])
            else:
                parsed = split_options(text)
                if parsed:
                    intro, items, tail = parsed
                    row.update(question='\n'.join(x for x in (intro, tail) if x), options=items)
                else:
                    row['question'] = text
                row.update(type='test', answer=re.sub(r'\D', '', t.get('answer') or ''))
            row['explanation'] = decision or None
            if not row['answer']:
                row.update(status='hidden', issue='нет ответа или не разобрано соответствие')
            elif row['options'] and any(int(ch) > len(row['options']) for ch in row['answer']):
                row.update(status='hidden', issue='в ответе цифра больше числа вариантов')
        else:
            row['type'] = 'image_written' if row['image'] else 'written'
            split = split_passage(READ_TEXT.sub('', text)) if number in (17, 18) and not body else None
            if number in (17, 18) and body:
                # Обычно текст — в text, вопросы — в body; бывает наоборот.
                passage, question = (text, body) if len(text) >= len(body) else (body, text)
                row['passage'] = READ_TEXT.sub('', passage)
                row['question'] = LINE_PREFIX.sub('', question)
            elif split:
                row['passage'], row['question'] = split
            else:
                row['question'] = LINE_PREFIX.sub('', rewrite['new'] if rewrite else text)
            if t['id'] in links and by_id.get(links[t['id']]):
                row['passage'] = READ_TEXT.sub('', clean(by_id[links[t['id']]]['text']))
            # Образец ответа второй части — в answer (так его читают
            # домашки и ИИ-проверка).
            row['answer'] = decision
            if not decision:
                row.update(status='hidden', issue='нет образца ответа')
            elif number in (17, 18) and not row['passage']:
                row.update(status='hidden', issue='17/18: не удалось отделить текст от вопросов')
            elif (number in (19, 20) and not row['passage'] and not rewrite
                  and len(text) <= 700 and REFS_TEXT.search(text)):
                row.update(status='hidden', issue='ссылается на текст, которого нет')
        row['question'] = SOURCE_TAIL.sub('', row['question']).strip()
        if row['passage']:
            row['passage'] = SOURCE_TAIL.sub('', row['passage']).strip()
            # Начала текста хватает: у 17 и 18 один текст, но хвосты в
            # выгрузке иногда расходятся на пару слов.
            row['group'] = hashlib.sha1(norm(row['passage'])[:300].encode()).hexdigest()[:12]
        if not row['block']:
            row.update(status='hidden', issue='неизвестный раздел')
        out.append(row)

    # Дубли выгрузки: одно и то же задание под разными id — оставляем
    # первое, остальные — в also.
    seen, deduped = {}, []
    for row in out:
        # Картинка входит в ключ: у графиков 21 условие одно, картинки разные.
        key = norm(f"{row['number']}|{row['passage'] or ''}|{row['question']}|{json.dumps(row['options'] or row['matches'], ensure_ascii=False)}|{row['answer']}") + '|' + (row['image'] or '')
        if key in seen:
            seen[key]['also'].append(row['ext'])
            continue
        seen[key] = row
        deduped.append(row)
    # Правка из rewrites.json «это дубль» — тоже в also.
    for dup, main in dup_of.items():
        for row in deduped:
            if row['ext'] == main:
                row['also'].append(dup)
    for row in deduped:
        if row['status'] != 'active':
            issues[row['issue']] += 1

    # Группы 17/18 с общим текстом, где нет пары, — просто задания с текстом.
    groups = defaultdict(list)
    for row in deduped:
        if row['group'] and row['number'] in (17, 18):
            groups[row['group']].append(row)
    for row in deduped:
        if row['number'] in (19, 20):
            row['group'] = None  # текст есть, но решаются отдельно
        elif row['group'] and len(groups[row['group']]) < 2:
            row['group'] = None

    # Картинки.
    IMG_DIR.mkdir(parents=True, exist_ok=True)
    for row in deduped:
        if row['image']:
            src = src_dir / 'images' / Path(row['image']).name
            if src.exists():
                shutil.copy2(src, IMG_DIR / src.name)
            else:
                row.update(image=None, status='hidden', issue='нет картинки')

    max_by_number = {}
    for n in range(1, 26):
        c = Counter(r['max'] for r in deduped if r['number'] == n)
        if c:
            max_by_number[n] = c.most_common(1)[0][0]

    return deduped, issues, max_by_number, len(data['tasks'])


def report(rows, issues, max_by_number, total):
    by_number = Counter(r['number'] for r in rows if r['status'] == 'active')
    no_options = sum(1 for r in rows if r['type'] == 'test' and not r['options'])
    no_expl = sum(1 for r in rows if r['number'] <= 16 and not r['explanation'])
    pairs = len({r['group'] for r in rows if r['group']})
    lines = [
        '# Банк заданий — отчёт сборки', '',
        'Собрано `tools/bank/build_from_egehub.py` из выгрузки ege-hub и '
        '`docs/bank/rewrites.json`. Файл перезаписывается при каждой сборке.', '',
        f'- В выгрузке: {total} заданий',
        f'- После склейки дублей: {len(rows)} (склеено {sum(len(r["also"]) for r in rows)})',
        f'- В тренажёре (active): {sum(by_number.values())}',
        f'- Скрыто: {sum(issues.values())}',
        f'- Пар 17+18 с общим текстом: {pairs}',
        f'- Первая часть без распознанного списка вариантов (показываются текстом): {no_options}',
        f'- Первая часть без разбора: {no_expl}', '',
        '## Скрыто — почему', '',
    ]
    lines += [f'- {k}: {v}' for k, v in issues.most_common()] or ['- ничего']
    lines += ['', '## По номерам', '', '| № | В тренажёре | Макс. балл |', '|---|---|---|']
    lines += [f'| {n} | {by_number.get(n, 0)} | {max_by_number.get(n, "—")} |' for n in range(1, 26)]
    OUT_REPORT.write_text('\n'.join(lines) + '\n', encoding='utf-8')


def main():
    if len(sys.argv) != 2:
        print(__doc__)
        sys.exit(1)
    rows, issues, max_by_number, total = build(sys.argv[1])
    OUT_JSON.write_text(json.dumps({
        'source': 'ege-hub.ru, выгрузка 28.10.2026',
        'max_by_number': max_by_number,
        'tasks': rows,
    }, ensure_ascii=False, indent=1), encoding='utf-8')
    report(rows, issues, max_by_number, total)
    print(f'{len(rows)} заданий -> {OUT_JSON.relative_to(ROOT)}; скрыто {sum(issues.values())}')


if __name__ == '__main__':
    main()
