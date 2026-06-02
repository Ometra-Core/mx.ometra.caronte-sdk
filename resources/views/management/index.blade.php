@extends('caronte::layouts.base')

@php
    $branding = $branding ?? config('caronte.ui.branding', []);
@endphp

@section('body_class', 'caronte-management-shell')

@section('content')
    <div class="container py-4 py-lg-5">
        <div class="caronte-management-header mb-4">
            <div>
                <span class="caronte-kicker">{{ data_get($branding, 'app_name', config('app.name')) }}</span>
                <h1 class="caronte-title mb-2">User management</h1>
                <p class="caronte-copy mb-0">Tenant <strong>{{ $tenant_id }}</strong>. Roles are defined locally in <code>config/caronte.php</code> and synchronized explicitly.</p>
            </div>

            <form method="POST" action="{{ $routes['logout'] }}">
                @csrf
                <button type="submit" class="btn caronte-btn-secondary">Sign out</button>
            </form>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-8">
                <div class="caronte-card">
                    <div class="caronte-card__header">
                        <h2>Application users</h2>
                        <p>Search, review, and navigate to the detail view for each tenant-scoped user.</p>
                    </div>

                    <form method="GET" action="{{ $routes['dashboard'] }}" class="row g-3 align-items-end mb-4">
                        <div class="col-md-8">
                            <label for="search" class="form-label">Search users</label>
                            <input id="search" type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Name or email">
                        </div>
                        <div class="col-md-4 d-grid">
                            <button type="submit" class="btn caronte-btn-secondary">Apply filters</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($users as $user)
                                    <tr>
                                        <td>{{ $user['name'] ?? '' }}</td>
                                        <td>{{ $user['email'] ?? '' }}</td>
                                        <td class="text-end">
                                            <a href="{{ str_replace('__USER__', $user['uri_user'], $routes['usersShow']) }}" class="btn btn-sm caronte-btn-secondary">Manage</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">No users matched your current filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if (method_exists($users, 'links'))
                        <div class="mt-3">
                            {{ $users->links() }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="caronte-card mb-4">
                    <div class="caronte-card__header">
                        <h2>Sync configured roles</h2>
                        <p>Remote role definitions should match local package configuration.</p>
                    </div>

                    <div class="caronte-status-list">
                        <div>
                            <span class="caronte-status-list__label">Configured roles</span>
                            <strong>{{ count($configured_roles) }}</strong>
                        </div>
                        <div>
                            <span class="caronte-status-list__label">Missing remotely</span>
                            <strong>{{ count($missing_roles) }}</strong>
                        </div>
                        <div>
                            <span class="caronte-status-list__label">Descriptions outdated</span>
                            <strong>{{ count($outdated_roles) }}</strong>
                        </div>
                    </div>

                    <form method="POST" action="{{ $routes['rolesSync'] }}" class="mt-4">
                        @csrf
                        <button type="submit" class="btn caronte-btn-primary w-100">Synchronize roles now</button>
                    </form>

                    <ul class="list-group list-group-flush mt-4">
                        @foreach ($configured_roles as $role)
                            <li class="list-group-item px-0">
                                <div class="fw-semibold">{{ $role['name'] }}</div>
                                <small class="text-muted">{{ $role['description'] }}</small>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="caronte-card">
                    <div class="caronte-card__header">
                        <h2>Create user</h2>
                        <p>New users are created in Caronte and immediately scoped to the configured role set you choose.</p>
                    </div>

                    <form method="POST" action="{{ $routes['usersStore'] }}" class="caronte-form">
                        @csrf
                        <div>
                            <label for="name" class="form-label">Name</label>
                            <input id="name" type="text" name="name" class="form-control" required>
                        </div>

                        <div>
                            <label for="email" class="form-label">Email</label>
                            <input id="email" type="email" name="email" class="form-control" required>
                        </div>

                        <div>
                            <label for="password" class="form-label">Temporary password</label>
                            <input id="password" type="password" name="password" class="form-control" required>
                        </div>

                        <div>
                            <label for="password_confirmation" class="form-label">Confirm password</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required>
                        </div>

                        <div>
                            <label class="form-label">Configured roles</label>
                            <div class="caronte-checkbox-list">
                                @foreach ($configured_roles as $role)
                                    <label class="caronte-checkbox">
                                        <input type="checkbox" name="roles[]" value="{{ $role['uri_applicationRole'] }}">
                                        <span>
                                            <strong>{{ $role['name'] }}</strong>
                                            <small>{{ $role['description'] }}</small>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <button type="submit" class="btn caronte-btn-primary">Create user</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="caronte-card mt-4">
            <div class="caronte-card__header">
                <h2>Suite access</h2>
                <p>Manage non-root roles for applications in the configured application group.</p>
            </div>

            @if (!($suite_access['enabled'] ?? false))
                <div class="alert alert-warning mb-0">
                    {{ $suite_access['error'] ?? 'Suite access is not available for this application.' }}
                </div>
            @elseif (empty($suite_access['applications']) || empty($suite_access['users']))
                <div class="text-muted">No suite applications or users matched the current filters.</div>
            @else
                <div class="accordion" id="suiteAccessAccordion">
                    @foreach ($suite_access['users'] as $suiteUser)
                        <div class="accordion-item">
                            <h3 class="accordion-header" id="suite-user-{{ $suiteUser['uri_user'] }}">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#suite-collapse-{{ $suiteUser['uri_user'] }}">
                                    {{ $suiteUser['name'] ?? '' }} · {{ $suiteUser['email'] ?? '' }}
                                </button>
                            </h3>
                            <div id="suite-collapse-{{ $suiteUser['uri_user'] }}" class="accordion-collapse collapse" data-bs-parent="#suiteAccessAccordion">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        @foreach ($suite_access['applications'] as $suiteApp)
                                            @php
                                                $manageableRoles = collect($suiteApp['roles'] ?? [])
                                                    ->filter(fn ($role) => ($role['manageable'] ?? true) !== false)
                                                    ->values();
                                                $assignedRoleUris = collect($suiteUser['roles'] ?? [])
                                                    ->where('app_id', $suiteApp['app_id'])
                                                    ->pluck('uri_applicationRole')
                                                    ->all();
                                                $syncUrl = str_replace(
                                                    ['__USER__', '__APP__'],
                                                    [$suiteUser['uri_user'], $suiteApp['app_id']],
                                                    $routes['suiteRolesSync']
                                                );
                                            @endphp
                                            <div class="col-12 col-lg-6">
                                                <form method="POST" action="{{ $syncUrl }}" class="border rounded p-3 h-100">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="fw-semibold mb-2">{{ $suiteApp['name'] ?? $suiteApp['app_id'] }}</div>
                                                    @forelse ($manageableRoles as $role)
                                                        <label class="caronte-checkbox">
                                                            <input
                                                                type="checkbox"
                                                                name="roles[]"
                                                                value="{{ $role['uri_applicationRole'] }}"
                                                                @checked(in_array($role['uri_applicationRole'], $assignedRoleUris, true))
                                                            >
                                                            <span>
                                                                <strong>{{ $role['name'] }}</strong>
                                                                <small>{{ $role['description'] ?? '' }}</small>
                                                            </span>
                                                        </label>
                                                    @empty
                                                        <div class="text-muted small mb-3">Only reserved roles are defined for this app.</div>
                                                    @endforelse
                                                    <button type="submit" class="btn caronte-btn-secondary btn-sm mt-2">Save suite roles</button>
                                                </form>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
