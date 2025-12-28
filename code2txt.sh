#!/bin/bash

#!/usr/bin/env bash
set -euo pipefail

# default output file
OUTPUT_FILE="full_codebase_llm.txt"
DRY_RUN=0
# use pattern '.[!.]*' to match hidden entries but not '.' or '..'
EXCLUDE_DIRS=".[!.]*,data"

print_help() {
    cat <<'EOF'
Usage: code2txt.sh [--prune] [--output FILE] [--exclude DIR1,DIR2]

Options:
  --prune            Dry-run: list files that would be included (no output written)
  --output FILE      Path to output file (default: full_codebase_llm.txt)
  --exclude LIST     Comma-separated list of directory basenames to prune (default: ".[!.]*,data")
  -h, --help         Show this help

Examples:
  ./code2txt.sh --prune
  ./code2txt.sh --output my_dump.txt --exclude "node_modules,.venv"
EOF
}

# parse args
while [[ $# -gt 0 ]]; do
    case "$1" in
        --prune)
            DRY_RUN=1
            shift
            ;;
        --output|-o)
            OUTPUT_FILE="$2"
            shift 2
            ;;
        --exclude|-x)
            EXCLUDE_DIRS="$2"
            shift 2
            ;;
        -h|--help)
            print_help
            exit 0
            ;;
        *)
            echo "Unknown argument: $1" >&2
            print_help
            exit 2
            ;;
    esac
done

# quick binary extension list to skip
EXCLUDE_EXTS="png|jpg|jpeg|gif|ico|pdf|zip|tar|gz|exe|bin|pyc|o|so|dll|woff|woff2|ttf|eot"

# build array of exclude dir basenames
IFS=',' read -r -a EXCLUDES <<< "$EXCLUDE_DIRS"

# sanity check
if ! command -v file >/dev/null 2>&1; then
    echo "Error: 'file' command is required but not found." >&2
    exit 1
fi

# prepare find arguments to safely handle any dir names
find_args=(.)
find_args+=(\()
for d in "${EXCLUDES[@]}"; do
    # trim whitespace
    d_trimmed="$(echo "$d" | xargs)"
    if [[ -n "$d_trimmed" ]]; then
        find_args+=(-type d -name "$d_trimmed" -o)
    fi
done
# remove trailing -o
unset 'find_args[${#find_args[@]}-1]'
find_args+=(\) -prune -o -type f -print0)

# show summary
if [[ $DRY_RUN -eq 1 ]]; then
    echo "Modalità dry-run: mostro i file che verrebbero inclusi (nessun file scritto)."
else
    echo "Generazione file per LLM in corso..."
    echo "Output: $OUTPUT_FILE"
fi

echo "Escludendo le directory: ${EXCLUDES[*]}"

# If not dry-run, remove existing output file
if [[ $DRY_RUN -ne 1 ]]; then
    rm -f "$OUTPUT_FILE"
fi

count=0

# run find and process files safely (null-separated)
while IFS= read -r -d '' file; do
    # skip output file itself (compare resolved path)
    # Use realpath if available
    resolved_file="$file"
    if command -v realpath >/dev/null 2>&1; then
        resolved_file="$(realpath -- "$file")"
        resolved_output="$(realpath -- "$OUTPUT_FILE" 2>/dev/null || echo "")"
    else
        resolved_output="$OUTPUT_FILE"
    fi

    if [[ -n "$resolved_output" && "$resolved_file" == "$resolved_output" ]]; then
        continue
    fi

    # skip the script itself
    if [[ "$file" == "./$(basename "$0")" || "$resolved_file" == "$(pwd)/$(basename "$0")" ]]; then
        continue
    fi

    # quick extension-based skip (case-insensitive)
    ext="${file##*.}"
    ext_lc="$(printf '%s' "$ext" | tr '[:upper:]' '[:lower:]')"
    if [[ "$ext_lc" =~ ^($EXCLUDE_EXTS)$ ]]; then
        continue
    fi

    # final mime-type check
    mime="$(file -b --mime-type -- "$file" 2>/dev/null || echo 'application/octet-stream')"
    if [[ "$mime" == text/* || "$mime" == application/json || "$mime" == application/xml || "$mime" == inode/x-empty || "$mime" == *+xml ]]; then
        if [[ $DRY_RUN -eq 1 ]]; then
            printf "Would add: %s\n" "$file"
            count=$((count+1))
            continue
        fi

        echo "Aggiungendo: $file"
        printf '%s\n' "================================================================================" >> "$OUTPUT_FILE"
        printf ' FILE: %s\n' "$file" >> "$OUTPUT_FILE"
        printf '%s\n' "================================================================================" >> "$OUTPUT_FILE"
        # append file content
        cat -- "$file" >> "$OUTPUT_FILE" || true
        printf '\n\n' >> "$OUTPUT_FILE"
        count=$((count+1))
    fi

done < <(find "${find_args[@]}")

if [[ $DRY_RUN -eq 1 ]]; then
    echo "\nDry-run completato. File candidati: $count"
    exit 0
else
    echo "----------------------------------------------------------------"
    echo "Completato! File inclusi: $count"
    if [[ -f "$OUTPUT_FILE" ]]; then
        echo "Dimensione totale: $(du -h "$OUTPUT_FILE" | cut -f1)"
    fi
fi
