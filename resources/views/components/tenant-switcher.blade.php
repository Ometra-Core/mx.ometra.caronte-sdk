@props([
    'tenants' => [],
    'currentTenantId' => null,
    'action' => null,
    'label' => 'Tenant',
])

@php
    $tenantOptions = collect($tenants);
    $selectedTenantId = $tenantOptions->contains(
        fn ($tenant) => (string) data_get($tenant, 'id_tenant') === (string) $currentTenantId
    ) ? (string) $currentTenantId : (string) data_get($tenantOptions->first(), 'id_tenant', '');
    $preservedQuery = request()->except('id_tenant');
    $selectId = 'caronte-tenant-switcher-'.$attributes->get('id', 'default');
@endphp

@if ($tenantOptions->count() >= 2)
<form
    method="GET"
    action="{{ $action ?? request()->url() }}"
    {{ $attributes->except('id')->class(['caronte-tenant-switcher']) }}
>
    @foreach ($preservedQuery as $name => $value)
        @if (is_scalar($value))
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @elseif (is_array($value))
            @foreach ($value as $nestedValue)
                @if (is_scalar($nestedValue))
                    <input type="hidden" name="{{ $name }}[]" value="{{ $nestedValue }}">
                @endif
            @endforeach
        @endif
    @endforeach

    <label for="{{ $selectId }}" class="caronte-tenant-switcher__label">{{ $label }}</label>
    <select
        id="{{ $selectId }}"
        name="id_tenant"
        class="form-select caronte-tenant-switcher__select"
        onchange="const url = new URL(window.location.href); url.searchParams.set('id_tenant', this.value); window.location.assign(url.toString())"
    >
        @foreach ($tenantOptions as $tenant)
            <option
                value="{{ data_get($tenant, 'id_tenant') }}"
                @selected((string) data_get($tenant, 'id_tenant') === $selectedTenantId)
            >
                {{ data_get($tenant, 'name', data_get($tenant, 'id_tenant')) }}
            </option>
        @endforeach
    </select>

    <noscript>
        <button type="submit" class="btn caronte-btn-secondary">Change tenant</button>
    </noscript>
</form>
@endif
