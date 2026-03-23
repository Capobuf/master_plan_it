# GitHub Copilot Instructions — MPIT

Read `AGENT_INSTRUCTIONS.md` before any task. All rules are there.

**In short:** Frappe v16 app, native Desk only, file-first metadata, no custom JS/CSS, no custom sync pipeline.  
Canonical metadata in `master_plan_it/master_plan_it/`. Apply changes with `bench migrate`.  
Never `git pull` or `pip install` inside a running production container.
