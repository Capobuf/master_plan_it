# TailAdmin native UI implementation standard

Status: `NORMATIVE`

## Scope

This standard applies to every application surface in this branch. The branch has one application UI stack: Inertia 3 + React 19 + Tailwind CSS 4 + native TailAdmin React components and methods. `resources/views/app.blade.php` is retained only as the technical Inertia mount root; it is not an application page, layout, or component.

Preline, Livewire, Alpine-driven UI, Blade application pages/layouts, and Filament UI are not part of this branch and must not be introduced as alternative frontend stacks.

## Boundaries

- All application pages, layouts, forms, tables, dialogs, navigation, feedback and data visualizations are React/Tailwind UI.
- Backend policies, Actions, Queries, DTOs, authorization, tenant context and server-calculated presentation payloads remain framework-owned.
- The Inertia root Blade file is infrastructure only and must contain no application UI.

## Mandatory implementation rule

1. Use only official TailAdmin React components, patterns and methods when TailAdmin provides a suitable capability. This includes documented component variants, composition patterns, theme context/hooks, responsive layout patterns and accessibility behavior.
2. Before writing UI code, search the official TailAdmin React component catalogue, documentation and source template for the closest fit. Compare the available variants and choose the best fit for the required behavior, responsive layout, accessibility and existing application flow.
3. Do not hand-write a duplicate of an existing TailAdmin primitive and do not introduce another component library or parallel design system for a TailAdmin-owned surface.
4. Application-specific adapters are allowed only for domain data, Inertia routing, authorization, server state and feature orchestration. Their visual primitive must delegate to the selected native TailAdmin component.
5. If no native TailAdmin fit exists, record an explicit exception in the owning plan/task before implementation: capability searched, alternatives considered, selected fallback, reason, scope and removal/revisit condition. The exception must remain the smallest possible composition around native TailAdmin primitives.

## Forbidden alternatives

- Preline components, plugins, lifecycle helpers or CDN assets.
- Livewire components, Livewire lifecycle hooks or Livewire-specific application UI tests.
- Blade application pages, layouts, partials or feature views.
- Alpine state for application interactions.
- Filament UI pages/resources as a second application frontend.

## Required validation

Every TailAdmin UI task must verify the native component import/source path, `npx tsc --noEmit`, the production Vite build and the relevant browser behavior at 360, 768 and 1280 CSS pixels. Theme work must verify both light and dark states, persistence across reload/navigation, keyboard access, visible focus and contrast.

## Traceability

Feature plans reference this standard. A task that intentionally deviates must link its exception record from the task and its focused validation.
