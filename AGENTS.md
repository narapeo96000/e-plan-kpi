AGENTS.md — AI Coding Agent Instructions (minimal)

Purpose
-------
This file gives concise, actionable guidance for AI coding agents working in this PHP web project. Keep it minimal: link to project docs and highlight conventions/tooling that aren't obvious.

Quick Start
-----------
- Run locally: `php -S localhost:8000` from the repository root and open http://localhost:8000
- Database: project uses plain SQL files in the repo — `office_budget_edu_db.sql` (full schema, for fresh installs) and `migration_upgrade.sql` (idempotent upgrade for existing databases). Use your own MySQL instance and import as needed.

Key files
---------
- [index.php](index.php): Application entry / router for simple pages.
- [db.php](db.php): Database connection and credentials handling (first place to check DB-related issues).
- [login.php](login.php), [logout.php](logout.php), [profile.php](profile.php): Authentication and user area.
- [projects.php](projects.php), [project_form.php](project_form.php), [project_save.php](project_save.php): Main CRUD flows for projects.
- [style.php](style.php): Central stylesheet include.

Conventions & notes for agents
-----------------------------
- The codebase is a small PHP app without a framework; prefer minimal, conservative edits.
- Do not assume Composer, NPM, or other build tools are present unless a lockfile or config exists.
- Preserve existing variable naming and HTML layout patterns; prefer in-place edits over broad refactors.
- When adding or changing DB schema, update the included SQL files and explain migration steps.

Suggested agent responsibilities
------------------------------
- Fix small bugs, implement requested form validation, and add unit-style test scaffolding if requested.
- Create focused instructions or skills if working on a specific subsystem (auth, projects, exports).

Next steps for humans / agents
----------------------------
- If you want richer agent behavior, create `.github/copilot-instructions.md` or targeted skill files for specific areas (auth, DB, frontend). Prefer linking existing docs rather than duplicating them.
- Ask the repo owner for DB credentials or test data when changes require running the app.

Contact
-------
If unclear, request clarification in the PR description or issue before making structural changes.
