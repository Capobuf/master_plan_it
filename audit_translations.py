
import os
import re
import csv
import ast

APP_DIR = "/usr/docker/masterplan-project/master-plan-it/master_plan_it"
TRANSLATION_FILE = "/usr/docker/masterplan-project/master-plan-it/master_plan_it/translations/it.csv"

def get_codebase_strings(app_dir):
    strings = {}  # "string": ["file:line", ...]
    
    # Regex for JS: __("string") or __('string')
    # Simple regex, might miss complex cases but good enough for audit
    js_pattern = re.compile(r'__\s*\(\s*(["\'])(.*?)\1')
    
    for root, dirs, files in os.walk(app_dir):
        if "node_modules" in root or ".git" in root:
            continue
            
        for file in files:
            path = os.path.join(root, file)
            rel_path = os.path.relpath(path, app_dir)
            
            if file.endswith(".py"):
                with open(path, "r", encoding="utf-8") as f:
                    try:
                        tree = ast.parse(f.read())
                        for node in ast.walk(tree):
                            if isinstance(node, ast.Call):
                                if isinstance(node.func, ast.Name) and node.func.id == "_":
                                    if node.args and isinstance(node.args[0], ast.Constant):
                                        s = node.args[0].value
                                        if isinstance(s, str):
                                            if s not in strings: strings[s] = []
                                            strings[s].append(f"{rel_path}:{node.lineno}")
                    except Exception as e:
                        # Fallback to regex if AST fails (e.g. syntax error or python2 compat issues, unlikely here)
                         pass

            elif file.endswith(".js"):
                with open(path, "r", encoding="utf-8") as f:
                    content = f.read()
                    for i, line in enumerate(content.splitlines(), 1):
                        matches = js_pattern.findall(line)
                        for quote, s in matches:
                            if s not in strings: strings[s] = []
                            strings[s].append(f"{rel_path}:{i}")

    # Scan JSON files for labels and descriptions
    for root, dirs, files in os.walk(app_dir):
        if "node_modules" in root or ".git" in root:
            continue
        for file in files:
            if file.endswith(".json"):
                 path = os.path.join(root, file)
                 rel_path = os.path.relpath(path, app_dir)
                 try:
                     import json
                     with open(path, "r", encoding="utf-8") as f:
                         data = json.load(f)
                     
                     def extract_json_strings(obj, loc):
                         if isinstance(obj, dict):
                             for k, v in obj.items():
                                 if k in ["label", "description", "message", "title", "subject"] and isinstance(v, str):
                                     if v not in strings: strings[v] = []
                                     strings[v].append(f"{loc}:{k}")
                                 elif k == "options" and isinstance(v, str):
                                      # Options can be translatable if they are select lists, but usually handled via other means.
                                      # But often "options" are just links to other doctypes which are NOT translatable strings in this context.
                                      # However, for Select fields, they are. 
                                      # Let's be conservative and only include them if they look like human text, but maybe skipped for now to avoid false positives (like "User", "Project" which are DocType names).
                                      pass
                                 else:
                                     extract_json_strings(v, loc)
                         elif isinstance(obj, list):
                             for item in obj:
                                 extract_json_strings(item, loc)

                     extract_json_strings(data, rel_path)
                 except:
                     pass
    return strings


def get_csv_strings(csv_path):
    data = {}
    if not os.path.exists(csv_path):
        return data
        
    with open(csv_path, "r", encoding="utf-8") as f:
        reader = csv.reader(f)
        try:
            next(reader) # header
        except StopIteration:
            return data
            
        for row in reader:
            if not row: continue
            source = row[0]
            translated = row[1] if len(row) > 1 else ""
            data[source] = translated
    return data

def main():
    code_strings = get_codebase_strings(APP_DIR)
    csv_strings = get_csv_strings(TRANSLATION_FILE)
    
    missing_in_csv = []
    stale_in_csv = []
    untranslated = []
    
    # Check for missing
    for s, locs in code_strings.items():
        if s not in csv_strings:
            missing_in_csv.append((s, locs))
    
    # Check for stale and untranslated
    for s, trans in csv_strings.items():
        if s not in code_strings:
            # Maybe it's a dynamic string or from a file we missed? 
            # Or maybe checking strictly against code_strings is too harsh if I missed some patterns.
            # But let's report it as potentially stale.
            stale_in_csv.append(s)
        
        if not trans or trans == s:
             untranslated.append(s)

    print("=== MISSING TRANSLATIONS (In code, not in CSV) ===")
    for s, locs in sorted(missing_in_csv):
        print(f"STRING: {s}")
        for l in locs[:3]: # show first 3 locs
            print(f"  - {l}")
        if len(locs) > 3:
            print(f"  ... and {len(locs)-3} more")
    
    print("\n=== UNTRANSLATED (In CSV, but same as source or empty) ===")
    for s in sorted(untranslated):
        print(f"- {s}")

    print("\n=== POTENTIALLY STALE (In CSV, not found in code) ===")
    print("(Note: regex/AST parsing might miss some dynamic strings)")
    for s in sorted(stale_in_csv):
         print(f"- {s}")

if __name__ == "__main__":
    main()
