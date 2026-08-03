# Research — Contracts and projects

Verification date: 2026-08-02. The implementation agent must re-check official documentation if versions have advanced.

| Decision ID | Problem | Decision | Compatibility/source class | Rejected alternative | Revisit condition |
|---|---|---|---|---|---|
| RES-004-001 | Framework baseline | Laravel 13 on PHP 8.3+ | Official Laravel deployment docs | Older Laravel baseline | Security/support change |
| RES-004-002 | Stateful server UI | Blade by default; Livewire 4 only for dynamic project register/editor, contract register/editor with term timeline | Official Livewire lifecycle docs | SPA/Inertia | Proven UX requirement impossible server-side |
| RES-004-003 | UI components | Tailwind 4 + Preline with central re-init adapter | Official Tailwind/Preline docs | second general UI library | Accessibility/maintenance failure |
| RES-004-004 | Tests | Pest feature/unit; Dusk only for browser lifecycle | Official Laravel/Pest docs | full browser-only suite | none |
| RES-004-005 | Hosting | synchronous requests/commands and cron | Laravel scheduler/deployment docs | Redis/worker daemon | hosting contract changes |

## Package ownership

No package may be introduced without listing the exact feature, file and reason here. Laravel standard facilities are mandatory when sufficient. For this feature, third-party runtime packages are limited to Livewire, Preline, Chart.js only where listed in `plan.md`; CSV uses standard streaming. XLSX/PDF remain adapter contracts until selected.
