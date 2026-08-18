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

Publish the SDK resources after every SDK update, then import the molecule from
the published namespace:

```bash
php artisan caronte:ui:update
```

```tsx
import TenantSwitcher from "@/vendor/caronte/Components/TenantSwitcher";
import type { CarontePageProps } from "@/vendor/caronte/types";

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

Both variants use a native labelled `select` and render nothing when fewer than
two choices exist. React consumers can use `onTenantChange` to close the host
menu before navigation. The SDK owns URL mutation and full-page navigation;
host wrappers own only placement and visual classes.

Changing tenant replaces only `id_tenant` and preserves the current path,
other query parameters, and browser fragment. The full reload reconstructs
permissions and application data for the selected tenant.

## Atomic Design classification

`TenantSwitcher` is a **molecule**: it combines a label, select control, URL
state transition, and progressive-enhancement behavior into one reusable UI
unit. Pages and layouts remain responsible for its placement.
