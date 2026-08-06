# TailAdmin native UI implementation standard

Status: `NORMATIVE`

## Scope

This standard applies to every application surface in this branch. The branch has one application UI stack: the official TailAdmin Laravel Free Blade components, Tailwind CSS 4 and TailAdmin's native Alpine methods.

Preline, Livewire, Filament UI and Inertia/React application views are not part of this branch and must not be introduced as alternative frontend stacks.

## Boundaries

- All application pages, layouts, forms, tables, dialogs, navigation, feedback and data visualizations are TailAdmin Laravel Blade/Tailwind UI.
- Backend policies, Actions, Queries, DTOs, authorization, tenant context and server-calculated presentation payloads remain framework-owned.
- Alpine is limited to the interaction methods supplied by or composed directly from the official TailAdmin Laravel Free source.

## Mandatory implementation rule

1. Use only official TailAdmin Laravel Free Blade components, patterns and Alpine methods when TailAdmin provides a suitable capability.
2. Before writing UI code, search the official TailAdmin Laravel Free source template for the closest fit.
3. Do not hand-write a duplicate of an existing TailAdmin primitive and do not introduce another component library or parallel design system for a TailAdmin-owned surface.
4. Application code may bind domain data, Laravel routes, authorization and server state to component props and form actions. It must not replace the selected native TailAdmin primitive.
5. If no native TailAdmin fit exists, record an explicit exception in the owning plan/task before implementation: capability searched, alternatives considered, selected fallback, reason, scope and removal/revisit condition. The exception must remain the smallest possible composition around native TailAdmin primitives.

## Forbidden alternatives

- Preline components, plugins, lifecycle helpers or CDN assets.
- Livewire components, Livewire lifecycle hooks or Livewire-specific application UI tests.
- Inertia or React application pages and layouts.
- Filament UI pages/resources as a second application frontend.

## Required validation

Every TailAdmin UI task must verify the native component source path, Blade compilation, the production Vite build and relevant browser behavior at 360, 768 and 1280 CSS pixels. Theme work must verify both light and dark states, persistence across reload/navigation, keyboard access, visible focus and contrast.

## Traceability

Feature plans reference this standard. A task that intentionally deviates must link its exception record from the task and its focused validation.

## Free source baseline

- Repository: `TailAdmin/tailadmin-laravel` (MIT), commit `13ce4efa25d0f5ba156697b4cb5f101fc8c232a9`.
- Native source patterns used: `layouts/app.blade.php`, `layouts/sidebar.blade.php`, `components/common/page-breadcrumb.blade.php`, `components/common/dropdown-menu.blade.php`, `components/ui/button.blade.php`, `components/ui/badge.blade.php`, `components/ui/modal.blade.php`, `components/profile/*`, and `components/ecommerce/*`.
- The Free repository does not include the Marketing or Finance dashboard source. The application dashboard therefore composes the available Free Ecommerce cards/charts with server-calculated MasterPlan data; it does not copy paid/demo-only markup or invent demo data.
