#!/usr/bin/env python3

from __future__ import annotations

import ast
import json
import os
import re
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent
APP_DIR = REPO_ROOT / "master_plan_it"
TRANSLATION_FILE = APP_DIR / "locale" / "it.po"


def _po_unquote(value: str) -> str:
    return ast.literal_eval(value)


def get_po_strings(po_path: Path) -> dict[str, str]:
    data: dict[str, str] = {}
    if not po_path.exists():
        return data

    current_msgid: list[str] = []
    current_msgstr: list[str] = []
    mode: str | None = None

    def flush() -> None:
        msgid = "".join(current_msgid)
        msgstr = "".join(current_msgstr)
        if msgid:
            data[msgid] = msgstr

    for raw_line in po_path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()

        if line.startswith("#"):
            continue

        if line.startswith("msgid "):
            if current_msgid or current_msgstr:
                flush()
            current_msgid = [_po_unquote(line[5:].strip())]
            current_msgstr = []
            mode = "msgid"
            continue

        if line.startswith("msgstr "):
            current_msgstr = [_po_unquote(line[6:].strip())]
            mode = "msgstr"
            continue

        if line.startswith('"'):
            if mode == "msgid":
                current_msgid.append(_po_unquote(line))
            elif mode == "msgstr":
                current_msgstr.append(_po_unquote(line))
            continue

        if not line:
            if current_msgid or current_msgstr:
                flush()
            current_msgid = []
            current_msgstr = []
            mode = None

    if current_msgid or current_msgstr:
        flush()

    return data


def get_codebase_strings(app_dir: Path) -> dict[str, list[str]]:
    strings: dict[str, list[str]] = {}
    js_pattern = re.compile(r'__\s*\(\s*(["\'])(.*?)\1')

    for root, _, files in os.walk(app_dir):
        if "node_modules" in root or ".git" in root or "/locale/" in root:
            continue

        for filename in files:
            path = Path(root) / filename
            rel_path = path.relative_to(app_dir)

            if filename.endswith(".py"):
                with path.open("r", encoding="utf-8") as handle:
                    try:
                        tree = ast.parse(handle.read())
                    except Exception:
                        continue
                for node in ast.walk(tree):
                    if not isinstance(node, ast.Call):
                        continue
                    if not isinstance(node.func, ast.Name) or node.func.id != "_":
                        continue
                    if not node.args or not isinstance(node.args[0], ast.Constant):
                        continue
                    text = node.args[0].value
                    if isinstance(text, str):
                        strings.setdefault(text, []).append(f"{rel_path}:{node.lineno}")

            elif filename.endswith(".js"):
                content = path.read_text(encoding="utf-8")
                for index, line in enumerate(content.splitlines(), 1):
                    for _, text in js_pattern.findall(line):
                        strings.setdefault(text, []).append(f"{rel_path}:{index}")

            elif filename.endswith(".json"):
                try:
                    data = json.loads(path.read_text(encoding="utf-8"))
                except Exception:
                    continue

                def extract_json_strings(obj):
                    if isinstance(obj, dict):
                        for key, value in obj.items():
                            if (
                                key in ["label", "description", "message", "title", "subject"]
                                and isinstance(value, str)
                            ):
                                strings.setdefault(value, []).append(f"{rel_path}:{key}")
                            elif key == "options" and isinstance(value, str):
                                continue
                            else:
                                extract_json_strings(value)
                    elif isinstance(obj, list):
                        for item in obj:
                            extract_json_strings(item)

                extract_json_strings(data)

    return strings


def main() -> None:
    code_strings = get_codebase_strings(APP_DIR)
    po_strings = get_po_strings(TRANSLATION_FILE)

    missing_in_po = []
    stale_in_po = []
    untranslated = []

    for text, locations in code_strings.items():
        if text not in po_strings:
            missing_in_po.append((text, locations))

    for text, translation in po_strings.items():
        if text not in code_strings:
            stale_in_po.append(text)
        if not translation or translation == text:
            untranslated.append(text)

    print("=== MISSING TRANSLATIONS (In code, not in PO) ===")
    for text, locations in sorted(missing_in_po):
        print(f"STRING: {text}")
        for location in locations[:3]:
            print(f"  - {location}")
        if len(locations) > 3:
            print(f"  ... and {len(locations) - 3} more")

    print("\n=== UNTRANSLATED (In PO, but same as source or empty) ===")
    for text in sorted(untranslated):
        print(f"- {text}")

    print("\n=== POTENTIALLY STALE (In PO, not found in code) ===")
    print("(Note: regex/AST parsing might miss some dynamic strings)")
    for text in sorted(stale_in_po):
        print(f"- {text}")


if __name__ == "__main__":
    main()
