#!/usr/bin/env python3
"""
Translation Checker: trova stringhe _(), __() e JSON labels non presenti in it.csv
"""
import csv
import json
import os
import re
from pathlib import Path

BASE_DIR = Path("/usr/docker/masterplan-project/master-plan-it/master_plan_it")
TRANSLATIONS_FILE = BASE_DIR / "translations/it.csv"

# Pattern per stringhe traducibili (gestisce apostrofi interni)
PY_PATTERN = re.compile(r'_\(\s*"([^"]+)"\s*\)|_\(\s*\'([^\']+)\'\s*\)')
JS_PATTERN = re.compile(r'__\(\s*"([^"]+)"\s*\)|__\(\s*\'([^\']+)\'\s*\)')

# Campi JSON da tradurre
JSON_FIELDS = ['label', 'description', 'options']

def load_existing_translations() -> set:
    """Carica le stringhe già tradotte da it.csv"""
    existing = set()
    if TRANSLATIONS_FILE.exists():
        with open(TRANSLATIONS_FILE, 'r', encoding='utf-8') as f:
            reader = csv.reader(f)
            for row in reader:
                if row:
                    existing.add(row[0])
    return existing

def find_code_strings(directory: Path) -> dict:
    """Trova tutte le stringhe _() e __() nei file .py e .js"""
    found = {}
    
    for ext, pattern in [('.py', PY_PATTERN), ('.js', JS_PATTERN)]:
        for filepath in directory.rglob(f'*{ext}'):
            if '__pycache__' in str(filepath) or 'test_' in filepath.name:
                continue
            try:
                content = filepath.read_text(encoding='utf-8')
                for match in pattern.finditer(content):
                    string = match.group(1) or match.group(2)
                    rel_path = filepath.relative_to(BASE_DIR)
                    if string not in found:
                        found[string] = []
                    found[string].append(str(rel_path))
            except Exception:
                pass
    return found

def find_json_strings(directory: Path) -> dict:
    """Trova label/description/options nei JSON DocType"""
    found = {}
    
    for filepath in directory.rglob('*.json'):
        if '__pycache__' in str(filepath):
            continue
        try:
            data = json.loads(filepath.read_text(encoding='utf-8'))
            rel_path = filepath.relative_to(BASE_DIR)
            
            # DocType level
            for field in JSON_FIELDS:
                val = data.get(field)
                if val and isinstance(val, str) and len(val) > 1:
                    found.setdefault(val, []).append(f"{rel_path}:doctype.{field}")
            
            # Fields level
            for f in data.get('fields', []):
                for field in JSON_FIELDS:
                    val = f.get(field)
                    if val and isinstance(val, str) and len(val) > 1:
                        # Skip options with newlines (select values)
                        if field == 'options' and '\n' in val:
                            continue
                        found.setdefault(val, []).append(f"{rel_path}:{f.get('fieldname','?')}.{field}")
        except Exception:
            pass
    return found

def main():
    print("=" * 60)
    print("TRANSLATION CHECKER - Master Plan IT")
    print("=" * 60)
    
    existing = load_existing_translations()
    print(f"\n📚 Traduzioni esistenti in it.csv: {len(existing)}")
    
    code_strings = find_code_strings(BASE_DIR)
    json_strings = find_json_strings(BASE_DIR)
    
    # Merge
    all_found = {}
    for s, locs in code_strings.items():
        all_found.setdefault(s, []).extend(locs)
    for s, locs in json_strings.items():
        all_found.setdefault(s, []).extend(locs)
    
    print(f"🔍 Stringhe nel codice: {len(code_strings)}")
    print(f"🔍 Stringhe nei JSON: {len(json_strings)}")
    print(f"🔍 Totale uniche: {len(all_found)}")
    
    missing = {s: locs for s, locs in all_found.items() if s not in existing}
    
    print(f"\n⚠️  STRINGHE MANCANTI: {len(missing)}")
    print("-" * 60)
    
    if missing:
        print("\n📝 Aggiungi a it.csv:\n")
        for string in sorted(missing.keys()):
            loc = missing[string][0][:50]  # First location, truncated
            escaped = string.replace('"', '""')
            print(f'"{escaped}","[TODO]","# {loc}"')
    else:
        print("\n✅ Tutte le stringhe sono già tradotte!")
    
    print("\n" + "=" * 60)

if __name__ == "__main__":
    main()

