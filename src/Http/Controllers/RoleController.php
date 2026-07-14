<?php

namespace Ometra\Caronte\Http\Controllers;

use Ometra\Caronte\Api\RoleApi;
use Ometra\Caronte\Support\CaronteResponse;
use Ometra\Caronte\Support\ConfiguredRoles;
use Symfony\Component\HttpFoundation\Response;
use Exception;

class RoleController extends BaseController
{
    public function sync(): Response
    {
        try {
            $roles = array_map(
                fn(array $role): array => [
                    'name' => $role['name'],
                    'description' => $role['description'],
                ],
                ConfiguredRoles::all()
            );
            $response = RoleApi::syncRoles($roles);

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

}
