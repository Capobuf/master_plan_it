---
description: Read Frappe v15 documentation based on a topic/prompt, dividing into chunks for complete reading
---

# Read Frappe Documentation Workflow

This workflow allows you to read the Frappe v15 documentation completely, without skipping, based on a specific topic or prompt.

## Documentation Location

The Frappe v15 documentation is located at:
```
/usr/docker/masterplan-project/master-plan-it/docs/_vendor/frappev15/
```

## Documentation Structure

Main directories and their focus areas:

| Directory | Focus Area |
|-----------|------------|
| `api/` | JavaScript & Python API references (dialog, form, document, database, REST, etc.) |
| `basics/` | Core concepts (apps, architecture, doctypes, sites, permissions, asset bundling) |
| `basics/doctypes/` | DocType deep-dive (controllers, naming, child tables, links, actions) |
| `bench/` | Bench CLI commands and management |
| `desk/` | Desk UI components (listview, form scripts, workspace) |
| `guides/` | Comprehensive guides organized by topic |
| `guides/app-development/` | App development patterns |
| `guides/integration/` | External integrations (webhooks, OAuth, REST) |
| `guides/reports-and-printing/` | Reports and print formats |
| `python-api/` | Python-specific API documentation |
| `tutorial/` | Step-by-step tutorial for building apps |

## Workflow Steps

### Step 1: Identify the topic

Based on USER's prompt, identify the relevant documentation area(s). Use this mapping:

- **DocType/Model questions** → `basics/doctypes/`, `basics/doctypes.md`
- **Form/UI questions** → `api/form.md`, `api/dialog.md`, `desk/`
- **Database/Query questions** → `api/database.md`, `api/query-builder.md`
- **REST API questions** → `api/rest.md`, `api/server-calls.md`
- **JavaScript API questions** → `api/`, `api/js-utils.md`
- **Testing questions** → `testing.md`, `guides/automated-testing/`
- **Reports questions** → `guides/reports-and-printing/`
- **Permissions questions** → `basics/users-and-permissions.md`
- **Hooks/Events questions** → `desk/`, `basics/apps.md`
- **Bench/CLI questions** → `bench/`
- **General architecture** → `basics/architecture.md`, `introduction.md`

### Step 2: Search for relevant files

Use the following tools to find documentation files:

```
# Search by keyword in filenames
find_by_name(Pattern="*keyword*", SearchDirectory="/usr/docker/masterplan-project/master-plan-it/docs/_vendor/frappev15", Extensions=["md"])

# Search by content
grep_search(Query="search term", SearchPath="/usr/docker/masterplan-project/master-plan-it/docs/_vendor/frappev15", Includes=["*.md"])
```

### Step 3: Read documentation files completely

For each relevant file, read it completely using chunked reading:

1. **First read**: Use `view_file` without line limits to see the full file
2. **If file is too large (>800 lines)**: Read in chunks of 800 lines maximum

```python
# Reading strategy for large files:
# Chunk 1: view_file(AbsolutePath=path, StartLine=1, EndLine=800)
# Chunk 2: view_file(AbsolutePath=path, StartLine=801, EndLine=1600)
# Continue until file is fully read
```

**IMPORTANT**: Never skip content. Always read the ENTIRE file, even if it seems long. Document knowledge gaps and continue reading.

### Step 4: Index priority files for common topics

For quick reference, here are the most important files per topic:

#### DocTypes & Controllers
1. `/docs/_vendor/frappev15/basics/doctypes.md`
2. `/docs/_vendor/frappev15/basics/doctypes/controllers.md`
3. `/docs/_vendor/frappev15/basics/doctypes/doctype-features.md`
4. `/docs/_vendor/frappev15/basics/doctypes/naming.md`
5. `/docs/_vendor/frappev15/api/document.md`

#### Forms & UI
1. `/docs/_vendor/frappev15/api/form.md`
2. `/docs/_vendor/frappev15/api/dialog.md`
3. `/docs/_vendor/frappev15/api/controls.md`
4. `/docs/_vendor/frappev15/desk/listview.md`
5. `/docs/_vendor/frappev15/desk/form-scripts.md`

#### Database & Queries
1. `/docs/_vendor/frappev15/api/database.md`
2. `/docs/_vendor/frappev15/api/query-builder.md`

#### REST & Server Calls
1. `/docs/_vendor/frappev15/api/rest.md`
2. `/docs/_vendor/frappev15/api/server-calls.md`

#### Reports
1. `/docs/_vendor/frappev15/guides/reports-and-printing/script-report.md`
2. `/docs/_vendor/frappev15/guides/reports-and-printing/query-report.md`

#### Testing
1. `/docs/_vendor/frappev15/testing.md`
2. `/docs/_vendor/frappev15/ui-testing.md`

### Step 5: Summarize findings

After reading all relevant documentation:

1. Create a summary of key points relevant to the USER's prompt
2. Include code examples from the documentation
3. Note any related topics that might be useful
4. If documentation is incomplete for the topic, clearly state what's missing

## Usage Example

**USER prompt**: "Come creo un report script in Frappe?"

**Agent actions**:
1. Identify topic: Reports → `guides/reports-and-printing/`
2. List directory to find all report-related files
3. Read completely (NO SKIPPING):
   - `guides/reports-and-printing/script-report.md`
   - `guides/reports-and-printing/query-report.md`
   - Any other relevant files found
4. Provide comprehensive answer with code examples from docs

## Critical Rules

- **NEVER skip documentation content** - read files completely in chunks if needed
- **Prioritize official documentation** over assumptions
- **Quote relevant sections** when answering
- **Note file paths** so USER can reference them later
- **If documentation is missing**, clearly state this and suggest alternatives
