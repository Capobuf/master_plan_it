# Frontend deployment contract

## Local development

The `frontend` Compose service runs the Vite development server on Node.js 22.
By default it is available at `http://localhost:5173`; `FRONTEND_PORT` may
change the host port.

Browser requests use same-origin relative paths only:

- `/api/v1/...`
- `/sanctum/...`

The Vite development server proxies `/api` and `/sanctum` to
`VITE_INTERNAL_API_PROXY_TARGET`. In Compose its default is
`http://laravel.test`, the private Laravel service on the `sail` network. The
variable is consumed only by `vite.config.ts`; it is excluded from Vite's
client environment and must not be referenced by application code.

Laravel Sanctum must list the browser-facing local origin (including the
configured frontend port) in `SANCTUM_STATEFUL_DOMAINS`. The repository default
covers `localhost:5173` and `127.0.0.1:5173`; if `FRONTEND_PORT` changes, the
stateful-domain setting must be changed to match.

## Production network boundary

The target production request path is:

```text
Browser
  -> public frontend Node site
  -> same-origin /api and /sanctum proxy
  -> API_INTERNAL_ORIGIN=http://127.0.0.1:<private-port>
  -> Laravel API
```

`API_INTERNAL_ORIGIN` is server-only configuration. It must never be included
in a browser bundle or returned to the browser. The browser must not call a
public API hostname or a loopback address; all API and Sanctum traffic remains
same-origin at the public frontend site.

The production Node server/reverse-proxy implementation is intentionally
deferred to the operational deployment phase. `npm run build` validates and
creates the production bundle, but Laravel does not serve that bundle and it
must not be copied into the Laravel application. `vite preview` is not a
production server.

The production proxy must remain transport-only: it must not contain business
rules, duplicate Laravel validation or permissions, or require wildcard CORS.
Laravel remains a private API service rather than a mandatory public API
domain.
