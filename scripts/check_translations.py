#!/usr/bin/env python3
"""
Translation checker: find _()/__() and JSON labels not present in locale/it.po.
"""

from __future__ import annotations

import ast
import json
import re
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
APP_DIR = REPO_ROOT / "master_plan_it"
PO_FILE = APP_DIR / "locale" / "it.po"

PY_PATTERN = re.compile(r'_\(\s*"([^"]+)"\s*\)|_\(\s*\'([^\']+)\'\s*\)')
JS_PATTERN = re.compile(r'__\(\s*"([^"]+)"\s*\)|__\(\s*\'([^\']+)\'\s*\)')
JSON_FIELDS = ["label", "description", "options"]


def _po_unquote(value: str) -> str:
    return ast.literal_eval(value)


def load_existing_translations() -> set[str]:
    """Load msgid entries from it.po."""
    existing: set[str] = set()
    if not PO_FILE.exists():
        return existing

    current_msgid: list[str] = []
    mode: str | None = None

    for raw_line in PO_FILE.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()

        if line.startswith("#"):
            continue

        if line.startswith("msgid "):
            if current_msgid:
                msgid = "".join(current_msgid)
                if msgid:
                    existing.add(msgid)
            current_msgid = [_po_unquote(line[5:].strip())]
            mode = "msgid"
            continue

        if line.startswith("msgstr "):
            mode = "msgstr"
            continue

        if line.startswith('"') and mode == "msgid":
            current_msgid.append(_po_unquote(line))
            continue

        if not line:
            if current_msgid:
                msgid = "".join(current_msgid)
                if msgid:
                    existing.add(msgid)
            current_msgid = []
            mode = None

    if current_msgid:
        msgid = "".join(current_msgid)
        if msgid:
            existing.add(msgid)

    return existing


def find_code_strings(directory: Path) -> dict[str, list[str]]:
    """Find all _()/__() literal strings in .py and .js files."""
    found: dict[str, list[str]] = {}

    for ext, pattern in [(".py", PY_PATTERN), (".js", JS_PATTERN)]:
        for filepath in directory.rglob(f"*{ext}"):
            path_text = str(filepath)
            if "__pycache__" in path_text or "/locale/" in path_text or "node_modules" in path_text:
                continue
            if filepath.name.startswith("test_"):
                continue

            try:
                content = filepath.read_text(encoding="utf-8")
            except Exception:
                continue

            for match in pattern.finditer(content):
                string = match.group(1) or match.group(2)
                rel_path = filepath.relative_to(APP_DIR)
                found.setdefault(string, []).append(str(rel_path))

    return found


def find_json_strings(directory: Path) -> dict[str, list[str]]:
    """Find labels/descriptions/options in JSON files."""
    found: dict[str, list[str]] = {}

    for filepath in directory.rglob("*.json"):
        if "__pycache__" in str(filepath) or "/locale/" in str(filepath):
            continue

        try:
            data = json.loads(filepath.read_text(encoding="utf-8"))
        except Exception:
            continue

        if not isinstance(data, dict):
            continue

        rel_path = filepath.relative_to(APP_DIR)

        for field in JSON_FIELDS:
            val = data.get(field)
            if val and isinstance(val, str) and len(val) > 1:
                found.setdefault(val, []).append(f"{rel_path}:doctype.{field}")

        for docfield in data.get("fields", []):
            if not isinstance(docfield, dict):
                continue
            for field in JSON_FIELDS:
                val = docfield.get(field)
                if val and isinstance(val, str) and len(val) > 1:
                    if field == "options":
                        if "\n" in val or val == "name":
                            continue
                    found.setdefault(val, []).append(
                        f"{rel_path}:{docfield.get('fieldname', '?')}.{field}"
                    )

    return found


def _po_quote(value: str) -> str:
    return value.replace("\\", "\\\\").replace('"', '\\"')


def main() -> None:
    print("=" * 60)
    print("TRANSLATION CHECKER - Master Plan IT")
    print("=" * 60)

    existing = load_existing_translations()
    print(f"\nExisting msgids in locale/it.po: {len(existing)}")

    code_strings = find_code_strings(APP_DIR)
    json_strings = find_json_strings(APP_DIR)

    all_found: dict[str, list[str]] = {}
    for source, locations in code_strings.items():
        all_found.setdefault(source, []).extend(locations)
    for source, locations in json_strings.items():
        all_found.setdefault(source, []).extend(locations)

    print(f"Strings in code: {len(code_strings)}")
    print(f"Strings in JSON: {len(json_strings)}")
    print(f"Total unique strings: {len(all_found)}")

    missing = {s: locs for s, locs in all_found.items() if s not in existing}

    print(f"\nMISSING STRINGS: {len(missing)}")
    print("-" * 60)

    if missing:
        print("\nAdd entries to locale/it.po:\n")
        for string in sorted(missing):
            loc = missing[string][0][:80]
            print(f"#: {loc}")
            print(f'msgid "{_po_quote(string)}"')
            print('msgstr "[TODO]"')
            print("")
    else:
        print("\nAll scanned strings are present in locale/it.po.")

    print("=" * 60)


if __name__ == "__main__":
    main()
