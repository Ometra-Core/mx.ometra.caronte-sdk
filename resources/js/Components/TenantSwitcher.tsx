import { useId } from "react";

import type { TenantOption, TenantSwitcherProps } from "../types";

function tenantUrl(tenantId: string): string {
  const url = new URL(window.location.href);
  url.searchParams.set("id_tenant", tenantId);

  return url.toString();
}

/**
 * Tenant selector molecule for Inertia/React applications.
 *
 * The selected tenant is kept in the URL so each browser tab owns its tenant
 * context. Consumers can use `onTenantChange` to update the `X-Tenant-Id`
 * header in their AJAX client before navigation.
 */
export default function TenantSwitcher({
  tenants,
  currentTenantId,
  label = "Tenant",
  className = "",
  disabled = false,
  onTenantChange,
}: TenantSwitcherProps) {
  const generatedId = useId();
  const selectId = `caronte-tenant-switcher-${generatedId.replace(/:/g, "")}`;
  const selectedTenantId =
    tenants.find((tenant) => tenant.id_tenant === currentTenantId)?.id_tenant ??
    tenants[0]?.id_tenant ??
    "";

  function handleChange(event: React.ChangeEvent<HTMLSelectElement>) {
    const tenantId = event.currentTarget.value;
    const tenant = tenants.find((candidate) => candidate.id_tenant === tenantId);

    if (!tenant) {
      return;
    }

    onTenantChange?.(tenantId, tenant);
    window.location.assign(tenantUrl(tenantId));
  }

  return (
    <div className={`caronte-tenant-switcher ${className}`.trim()}>
      <label htmlFor={selectId} className="caronte-tenant-switcher__label">
        {label}
      </label>
      <select
        id={selectId}
        className="form-select caronte-tenant-switcher__select"
        value={selectedTenantId}
        onChange={handleChange}
        disabled={disabled || tenants.length < 2}
        aria-label={label}
      >
        {tenants.length === 0 ? (
          <option value="">No tenants available</option>
        ) : (
          tenants.map((tenant: TenantOption) => (
            <option key={tenant.id_tenant} value={tenant.id_tenant}>
              {tenant.name}
            </option>
          ))
        )}
      </select>
    </div>
  );
}
