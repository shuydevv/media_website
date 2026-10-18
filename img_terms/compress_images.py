"""
Одноразовое сжатие картинок для тренажёра терминов.

Берёт все .jpg из этой папки и кладёт сжатые копии с теми же именами в
подпапку compressed/. Оригиналы не трогает.

Что делает с каждой картинкой:
  - если шире MAX_WIDTH, уменьшает до MAX_WIDTH по ширине (пропорционально).
    На сайте фото занимает не больше ~720 px в ширину, 1440 — запас на
    экраны с двойной плотностью пикселей, так что на глаз разницы нет;
  - пересохраняет в JPEG с качеством QUALITY и полным цветовым разрешением,
    прогрессивно, с оптимизацией таблиц сжатия, без служебных данных (EXIF),
    но с цветовым профилем. Настройки подобраны по замеру на этих 188
    картинках: качество 85 давало втрое меньший вес, но у самых детальных
    иллюстраций заметные потери (PSNR до 34 дБ); 92 с полным цветом — не
    ниже ~39 дБ при весе вдвое меньше исходного;
  - если сжатая копия вышла не меньше оригинала, кладёт оригинал как есть.

В конце печатает, сколько весили файлы до и после, и насколько сжатые
отличаются от исходных (PSNR: около 40 дБ и выше — на глаз неотличимо).

Запуск из любой папки:  python compress_images.py
Нужен Pillow:           python -m pip install pillow
"""

import math
import os
import shutil

from PIL import Image, ImageChops, ImageStat

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'compressed')

MAX_WIDTH = 1440
QUALITY = 92
# 0 = цвет хранится в полном разрешении (4:4:4). По умолчанию JPEG урезает
# его вдвое — на этих детальных иллюстрациях это заметно по краям.
SUBSAMPLING = 0


def psnr(a: Image.Image, b: Image.Image) -> float:
    """Насколько картинки похожи: чем больше, тем ближе (бесконечность — одинаковые)."""
    diff = ImageChops.difference(a.convert('RGB'), b.convert('RGB'))
    mse = sum(v * v for v in ImageStat.Stat(diff).rms) / 3
    return float('inf') if mse == 0 else 20 * math.log10(255 / math.sqrt(mse))


def main() -> None:
    os.makedirs(OUT, exist_ok=True)
    files = sorted(f for f in os.listdir(HERE) if f.lower().endswith(('.jpg', '.jpeg')))

    before = after = 0
    worst = (float('inf'), '')
    kept_original = []

    for name in files:
        src = os.path.join(HERE, name)
        dst = os.path.join(OUT, name)

        with Image.open(src) as original:
            original.load()
            icc = original.info.get('icc_profile')
            image = original.convert('RGB')

        if image.width > MAX_WIDTH:
            height = round(image.height * MAX_WIDTH / image.width)
            image = image.resize((MAX_WIDTH, height), Image.LANCZOS)

        image.save(dst, 'JPEG', quality=QUALITY, subsampling=SUBSAMPLING, optimize=True, progressive=True, icc_profile=icc)

        if os.path.getsize(dst) >= os.path.getsize(src):
            shutil.copy2(src, dst)
            kept_original.append(name)
        else:
            # Сравниваем с оригиналом того же размера, чтобы мерить именно
            # потери от сжатия, а не от уменьшения.
            with Image.open(src) as original, Image.open(dst) as compressed:
                reference = original.convert('RGB').resize(compressed.size, Image.LANCZOS)
                score = psnr(reference, compressed)
                worst = min(worst, (score, name))

        before += os.path.getsize(src)
        after += os.path.getsize(dst)

    mb = 1024 * 1024
    print(f'Файлов: {len(files)}')
    print(f'Было: {before / mb:.1f} МБ, стало: {after / mb:.1f} МБ ({after / before:.0%} от исходного)')
    if worst[1]:
        print(f'Самое заметное отличие от оригинала: {worst[0]:.1f} дБ PSNR ({worst[1]}); около 40 и выше — на глаз неотличимо')
    if kept_original:
        print(f'Оставлены без изменений (сжатие не помогло): {", ".join(kept_original)}')
    print(f'Сжатые файлы: {OUT}')


if __name__ == '__main__':
    main()
