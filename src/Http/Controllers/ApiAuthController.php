<?php

namespace Ometra\Caronte\Http\Controllers;

use Illuminate\Http\Request;
use Ometra\Caronte\Api\AuthApi;
use Ometra\Caronte\CaronteUserToken;
use Ometra\Caronte\Exceptions\CaronteApiException;
use Ometra\Caronte\Facades\Caronte;
use Ometra\Caronte\Support\CaronteResponse;
use Ometra\Caronte\Support\CaronteTenancy;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthController extends BaseController
{
    public function login(Request $request): Response
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required_without:tenant_selection_token', 'nullable', 'string'],
            'tenant_id' => ['nullable', 'string'],
            'tenant_selection_token' => ['nullable', 'string'],
        ]);

        $tenantId = $request->input('tenant_id') !== null
            ? trim($request->string('tenant_id')->toString())
            : null;
        $configuredTenantId = CaronteTenancy::isSingleTenant()
            ? CaronteTenancy::requireConfiguredTenantId()
            : null;

        if (
            $configuredTenantId !== null
            && is_string($tenantId)
            && $tenantId !== ''
            && $tenantId !== $configuredTenantId
        ) {
            return CaronteResponse::forbidden(
                message: 'Tenant mismatch.',
                errors: ['Tenant mismatch.']
            );
        }

        if ($configuredTenantId !== null) {
            $tenantId = $configuredTenantId;
        }

        try {
            $response = AuthApi::login(
                email: $request->string('email')->toString(),
                password: $request->filled('password') ? $request->string('password')->toString() : null,
                tenantId: $tenantId,
                tenantSelectionToken: $request->filled('tenant_selection_token')
                    ? $request->string('tenant_selection_token')->toString()
                    : null
            );

            $tokenString = (string) data_get($response, 'data.token', '');
            $token = CaronteUserToken::validateToken($tokenString, skipExchange: true);

            return CaronteResponse::success(
                message: $response['message'],
                data: ['token' => $token->toString()]
            );
        } catch (CaronteApiException $exception) {
            if (
                $exception->getCode() === 409
                && ($exception->errors()['code'] ?? null) === 'tenant_selection_required'
            ) {
                return CaronteResponse::conflict(
                    message: $exception->getMessage(),
                    errors: $exception->errors(),
                    data: [
                        'tenants' => $exception->errors()['tenants'] ?? [],
                        'tenant_selection_token' => $exception->errors()['tenant_selection_token'] ?? null,
                    ]
                );
            }

            return CaronteResponse::handleException(
                exception: $exception,
                errors: $exception->errors()
            );
        }
    }

    public function me(Request $request): Response
    {
        $user = $request->attributes->get('caronte.user');

        if (! $user instanceof stdClass) {
            $user = Caronte::getUser();
        }

        return CaronteResponse::success(
            message: 'Authenticated user retrieved.',
            data: [
                'user' => [
                    'uri_user' => $user->uri_user ?? null,
                    'name' => $user->name ?? null,
                    'email' => $user->email ?? null,
                ],
                'tenant_id' => $user->tenant_id ?? null,
                'roles' => $user->roles ?? [],
                'metadata' => $user->metadata ?? [],
            ]
        );
    }

    public function logout(Request $request): Response
    {
        $userToken = Caronte::getToken()->toString();

        if ($userToken === '') {
            return CaronteResponse::unauthorized('Token not found');
        }

        try {
            $response = AuthApi::logout($userToken);

            return CaronteResponse::success(
                message: $response['message'],
                data: $response['data']
            );
        } catch (CaronteApiException $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                errors: $exception->errors()
            );
        }
    }
}
