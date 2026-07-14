@extends('caronte::layouts.base')

@php($branding = $branding ?? config('caronte.ui.branding', []))
@php($tenantOptions = $tenant_options ?? [])
@php($pendingLogin = is_array($pending_login ?? null) ? $pending_login : null)
@php($isTenantSelection = !empty($tenantOptions) && is_array($pendingLogin) && !empty($pendingLogin['email']))

@section('content')
    <section class="caronte-auth">
        <div class="caronte-auth__panel">
            <span class="caronte-kicker">{{ data_get($branding, 'app_name', config('app.name')) }}</span>
            <h1 class="caronte-title">{{ data_get($branding, 'headline', 'Secure access with Caronte') }}</h1>
            <p class="caronte-copy">{{ data_get($branding, 'subheadline', 'Authenticate users and administer access from a polished package surface.') }}</p>

            <div class="caronte-card">
                <div class="caronte-card__header">
                    <h2>Sign in</h2>
                    <p>Use your Caronte credentials to continue.</p>
                </div>

                <form method="POST" action="{{ $routes['login'] }}" class="caronte-form">
                    @csrf
                    @if ($callback_url)
                        <input type="hidden" name="callback_url" value="{{ $callback_url }}">
                    @endif

                    <div>
                        <label for="email" class="form-label">Email</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ $isTenantSelection ? $pendingLogin['email'] : old('email') }}"
                            class="form-control"
                            required
                            @readonly($isTenantSelection)
                            autofocus
                        >
                    </div>

                    @unless ($isTenantSelection)
                        <div>
                            <label for="password" class="form-label">Password</label>
                            <input id="password" type="password" name="password" class="form-control" required>
                        </div>
                    @endunless

                    @if (!empty($tenantOptions))
                        <div>
                            <label for="tenant_id" class="form-label">Tenant</label>
                            <select id="tenant_id" name="tenant_id" class="form-control" required>
                                <option value="">Select tenant</option>
                                @foreach ($tenantOptions as $tenant)
                                    <option value="{{ $tenant['tenant_id'] }}" @selected(old('tenant_id') === $tenant['tenant_id'])>
                                        {{ $tenant['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <button type="submit" class="btn caronte-btn-primary">Continue</button>
                </form>

                <div class="caronte-card__footer">
                    <a href="{{ $routes['passwordRecoverForm'] }}">Forgot your password?</a>
                </div>
            </div>
        </div>
    </section>
@endsection
