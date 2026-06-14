import json
import re
from pathlib import Path

base = Path(r"e:\php\study\pun\game2")
a_path = base / "a.json"
ans_path = base / "issue2-answer.json"
noans_path = base / "issue2.json"
php_path = base / "pun_levels_issue2_static.php"

start, end = 189, 252
prefixes = {"仓薯", "糸糸", "沧海一声啸", "匿名作者", "画画的思诺", "淮", "小Y"}
pinyin_re = re.compile(r"^[a-zA-ZvüÜ]+\d$")

lines = a_path.read_text(encoding="utf-8", errors="ignore").splitlines()
new_rows = []

for ln in range(start, end + 1):
    if ln - 1 >= len(lines):
        continue
    line = lines[ln - 1].strip()
    m = re.search(r'"([^"]+)"\s*,?\s*//\s*(\d+)', line)
    if not m:
        continue

    raw = m.group(1).strip()
    level = int(m.group(2))
    tokens = raw.split()

    while tokens and (tokens[0] in prefixes or re.fullmatch(r"[A-Za-z]", tokens[0])):
        tokens = tokens[1:]

    p0 = None
    for i, t in enumerate(tokens):
        if pinyin_re.match(t):
            p0 = i
            break
    if p0 is None or p0 < 2:
        continue

    p1 = p0
    while p1 < len(tokens) and pinyin_re.match(tokens[p1]):
        p1 += 1

    head = tokens[:p0]
    if len(head) < 2:
        continue

    answer = head[0]
    question = head[1]
    tips = " ".join(head[2:]) if len(head) > 2 else question
    answer_type = "".join(tokens[p1:]) if p1 < len(tokens) else "词语"

    new_rows.append(
        {
            "level": level,
            "question": question,
            "answerLength": len(answer),
            "answer": answer,
            "answerType": answer_type,
            "tips": tips,
            "pinyin": " ".join(tokens[p0:p1]),
        }
    )

arr = json.loads(ans_path.read_text(encoding="utf-8"))
by_level = {x["level"]: x for x in arr if isinstance(x, dict) and "level" in x}
for row in new_rows:
    by_level[row["level"]] = row

merged = [by_level[k] for k in sorted(by_level.keys())]
ans_path.write_text(json.dumps(merged, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

no_answer = []
for r in merged:
    t = dict(r)
    t.pop("answer", None)
    no_answer.append(t)
noans_path.write_text(json.dumps(no_answer, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

php_lines = ["<?php", "", "return ["]
for r in merged:
    lv = r.get("level", 0)
    if not isinstance(lv, int) or lv < 1:
        continue
    ans = str(r.get("answer", ""))
    chars = [c.replace("\\", "\\\\").replace("'", "\\'") for c in ans]
    php_lines.append(f"    {lv} => [" + ",".join([f"'{c}'" for c in chars]) + "],")
php_lines.append("];")
php_path.write_text("\n".join(php_lines) + "\n", encoding="utf-8")

print(f"parsed_new={len(new_rows)} merged_total={len(merged)} max_level={max(x['level'] for x in merged)}")
