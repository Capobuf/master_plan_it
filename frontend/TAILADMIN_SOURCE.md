# TailAdmin upstream source

- Upstream: https://github.com/TailAdmin/free-react-tailwind-admin-dashboard
- Exact commit: `21dc917cb6cb22b5f1d12e5af57359a849d19aa8`
- Upstream version: `2.3.0`
- License: MIT (`LICENSE.md`)
- Imported on: 2026-08-08

## Imported files

The frontend foundation was copied directly from the checked-out upstream commit:

- `public/`
- `src/`
- `index.html`
- `package.json`
- `package-lock.json`
- `vite.config.ts`
- `tsconfig.app.json`
- `tsconfig.json`
- `tsconfig.node.json`
- `eslint.config.js`
- `postcss.config.js`
- `.gitignore`
- `LICENSE.md`

## Upstream modifications

The imported structure and all unused TailAdmin components remain available.
The following upstream files have intentional, scoped changes:

- `package.json`: added Axios as the single HTTP client dependency required by the Laravel Sanctum integration.
- `package-lock.json`: locked Axios and its transitive dependencies; npm also synchronized the lockfile root version to the upstream package version (`2.3.0`).
- `vite.config.ts`: added the Node-side development proxy for relative `/api` and `/sanctum` requests and restricted browser-exposed environment variables.
- `src/App.tsx`: reduced routes to the implemented sign-in, protected dashboard, and not-found surfaces and installed the auth/application providers.
- `src/components/auth/SignInForm.tsx`: connected the stock TailAdmin form primitives to the Sanctum login flow and removed unsupported demo, signup, social-login, remember-me, and password-recovery controls.
- `src/components/form/input/InputField.tsx`: added passthrough for the native `autocomplete` attribute used by the sign-in fields.
- `src/layout/AppHeader.tsx`: replaced unsupported demo search/notifications with the application tenant and user dropdowns while retaining the TailAdmin shell.
- `src/components/header/UserDropdown.tsx`: replaced demo identity/profile links with current-user data and API logout.
- `src/layout/AppSidebar.tsx`: replaced demo navigation with the local ability-filtered route configuration.
- `src/pages/Dashboard/Home.tsx`: replaced ecommerce demo data with the protected API connectivity surface using stock TailAdmin content components.
- `src/pages/AuthPages/SignIn.tsx`, `src/pages/AuthPages/AuthPageLayout.tsx`, and `src/pages/OtherPage/NotFound.tsx`: changed application labels and metadata only.
- `src/components/ecommerce/CountryMap.tsx`, `src/pages/Calendar.tsx`, and `src/svg.d.ts`: type-only corrections required for the upstream ESLint gate; runtime UI behavior is unchanged.

New application-specific API, context, navigation, guard, tenant-dropdown, and deployment files do not modify generic TailAdmin primitives.
