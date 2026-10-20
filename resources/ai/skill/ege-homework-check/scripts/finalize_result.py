"""Собирает файл результата проверки из results.jsonl и сверяет его с выгрузкой.

Использование:
    python finalize_result.py <выгрузка.json> <results.jsonl> <результат.json>

Ошибки (нет результата на задание, балл вне шкалы, нет обязательного поля)
печатаются, и скрипт завершается с кодом 1 — файл результата при этом не
пишется. Если в results.jsonl несколько строк с одним id, берётся последняя.
"""

import json
import os
import sys

INPUT_FORMAT = "poltavskiy-homework-check/v1"
RESULT_FORMAT = "poltavskiy-homework-check-result/v1"

REQUIRED = {
    "elements": list,
    "extra_elements_with_errors": list,
    "criteria_scores": list,
    "score": int,
    "rationale": str,
    "comment": str,
    "needs_human_review": bool,
    "review_reason": str,
}


def main(input_path, results_path, output_path):
    sys.stdout.reconfigure(encoding="utf-8")
    with open(input_path, encoding="utf-8") as f:
        export = json.load(f)
    if export.get("format") != INPUT_FORMAT:
        sys.exit(f"{input_path}: это не файл выгрузки ({INPUT_FORMAT})")

    version_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "VERSION")
    if os.path.exists(version_path):
        with open(version_path, encoding="utf-8") as f:
            skill_version = f.read().strip()
        if export.get("rules_version") and export["rules_version"] != skill_version:
            print(
                f"Предупреждение: навык версии {skill_version}, а выгрузка сделана по правилам "
                f"{export['rules_version']} — навык устарел, его нужно скачать в кабинете заново."
            )

    items = {item["id"]: item for item in export["items"]}

    results = {}
    with open(results_path, encoding="utf-8") as f:
        for n, line in enumerate(f, 1):
            line = line.strip()
            if not line:
                continue
            try:
                row = json.loads(line)
            except json.JSONDecodeError as e:
                sys.exit(f"{results_path}:{n}: не JSON ({e})")
            results[row.get("id")] = row

    errors = []
    warnings = []
    out = []
    for item_id, item in items.items():
        row = results.get(item_id)
        if row is None:
            errors.append(f"{item_id}: нет результата")
            continue

        for field, kind in REQUIRED.items():
            value = row.get(field)
            # bool — подкласс int в Python, балл True/False не пропускаем.
            if not isinstance(value, kind) or (kind is int and isinstance(value, bool)):
                errors.append(f"{item_id}: поле {field} отсутствует или не {kind.__name__}")

        score = row.get("score")
        if isinstance(score, int) and not isinstance(score, bool):
            if not 0 <= score <= item["max_score"]:
                errors.append(f"{item_id}: балл {score} вне шкалы 0..{item['max_score']}")
            parts = row.get("criteria_scores") or []
            if parts and all(isinstance(p.get("score"), int) for p in parts):
                total = sum(p["score"] for p in parts)
                if total != score:
                    warnings.append(f"{item_id}: сумма по критериям {total} ≠ итоговому баллу {score}")

        if row.get("needs_human_review") and not str(row.get("review_reason", "")).strip():
            warnings.append(f"{item_id}: needs_human_review без review_reason")

        row["submission_id"] = item["submission_id"]
        row["task_id"] = item["task_id"]
        out.append(row)

    extra = [r for r in results if r not in items]
    for item_id in extra:
        errors.append(f"{item_id}: такого задания нет в выгрузке")

    for w in warnings:
        print("Предупреждение:", w)
    if errors:
        for e in errors:
            print("Ошибка:", e)
        sys.exit(1)

    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(
            {"format": RESULT_FORMAT, "export_id": export["export_id"], "results": out},
            f,
            ensure_ascii=False,
            indent=2,
        )

    review = sum(1 for r in out if r["needs_human_review"])
    print(f"Готово: {output_path} — заданий: {len(out)}, «проверьте сами»: {review}")


if __name__ == "__main__":
    if len(sys.argv) != 4:
        sys.exit(__doc__)
    main(*sys.argv[1:])
