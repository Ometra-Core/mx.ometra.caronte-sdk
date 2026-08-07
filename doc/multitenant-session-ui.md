# Multitenant session UI

The SDK provides a tenant switcher for Blade and React applications. Both
variants write `id_tenant` to the current URL and preserve the other query
parameters. Keeping the tenant in the URL means separate browser tabs can use
different tenants from the same authenticated session.

The host application must supply only the tenants authorized for the current
application. Each option has this shape:

```json
{"id_tenant":"tenant-uuid","name":"Acme Mexico"}
```

## Blade

Publish or load the package views and render the anonymous component with the
available tenants and the tenant resolved for the current request:

```blade
<x-caronte::tenant-switcher
    :tenants="$caronteTenants"
    :current-tenant-id="$currentTenantId"
    :action="url()->current()"
    class="mb-3"
/>
```

`action` is optional and defaults to the current URL. The component submits a
GET form, preserves existing scalar and list query parameters, and replaces
`id_tenant`. It includes a submit button fallback when JavaScript is disabled.

## React / Inertia

Import the molecule directly from the SDK source copied or exposed by the host
application's asset pipeline:

```tsx
import TenantSwitcher from "vendor/ometra/caronte-sdk/resources/js/Components/TenantSwitcher";

<TenantSwitcher
  tenants={tenants}
  currentTenantId={currentTenantId}
  onTenantChange={(tenantId) => {
    api.defaults.headers.common["X-Tenant-Id"] = tenantId;
  }}
/>
```

The callback runs before browser navigation and is intended to update the
default `X-Tenant-Id` header used by AJAX calls. The component then navigates
to the same URL with the new `id_tenant` value. Do not store the selected
tenant as a single global server-side session value; the URL or header is the
request-specific source of truth.

Both variants use a native labelled `select`, disable it when fewer than two
choices exist, and display an explicit empty state when no tenant is supplied.

## Atomic Design classification

`TenantSwitcher` is a **molecule**: it combines a label, select control, URL
state transition, and progressive-enhancement behavior into one reusable UI
unit. Pages and layouts remain responsible for its placement.
