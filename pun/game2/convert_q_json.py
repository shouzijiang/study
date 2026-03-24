# -*- coding: utf-8 -*-
"""将 q.json 旧行格式转为结构化 JSON。"""
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent
SRC = ROOT / "q.json"
OUT = ROOT / "q.json"

# 拼音音节：字母 + 声调数字 1-5（兼容 ü 用 v）
PINYIN_RE = re.compile(r"^[a-zA-ZvüÜ]+\d$", re.I)

# 行尾关卡号：//123 或 ,/123 或空格 //123
LEVEL_RE = re.compile(r"//\s*(\d+)\s*$|,\s*/\s*(\d+)\s*$")


def is_pinyin_token(t: str) -> bool:
    t = t.strip()
    if not t:
        return False
    return bool(PINYIN_RE.match(t))


def parse_line(line: str) -> dict | None:
    line = line.strip()
    if not line or line.startswith("//"):
        return None
    m = re.search(r'"([^"]*)"', line)
    if not m:
        return None
    inner = m.group(1)
    parts = inner.split()
    if not parts:
        return None

    # 第一个拼音音节的位置
    p0 = None
    for i, p in enumerate(parts):
        if is_pinyin_token(p):
            p0 = i
            break
    if p0 is None:
        return None

    # 连续拼音段
    p1 = p0
    while p1 < len(parts) and is_pinyin_token(parts[p1]):
        p1 += 1

    pinyin = " ".join(parts[p0:p1])
    type_parts = parts[p1:]
    answer_type = "".join(type_parts) if type_parts else ""
    # 「神 话」→ 神话；已是单段则不变
    if len(type_parts) > 1 and all(not is_pinyin_token(x) for x in type_parts):
        answer_type = "".join(type_parts)

    head = parts[:p0]
    if len(head) < 2:
        return {"_error": "head < 2 fields", "raw": inner}

    answer = head[0]
    question = head[1]
    tips = " ".join(head[2:]) if len(head) > 2 else ""

    lm = LEVEL_RE.search(line)
    level = None
    if lm:
        level = int(lm.group(1) or lm.group(2))

    return {
        "level": level,
        "question": question,
        "answerLength": len(answer),
        "answer": answer,
        "answerType": answer_type,
        "tips": tips,
        "pinyin": pinyin,
    }


def main():
    text = SRC.read_text(encoding="utf-8")
    rows = []
    errors = []
    for i, line in enumerate(text.splitlines(), 1):
        rec = parse_line(line)
        if rec is None:
            continue
        if "_error" in rec:
            errors.append((i, rec))
            continue
        rows.append(rec)

    if errors:
        for ln, rec in errors:
            print(f"行 {ln}: {rec}")
        raise SystemExit(1)

    OUT.write_text(
        json.dumps(rows, ensure_ascii=False, indent=4) + "\n",
        encoding="utf-8",
    )
    print(f"已写入 {len(rows)} 条 -> {OUT}")


if __name__ == "__main__":
    main()
