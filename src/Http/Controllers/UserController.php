<?php

namespace Ometra\Caronte\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;
use Ometra\Caronte\Api\ClientApi;
use Ometra\Caronte\Api\GroupApi;
use Ometra\Caronte\Facades\Caronte;
use Ometra\Caronte\Support\CaronteResponse;
use Ometra\Caronte\Support\ConfiguredRoles;
use Symfony\Component\HttpFoundation\Response;
use Exception;

class UserController extends BaseController
{
    public function list(Request $request): JsonResponse|Response
    {
        try {
            $response = ClientApi::showUsers(
                search: trim((string) $request->query('search', '')),
                usersApp: $request->boolean('usersApp', true)
            );

            return response()->json($response, $response['status'] >= 400 ? $response['status'] : 200);
        } catch (Exception $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                forwardUrl: route('caronte.management.dashboard')
            );
        }
    }

    public function store(Request $request): Response
    {
        $roleUris = $this->configuredRoleUris();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::in($roleUris)],
        ]);

        try {
            $response = ClientApi::createUser([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $request->password_confirmation
            ]);

            $user = $response['data']['user'] ?? null;

            if (!is_array($user) || !isset($user['uri_user'])) {
                throw new \RuntimeException('Caronte did not return the created user.');
            }

            ClientApi::syncUserRoles(
                uriUser: (string) $user['uri_user'],
                roleUris: array_values($validated['roles'] ?? [])
            );

            return redirect()
                ->route('caronte.management.users.show', ['uri_user' => $user['uri_user']])
                ->with('success', $response['message']);
        } catch (Exception $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                forwardUrl: route('caronte.management.dashboard')
            );
        }
    }

    public function listRoles(string $uri_user): JsonResponse|Response
    {
        try {
            $response = ClientApi::showUserRoles($uri_user);

            return response()->json($response, $response['status'] >= 400 ? $response['status'] : 200);
        } catch (Exception $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                forwardUrl: route('caronte.management.dashboard')
            );
        }
    }

    public function show(string $uri_user): View|InertiaResponse|Response
    {
        try {
            $userResponse = ClientApi::showUser($uri_user);
            $rolesResponse = ClientApi::showUserRoles($uri_user);

            $user = is_array($userResponse['data']) ? $userResponse['data'] : [];
            $assignedRoles = is_array($rolesResponse['data']) ? $rolesResponse['data'] : [];
            $assignedRoleUris = array_values(array_filter(array_map(
                fn(array $role): ?string => $role['uri_applicationRole'] ?? null,
                $assignedRoles
            )));

            return $this->toView('management.user-detail', [
                'branding' => $this->branding(),
                'user' => $user,
                'assigned_roles' => $assignedRoles,
                'assigned_role_uris' => $assignedRoleUris,
                'configured_roles' => ConfiguredRoles::all(),
                'features' => config('caronte.management.features', []),
                'csrf_token' => csrf_token(),
                'routes' => [
                    'dashboard' => route('caronte.management.dashboard'),
                    'update' => route('caronte.management.users.update.direct', ['uri_user' => $uri_user]),
                    'delete' => route('caronte.management.users.delete.direct', ['uri_user' => $uri_user]),
                    'syncRoles' => route('caronte.management.users.roles.sync', ['uri_user' => $uri_user]),
                    'storeMetadata' => route('caronte.management.users.metadata.store', ['uri_user' => $uri_user]),
                    'deleteMetadata' => route('caronte.management.users.metadata.delete', ['uri_user' => $uri_user]),
                ],
            ], true);
        } catch (Exception $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                forwardUrl: route('caronte.management.dashboard')
            );
        }
    }

    public function update(Request $request, string $uri_user): Response
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $response = ClientApi::updateUser($uri_user, [
                'name' => $validated['name'],
            ]);

            return redirect()
                ->route('caronte.management.users.show', ['uri_user' => $uri_user])
                ->with('success', $response['message']);
        } catch (Exception $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                forwardUrl: route('caronte.management.users.show', ['uri_user' => $uri_user])
            );
        }
    }

    public function updateLegacy(Request $request): Response
    {
        $uriUser = (string) $request->input('uri_user', '');

        abort_if($uriUser === '', 422, 'Missing uri_user.');

        return $this->update($request, $uriUser);
    }

    public function syncRoles(Request $request, string $uri_user): Response
    {
        $roleUris = $this->configuredRoleUris();

        $validated = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string', Rule::in($roleUris)],
        ]);

        try {
            $response = ClientApi::syncUserRoles(
                uriUser: $uri_user,
                roleUris: array_values($validated['roles'] ?? [])
            );

            return redirect()
                ->route('caronte.management.users.show', ['uri_user' => $uri_user])
                ->with('success', $response['message']);
        } catch (Exception $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                forwardUrl: route('caronte.management.users.show', ['uri_user' => $uri_user])
            );
        }
    }

    public function syncGroupRoles(Request $request, string $uri_user, string $app_id): Response
    {
        $roleUris = $this->manageableGroupRoleUris($app_id);

        $validated = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string', Rule::in($roleUris)],
        ]);

        try {
            $response = GroupApi::syncGroupUserRoles(
                uriUser: $uri_user,
                appId: $app_id,
                roleUris: array_values($validated['roles'] ?? []),
                actorToken: Caronte::getToken()->toString()
            );

            return redirect()
                ->route('caronte.management.dashboard')
                ->with('success', $response['message']);
        } catch (Exception $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                forwardUrl: route('caronte.management.dashboard')
            );
        }
    }

    public function storeMetadata(Request $request, string $uri_user): Response
    {
        if (!config('caronte.management.features.metadata', true)) {
            return redirect()->route('caronte.management.users.show', ['uri_user' => $uri_user])
                ->with('warning', 'Metadata management is disabled.');
        }

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
        ]);

        try {
            $response = ClientApi::storeUserMetadata($uri_user, [
                $validated['key'] => $validated['value'] ?? '',
            ]);

            return redirect()
                ->route('caronte.management.users.show', ['uri_user' => $uri_user])
                ->with('success', $response['message']);
        } catch (Exception $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                forwardUrl: route('caronte.management.users.show', ['uri_user' => $uri_user])
            );
        }
    }

    public function deleteMetadata(Request $request, string $uri_user): Response
    {
        if (!config('caronte.management.features.metadata', true)) {
            return redirect()->route('caronte.management.users.show', ['uri_user' => $uri_user])
                ->with('warning', 'Metadata management is disabled.');
        }

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255'],
        ]);

        try {
            $response = ClientApi::deleteUserMetadata($uri_user, $validated['key']);

            return redirect()
                ->route('caronte.management.users.show', ['uri_user' => $uri_user])
                ->with('success', $response['message']);
        } catch (Exception $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                forwardUrl: route('caronte.management.users.show', ['uri_user' => $uri_user])
            );
        }
    }

    public function delete(string $uri_user): Response
    {
        try {
            $response = ClientApi::deleteUser($uri_user);

            return redirect()
                ->route('caronte.management.dashboard')
                ->with('success', $response['message']);
        } catch (Exception $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                forwardUrl: route('caronte.management.dashboard')
            );
        }
    }

    public function deleteLegacy(Request $request): Response
    {
        $uriUser = (string) $request->input('uri_user', '');

        abort_if($uriUser === '', 422, 'Missing uri_user.');

        return $this->delete($uriUser);
    }

    /**
     * @return array<int, string>
     */
    private function configuredRoleUris(): array
    {
        return array_values(array_map(
            fn(array $role): string => $role['uri_applicationRole'],
            ConfiguredRoles::all()
        ));
    }

    /**
     * @return array<int, string>
     */
    private function manageableGroupRoleUris(string $appId): array
    {
        $response = GroupApi::showGroupRoles();
        $applications = $response['data']['applications'] ?? [];

        foreach ((array) $applications as $application) {
            if (!is_array($application) || (string) ($application['app_id'] ?? '') !== $appId) {
                continue;
            }

            return array_values(array_filter(array_map(
                function (array $role): ?string {
                    if (($role['manageable'] ?? true) === false) {
                        return null;
                    }

                    return is_string($role['uri_applicationRole'] ?? null)
                        ? $role['uri_applicationRole']
                        : null;
                },
                (array) ($application['roles'] ?? [])
            )));
        }

        return [];
    }
}
