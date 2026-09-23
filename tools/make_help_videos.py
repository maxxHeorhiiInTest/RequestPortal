#!/usr/bin/env python3
"""Build short silent how-to videos for the public portal pages."""

from __future__ import annotations

import math
import shutil
import subprocess
import tempfile
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont
import imageio_ffmpeg

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "assets" / "help"
FFMPEG = imageio_ffmpeg.get_ffmpeg_exe()
W, H = 1280, 720
FPS = 12
CAPTION_H = 92

BG = "#f4f6f8"
CARD = "#ffffff"
TEXT = "#1f2933"
MUTED = "#6b7680"
BORDER = "#d8dee4"
PRIMARY = "#2563a8"
PRIMARY_DARK = "#1d4d85"
WHITE = "#ffffff"
HIGHLIGHT = "#fde68a"

FONT = "/System/Library/Fonts/Supplemental/Arial.ttf"
FONT_B = "/System/Library/Fonts/Supplemental/Arial Bold.ttf"


def font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont:
    return ImageFont.truetype(FONT_B if bold else FONT, size)


def wrap(draw: ImageDraw.ImageDraw, text: str, fnt: ImageFont.FreeTypeFont, max_w: int) -> list[str]:
    words = text.split()
    lines: list[str] = []
    cur = ""
    for word in words:
        trial = word if not cur else cur + " " + word
        if draw.textlength(trial, font=fnt) <= max_w:
            cur = trial
        else:
            if cur:
                lines.append(cur)
            cur = word
    if cur:
        lines.append(cur)
    return lines or [text]


def round_rect(draw: ImageDraw.ImageDraw, xy, r: int, fill, outline=None, width: int = 1) -> None:
    draw.rounded_rectangle(xy, radius=r, fill=fill, outline=outline, width=width)


def draw_pointer(img: Image.Image, xy: tuple[int, int], click: bool = False) -> None:
    x, y = xy
    scale = 0.86 if click else 1.0
    pts = [
        (x, y),
        (x + int(18 * scale), y + int(18 * scale)),
        (x + int(8 * scale), y + int(18 * scale)),
        (x + int(14 * scale), y + int(32 * scale)),
        (x + int(8 * scale), y + int(34 * scale)),
        (x, y + int(20 * scale)),
    ]
    overlay = Image.new("RGBA", img.size, (0, 0, 0, 0))
    d = ImageDraw.Draw(overlay)
    if click:
        d.ellipse((x - 16, y - 16, x + 16, y + 16), outline=(37, 99, 168, 90), width=3)
    d.polygon(pts, fill=(31, 41, 51, 255), outline=(255, 255, 255, 255))
    img.alpha_composite(overlay)


def chrome(active: str) -> Image.Image:
    img = Image.new("RGBA", (W, H), BG)
    d = ImageDraw.Draw(img)
    d.rectangle((0, 0, W, 64), fill=WHITE)
    d.line((0, 64, W, 64), fill=BORDER)
    d.text((32, 12), "Портал заявок", font=font(18, True), fill=TEXT)
    d.text((32, 36), "Заявки на сайт, питання та пропозиції", font=font(12), fill=MUTED)
    d.text((980, 22), "Панель адміністратора", font=font(13), fill=PRIMARY)
    round_rect(d, (1180, 16, 1238, 42), 4, PRIMARY)
    d.text((1193, 21), "UK", font=font(13, True), fill=WHITE)

    y = 80
    d.line((32, 118, W - 32, 118), fill=BORDER)
    tabs = [
        ("requests", "Заявки на сайт"),
        ("plan", "Додати анонс"),
        ("feedback", "Питання / пропозиції"),
        ("it", "Заявка до IT-відділу"),
    ]
    x = 40
    for key, label in tabs:
        fnt = font(14, True)
        tw = d.textlength(label, font=fnt)
        color = PRIMARY if key == active else MUTED
        d.text((x, y + 2), label, font=fnt, fill=color)
        if key == active:
            d.line((x, 116, x + tw, 116), fill=PRIMARY, width=3)
        x += tw + 20
    return img


def field(d, x, y, w, h, label, value, hl=False, placeholder="") -> tuple[int, int, int, int]:
    d.text((x, y), label, font=font(13, True), fill=TEXT)
    box = (x, y + 20, x + w, y + 20 + h)
    round_rect(d, box, 6, "#fffbeb" if hl else WHITE, HIGHLIGHT if hl else BORDER, 3 if hl else 1)
    shown = value if value else placeholder
    color = TEXT if value else MUTED
    d.text((x + 10, y + 26 + (h - 22) / 2), shown, font=font(14), fill=color)
    return box


def caption_bar(img: Image.Image, text: str) -> None:
    d = ImageDraw.Draw(img)
    d.rectangle((0, H - CAPTION_H, W, H), fill=PRIMARY_DARK)
    fnt = font(26, True)
    lines = wrap(d, text, fnt, W - 80)
    total = len(lines) * 32
    y = H - CAPTION_H + (CAPTION_H - total) / 2
    for line in lines[:2]:
        tw = d.textlength(line, font=fnt)
        d.text(((W - tw) / 2, y), line, font=fnt, fill=WHITE)
        y += 32


def draw_requests(state: dict):
    img = chrome("requests")
    d = ImageDraw.Draw(img)
    round_rect(d, (32, 128, W - 32, H - CAPTION_H - 12), 8, CARD, BORDER)
    d.text((56, 140), "Подати заявку", font=font(22, True), fill=TEXT)
    if state.get("success"):
        d.text((56, 210), "Заявку надіслано", font=font(28, True), fill=PRIMARY)
        d.text((56, 258), "Дякуємо! Заявку зареєстровано під номером", font=font(18), fill=TEXT)
        round_rect(d, (56, 308, 430, 368), 8, "#e8f1fb", PRIMARY)
        d.text((76, 324), state["success"], font=font(22, True), fill=PRIMARY_DARK)
        d.text((56, 392), "Запишіть цей номер або сфотографуйте екран.", font=font(18), fill=TEXT)
        caption_bar(img, state["caption"])
        return img, {}

    d.text((56, 170), "Заповніть форму — заявка потрапить до адміністратора сайту.", font=font(13), fill=MUTED)
    hl = state.get("hl")
    d.text((56, 196), "Тип заявки *", font=font(13, True), fill=TEXT)
    type_box = (56, 216, 760, 256)
    selected = state.get("type", "new") == "new"
    outline = HIGHLIGHT if hl == "type" else (PRIMARY if selected else BORDER)
    round_rect(d, type_box, 6, "#fffbeb" if hl == "type" else WHITE, outline, 2)
    d.ellipse((70, 228, 86, 244), outline=PRIMARY, width=2)
    d.ellipse((74, 232, 82, 240), fill=PRIMARY)
    d.text((98, 226), "Нове розміщення", font=font(14, True), fill=TEXT)
    d.text((280, 227), "або «Оновлення існуючої інформації»", font=font(13), fill=MUTED)

    boxes = {"type": type_box}
    boxes["target"] = field(d, 56, 266, 720, 32, "Де саме розмістити або змінити *", state.get("target", ""), hl == "target", "посилання або назва розділу")
    boxes["description"] = field(d, 56, 326, 720, 40, "Що потрібно розмістити або змінити *", state.get("description", ""), hl == "description")
    boxes["faculty"] = field(d, 56, 392, 350, 32, "Факультет *", state.get("faculty", ""), hl == "faculty")
    boxes["department"] = field(d, 426, 392, 350, 32, "Кафедра *", state.get("department", ""), hl == "department")
    boxes["name"] = field(d, 56, 452, 350, 32, "Ваше ім’я *", state.get("name", ""), hl == "name")
    boxes["contact"] = field(d, 426, 452, 350, 32, "Контакт для зв’язку *", state.get("contact", ""), hl == "contact")
    boxes["files"] = field(d, 56, 512, 350, 32, "Файли (не обов’язково)", state.get("files", "Обрати файли"), hl == "files")
    btn = (426, 532, 640, 572)
    round_rect(d, btn, 6, PRIMARY_DARK if hl == "submit" else PRIMARY)
    d.text((448, 542), "Надіслати заявку", font=font(16, True), fill=WHITE)
    boxes["submit"] = btn
    caption_bar(img, state["caption"])
    return img, boxes


def draw_feedback(state: dict):
    img = chrome("feedback")
    d = ImageDraw.Draw(img)
    round_rect(d, (32, 128, W - 32, H - CAPTION_H - 12), 8, CARD, BORDER)
    d.text((56, 140), "Питання або пропозиція", font=font(22, True), fill=TEXT)
    if state.get("success"):
        d.text((56, 210), "Звернення надіслано", font=font(28, True), fill=PRIMARY)
        d.text((56, 258), "Дякуємо! Звернення зареєстровано під номером", font=font(18), fill=TEXT)
        round_rect(d, (56, 308, 430, 368), 8, "#e8f1fb", PRIMARY)
        d.text((76, 324), state["success"], font=font(22, True), fill=PRIMARY_DARK)
        d.text((56, 392), "Відповідь надійде в обраний канал: Email, Telegram або WhatsApp.", font=font(18), fill=TEXT)
        caption_bar(img, state["caption"])
        return img, {}

    d.text((56, 168), "Напишіть питання чи пропозицію. Логін не потрібен.", font=font(13), fill=MUTED)
    hl = state.get("hl")
    boxes = {}
    boxes["message"] = field(d, 56, 190, 700, 44, "Питання / пропозиція *", state.get("message", ""), hl == "message")
    boxes["files"] = field(d, 56, 258, 330, 28, "Фото, відео або документи", state.get("files", "Обрати файли"), hl == "files")
    boxes["faculty"] = field(d, 56, 308, 330, 28, "Факультет *", state.get("faculty", ""), hl == "faculty")
    boxes["department"] = field(d, 406, 308, 330, 28, "Кафедра *", state.get("department", ""), hl == "department")
    boxes["name"] = field(d, 56, 358, 330, 28, "Ваше ім’я *", state.get("name", ""), hl == "name")
    boxes["phone"] = field(d, 406, 358, 330, 28, "Номер телефону *", state.get("phone", ""), hl == "phone", "+380 50 123 45 67")

    d.text((56, 410), "Куди надіслати відповідь *", font=font(13, True), fill=TEXT)
    channel_box = (56, 430, 736, 474)
    round_rect(d, channel_box, 6, "#fffbeb" if hl == "channel" else WHITE, HIGHLIGHT if hl == "channel" else BORDER, 3 if hl == "channel" else 1)
    selected = state.get("channel", "")
    x = 72
    for key, label in (("email", "Email"), ("telegram", "Telegram"), ("whatsapp", "WhatsApp")):
        on = selected == key
        d.ellipse((x, 444, x + 16, 460), outline=PRIMARY, width=2)
        if on:
            d.ellipse((x + 4, 448, x + 12, 456), fill=PRIMARY)
        d.text((x + 24, 442), label, font=font(14, True if on else False), fill=TEXT)
        x += 220
    boxes["channel"] = channel_box

    contact_label = {
        "email": "Email *",
        "telegram": "Нік або посилання Telegram *",
        "whatsapp": "Номер WhatsApp *",
    }.get(selected, "Контакт у цьому каналі *")
    boxes["contact"] = field(d, 56, 486, 700, 28, contact_label, state.get("contact", ""), hl == "contact")

    btn = (56, 538, 200, 576)
    round_rect(d, btn, 6, PRIMARY_DARK if hl == "submit" else PRIMARY)
    d.text((88, 548), "Надіслати", font=font(16, True), fill=WHITE)
    boxes["submit"] = btn
    caption_bar(img, state["caption"])
    return img, boxes


def draw_plan(state: dict):
    img = chrome("plan")
    d = ImageDraw.Draw(img)
    round_rect(d, (32, 128, W - 32, H - CAPTION_H - 12), 8, CARD, BORDER)
    d.text((56, 140), "Додати анонс", font=font(22, True), fill=TEXT)
    if state.get("success"):
        d.text((56, 210), "Анонс надіслано", font=font(28, True), fill=PRIMARY)
        d.text((56, 258), "Дякуємо! Анонс збережено як чернетку.", font=font(18), fill=TEXT)
        d.text((56, 292), "Команда сайту побачить його в контент-плані.", font=font(18), fill=TEXT)
        d.text((56, 360), "Це ще не публікація — спочатку перевірка.", font=font(18), fill=TEXT)
        caption_bar(img, state["caption"])
        return img, {}

    d.text((56, 170), "Заповніть форму — анонс потрапить до контент-плану як чернетка.", font=font(13), fill=MUTED)
    hl = state.get("hl")
    boxes = {}
    boxes["title"] = field(d, 56, 196, 720, 32, "Назва події *", state.get("title", ""), hl == "title", "наприклад: Кубок університету")
    boxes["when"] = field(d, 56, 256, 350, 32, "Дата та час проведення *", state.get("when", ""), hl == "when", "оберіть майбутню дату")
    boxes["department"] = field(d, 426, 256, 350, 32, "Підрозділ / кафедра *", state.get("department", ""), hl == "department")
    boxes["responsible"] = field(d, 56, 316, 350, 32, "Відповідальна особа *", state.get("responsible", ""), hl == "responsible")
    boxes["description"] = field(d, 426, 316, 350, 32, "Короткий опис *", state.get("description", ""), hl == "description")
    boxes["extra"] = field(d, 56, 376, 720, 32, "Посилання або додаткова інформація", state.get("extra", ""), hl == "extra", "не обов’язково")

    d.text((56, 436), "Заплановані соцмережі / канали публікації", font=font(13, True), fill=TEXT)
    channel_box = (56, 458, 760, 508)
    round_rect(d, channel_box, 6, "#fffbeb" if hl == "channels" else WHITE, HIGHLIGHT if hl == "channels" else BORDER, 3 if hl == "channels" else 1)
    selected = set(state.get("channels") or [])
    x = 68
    for name in ("Сайт", "Facebook", "Instagram", "Telegram", "YouTube", "TikTok"):
        mark = name in selected
        d.rectangle((x, 474, x + 14, 488), outline=PRIMARY, width=2, fill=PRIMARY if mark else WHITE)
        if mark:
            d.line((x + 3, 481, x + 6, 486), fill=WHITE, width=2)
            d.line((x + 6, 486, x + 11, 476), fill=WHITE, width=2)
        d.text((x + 18, 472), name, font=font(12), fill=TEXT)
        x += 114
    boxes["channels"] = channel_box

    btn = (56, 528, 270, 568)
    round_rect(d, btn, 6, PRIMARY_DARK if hl == "submit" else PRIMARY)
    d.text((78, 538), "Надіслати анонс", font=font(16, True), fill=WHITE)
    boxes["submit"] = btn
    caption_bar(img, state["caption"])
    return img, boxes


def draw_it(state: dict):
    img = chrome("it")
    d = ImageDraw.Draw(img)
    round_rect(d, (32, 128, W - 32, H - CAPTION_H - 12), 8, CARD, BORDER)
    d.text((56, 140), "Заявка до IT-відділу", font=font(22, True), fill=TEXT)
    if state.get("success"):
        d.text((56, 210), "Заявку надіслано", font=font(28, True), fill=PRIMARY)
        d.text((56, 258), "Дякуємо! Заявку до IT-відділу зареєстровано під номером", font=font(18), fill=TEXT)
        round_rect(d, (56, 308, 430, 368), 8, "#e8f1fb", PRIMARY)
        d.text((76, 324), state["success"], font=font(22, True), fill=PRIMARY_DARK)
        d.text((56, 392), "Співробітник IT-відділу побачить заявку на дошці.", font=font(18), fill=TEXT)
        caption_bar(img, state["caption"])
        return img, {}

    d.text((56, 168), "Опишіть проблему — заявка потрапить до співробітника IT-відділу.", font=font(13), fill=MUTED)
    hl = state.get("hl")
    boxes = {}

    d.text((56, 196), "Тип звернення *", font=font(13, True), fill=TEXT)
    type_box = (56, 216, 760, 260)
    round_rect(d, type_box, 6, "#fffbeb" if hl == "category" else WHITE, HIGHLIGHT if hl == "category" else BORDER, 3 if hl == "category" else 1)
    selected = state.get("category", "")
    x = 72
    for key, label in (
        ("printer", "Принтер"),
        ("cartridge", "Картридж"),
        ("computer", "Комп’ютер"),
        ("network", "Мережа"),
        ("other", "Інше"),
    ):
        on = selected == key
        d.ellipse((x, 230, x + 16, 246), outline=PRIMARY, width=2)
        if on:
            d.ellipse((x + 4, 234, x + 12, 242), fill=PRIMARY)
        d.text((x + 22, 228), label, font=font(13, True if on else False), fill=TEXT)
        x += 138
    boxes["category"] = type_box

    boxes["name"] = field(d, 56, 274, 350, 32, "Ім’я та прізвище співробітника *", state.get("name", ""), hl == "name")
    boxes["phone"] = field(d, 426, 274, 350, 32, "Контактний номер *", state.get("phone", ""), hl == "phone", "+380 50 123 45 67")
    boxes["building"] = field(d, 56, 334, 350, 32, "Корпус *", state.get("building", ""), hl == "building")
    boxes["room"] = field(d, 426, 334, 350, 32, "Номер кабінету *", state.get("room", ""), hl == "room")
    boxes["message"] = field(d, 56, 394, 720, 48, "Короткий опис проблеми *", state.get("message", ""), hl == "message")

    btn = (56, 468, 360, 508)
    round_rect(d, btn, 6, PRIMARY_DARK if hl == "submit" else PRIMARY)
    d.text((78, 478), "Надіслати заявку до IT", font=font(16, True), fill=WHITE)
    boxes["submit"] = btn
    caption_bar(img, state["caption"])
    return img, boxes


def box_point(box) -> tuple[int, int]:
    return (int((box[0] + box[2]) / 2), int(box[1] + 8))


def lerp(a, b, t: float):
    return (int(a[0] + (b[0] - a[0]) * t), int(a[1] + (b[1] - a[1]) * t))


def ease(t: float) -> float:
    return 0.5 - 0.5 * math.cos(math.pi * t)


def render_story(draw_fn, story: list[dict], out_mp4: Path, poster: Path) -> None:
    tmp = Path(tempfile.mkdtemp(prefix="rp-help-"))
    n = 0
    pointer = (640, 360)
    first = None

    def emit(img: Image.Image, pointer_xy=None, click=False, copies=1):
        nonlocal n, first
        frame = img.convert("RGBA")
        if pointer_xy:
            draw_pointer(frame, pointer_xy, click=click)
        rgb = frame.convert("RGB")
        if first is None:
            first = rgb.copy()
        for _ in range(copies):
            rgb.save(tmp / f"f{n:04d}.png")
            n += 1

    for step in story:
        img, boxes = draw_fn(step)
        target = pointer
        if step.get("point") and step["point"] in boxes:
            target = box_point(boxes[step["point"]])
        if step.get("move", True) and target != pointer:
            frames = 8
            start = pointer
            for i in range(frames):
                img, boxes = draw_fn(step)
                emit(img, lerp(start, target, ease((i + 1) / frames)))
            pointer = target
        copies = max(1, int(step.get("hold", 2.2) * FPS))
        emit(img, pointer if step.get("point") else None, click=False, copies=copies)
        if step.get("click"):
            img, boxes = draw_fn(step)
            emit(img, pointer, click=True, copies=4)
            emit(img, pointer, click=False, copies=3)

    if first is None:
        raise RuntimeError("no frames")
    first.save(poster, quality=86)

    cmd = [
        FFMPEG, "-y",
        "-framerate", str(FPS),
        "-i", str(tmp / "f%04d.png"),
        "-c:v", "libx264",
        "-pix_fmt", "yuv420p",
        "-crf", "24",
        "-movflags", "+faststart",
        str(out_mp4),
    ]
    subprocess.check_call(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    shutil.rmtree(tmp, ignore_errors=True)


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)

    request_story = [
        {"caption": "Ця сторінка — щоб попросити розмістити або виправити інформацію на сайті.", "hl": None, "hold": 2.8, "point": None, "move": False},
        {"caption": "Питання — «Питання / пропозиції». Подія — «Додати анонс». Техніка — «Заявка до IT-відділу».", "hl": None, "hold": 3.2, "point": None, "move": False},
        {"caption": "Оберіть тип: нове розміщення — якщо цього ще немає на сайті.", "hl": "type", "type": "new", "hold": 3.0, "point": "type", "click": True},
        {"caption": "Напишіть, де саме це має бути: посилання на сторінку або назва розділу.", "hl": "target", "type": "new", "target": "Новини → Оголошення", "hold": 3.2, "point": "target", "click": True},
        {"caption": "Своїми словами опишіть, що треба зробити. Чим зрозуміліше — тим швидше допоможуть.", "hl": "description", "type": "new", "target": "Новини → Оголошення", "description": "Просимо опублікувати оголошення про збори.", "hold": 3.4, "point": "description", "click": True},
        {"caption": "Обов’язково вкажіть факультет і кафедру.", "hl": "faculty", "type": "new", "target": "Новини → Оголошення", "description": "Просимо опублікувати оголошення про збори.", "faculty": "Факультет спорту", "department": "Кафедра футболу", "hold": 3.0, "point": "faculty", "click": True},
        {"caption": "Напишіть ім’я і контакт: телефон, пошта або Telegram.", "hl": "contact", "type": "new", "target": "Новини → Оголошення", "description": "Просимо опублікувати оголошення про збори.", "faculty": "Факультет спорту", "department": "Кафедра футболу", "name": "Іван Петренко", "contact": "050 123 45 67", "hold": 3.2, "point": "contact", "click": True},
        {"caption": "Файли можна додати, але не обов’язково.", "hl": "files", "type": "new", "target": "Новини → Оголошення", "description": "Просимо опублікувати оголошення про збори.", "faculty": "Факультет спорту", "department": "Кафедра футболу", "name": "Іван Петренко", "contact": "050 123 45 67", "hold": 2.6, "point": "files"},
        {"caption": "Натисніть синю кнопку «Надіслати заявку» внизу.", "hl": "submit", "type": "new", "target": "Новини → Оголошення", "description": "Просимо опублікувати оголошення про збори.", "faculty": "Факультет спорту", "department": "Кафедра футболу", "name": "Іван Петренко", "contact": "050 123 45 67", "hold": 2.4, "point": "submit", "click": True},
        {"caption": "З’явиться номер заявки. Запишіть його або сфотографуйте екран.", "success": "REQ-2026-XXXXXX", "hold": 3.8, "point": None, "move": False},
    ]
    render_story(draw_requests, request_story, OUT / "requests.mp4", OUT / "requests.jpg")

    filled = {
        "message": "Чи можна додати розклад на сайт?",
        "faculty": "Факультет спорту",
        "department": "Кафедра футболу",
        "name": "Марія Коваленко",
        "phone": "+380 50 123 45 67",
        "channel": "telegram",
        "contact": "@igor",
    }
    feedback_story = [
        {"caption": "Ця сторінка — для запитання або пропозиції. Реєструватися не потрібно.", "hl": None, "hold": 2.8, "point": None, "move": False},
        {"caption": "Сайт — «Заявки на сайт». Подія — «Додати анонс». Техніка — «Заявка до IT-відділу».", "hl": None, "hold": 3.2, "point": None, "move": False},
        {"caption": "У полі «Питання / пропозиція» напишіть звернення своїми словами.", "hl": "message", "message": filled["message"], "hold": 3.2, "point": "message", "click": True},
        {"caption": "Фото чи документ можна додати, якщо це допоможе. Можна нічого не додавати.", "hl": "files", "message": filled["message"], "hold": 2.6, "point": "files"},
        {"caption": "Обов’язково вкажіть факультет і кафедру.", "hl": "faculty", "message": filled["message"], "faculty": filled["faculty"], "department": filled["department"], "hold": 2.8, "point": "faculty", "click": True},
        {"caption": "Напишіть своє ім’я.", "hl": "name", "message": filled["message"], "faculty": filled["faculty"], "department": filled["department"], "name": filled["name"], "hold": 2.4, "point": "name", "click": True},
        {"caption": "У полі «Номер телефону» вкажіть телефон. Це обов’язково.", "hl": "phone", **{k: filled[k] for k in ("message", "faculty", "department", "name", "phone")}, "hold": 2.8, "point": "phone", "click": True},
        {"caption": "Оберіть, куди надіслати відповідь: Email, Telegram або WhatsApp.", "hl": "channel", **{k: filled[k] for k in ("message", "faculty", "department", "name", "phone", "channel")}, "hold": 3.2, "point": "channel", "click": True},
        {"caption": "Вкажіть контакт цього каналу. Для Telegram — нік, наприклад @igor.", "hl": "contact", **filled, "hold": 3.2, "point": "contact", "click": True},
        {"caption": "Натисніть синю кнопку «Надіслати» внизу сторінки.", "hl": "submit", **filled, "hold": 2.4, "point": "submit", "click": True},
        {"caption": "З’явиться номер звернення. Відповідь надійде в обраний канал.", "success": "QST-2026-XXXXXX", "hold": 3.8, "point": None, "move": False},
    ]
    render_story(draw_feedback, feedback_story, OUT / "feedback.mp4", OUT / "feedback.jpg")

    plan_story = [
        {"caption": "Ця сторінка — щоб повідомити про майбутню подію. Реєструватися не потрібно.", "hl": None, "hold": 2.8, "point": None, "move": False},
        {"caption": "Сайт — «Заявки на сайт». Питання — «Питання / пропозиції». Техніка — «Заявка до IT-відділу».", "hl": None, "hold": 3.2, "point": None, "move": False},
        {"caption": "Напишіть коротку назву події.", "hl": "title", "title": "Кубок університету з футболу", "hold": 2.8, "point": "title", "click": True},
        {"caption": "Оберіть дату і час. Минулу дату поставити не можна.", "hl": "when", "title": "Кубок університету з футболу", "when": "12.10.2026, 10:00", "hold": 3.0, "point": "when", "click": True},
        {"caption": "Вкажіть підрозділ і відповідальну особу.", "hl": "responsible", "title": "Кубок університету з футболу", "when": "12.10.2026, 10:00", "department": "Кафедра футболу", "responsible": "Іван Петренко", "hold": 3.0, "point": "responsible", "click": True},
        {"caption": "Коротко опишіть подію своїми словами.", "hl": "description", "title": "Кубок університету з футболу", "when": "12.10.2026, 10:00", "department": "Кафедра футболу", "responsible": "Іван Петренко", "description": "Відкриті змагання для студентів.", "hold": 3.0, "point": "description", "click": True},
        {"caption": "Позначте канали: сайт, Facebook, Instagram, Telegram, YouTube, TikTok.", "hl": "channels", "title": "Кубок університету з футболу", "when": "12.10.2026, 10:00", "department": "Кафедра футболу", "responsible": "Іван Петренко", "description": "Відкриті змагання для студентів.", "channels": ["Сайт", "Facebook", "Telegram"], "hold": 3.0, "point": "channels", "click": True},
        {"caption": "Натисніть синю кнопку «Надіслати анонс».", "hl": "submit", "title": "Кубок університету з футболу", "when": "12.10.2026, 10:00", "department": "Кафедра футболу", "responsible": "Іван Петренко", "description": "Відкриті змагання для студентів.", "channels": ["Сайт", "Facebook", "Telegram"], "hold": 2.4, "point": "submit", "click": True},
        {"caption": "З’явиться підтвердження. Анонс збережеться як чернетка до перевірки.", "success": True, "hold": 3.8, "point": None, "move": False},
    ]
    render_story(draw_plan, plan_story, OUT / "plan.mp4", OUT / "plan.jpg")

    it_filled = {
        "category": "printer",
        "name": "Іван Петренко",
        "phone": "+380 50 123 45 67",
        "building": "Корпус 1",
        "room": "215",
        "message": "Принтер не друкує, блимає червона лампочка.",
    }
    it_story = [
        {"caption": "Ця сторінка — щоб повідомити IT-відділ про технічну проблему.", "hl": None, "hold": 2.8, "point": None, "move": False},
        {"caption": "Сайт — «Заявки на сайт». Подія — «Додати анонс». Питання — «Питання / пропозиції».", "hl": None, "hold": 3.2, "point": None, "move": False},
        {"caption": "Оберіть тип звернення: принтер, картридж, комп’ютер, мережа або інше.", "hl": "category", "category": "printer", "hold": 3.2, "point": "category", "click": True},
        {"caption": "Напишіть ім’я та прізвище співробітника.", "hl": "name", "category": "printer", "name": it_filled["name"], "hold": 2.6, "point": "name", "click": True},
        {"caption": "У полі «Контактний номер» вкажіть телефон.", "hl": "phone", "category": "printer", "name": it_filled["name"], "phone": it_filled["phone"], "hold": 2.8, "point": "phone", "click": True},
        {"caption": "Вкажіть корпус і номер кабінету.", "hl": "building", "category": "printer", "name": it_filled["name"], "phone": it_filled["phone"], "building": it_filled["building"], "room": it_filled["room"], "hold": 2.8, "point": "building", "click": True},
        {"caption": "Коротко опишіть проблему своїми словами.", "hl": "message", **it_filled, "hold": 3.2, "point": "message", "click": True},
        {"caption": "Натисніть синю кнопку «Надіслати заявку до IT».", "hl": "submit", **it_filled, "hold": 2.4, "point": "submit", "click": True},
        {"caption": "З’явиться номер заявки. Запишіть його або сфотографуйте екран.", "success": "IT-2026-XXXXXX", "hold": 3.8, "point": None, "move": False},
    ]
    render_story(draw_it, it_story, OUT / "it.mp4", OUT / "it.jpg")

    for name in ("requests.mp4", "feedback.mp4", "plan.mp4", "it.mp4"):
        path = OUT / name
        print(f"{name} {path.stat().st_size / 1024:.0f} KB")


if __name__ == "__main__":
    main()
