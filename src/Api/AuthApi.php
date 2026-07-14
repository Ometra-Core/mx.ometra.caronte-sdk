<?php

namespace Ometra\Caronte\Api;

use Ometra\Caronte\Facades\Caronte;

class AuthApi
{
    /**
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function login(
        string $email,
        ?string $password = null,
        ?string $tenantId = null,
        ?string $tenantSelectionToken = null
    ): array
    {
        $payload = [
            'email' => $email,
        ];

        if (is_string($password) && $password !== '') {
            $payload['password'] = $password;
        }

        if (is_string($tenantId) && trim($tenantId) !== '') {
            $payload['tenant_id'] = trim($tenantId);
        }

        if (is_string($tenantSelectionToken) && trim($tenantSelectionToken) !== '') {
            $payload['tenant_selection_token'] = trim($tenantSelectionToken);
        }

        return app(CaronteApiClient::class)->authRequest(
            method: 'post',
            endpoint: 'api/auth/login',
            payload: $payload
        );
    }

    /**
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function requestTwoFactor(string $email, string $callbackUrl): array
    {
        return app(CaronteApiClient::class)->authRequest(
            method: 'post',
            endpoint: 'api/auth/two-factor',
            payload: [
                'email' => $email,
                'callback_url' => $callbackUrl,
            ]
        );
    }

    /**
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function issueTwoFactor(string $email, string $callbackUrl): array
    {
        return app(CaronteApiClient::class)->authRequest(
            method: 'post',
            endpoint: 'api/auth/two-factor/issue',
            payload: [
                'email' => $email,
                'callback_url' => $callbackUrl,
            ]
        );
    }

    /**
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function consumeTwoFactor(string $token): array
    {
        return app(CaronteApiClient::class)->authRequest(
            method: 'post',
            endpoint: 'api/auth/two-factor/' . $token
        );
    }

    /**
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function requestPasswordRecovery(string $email): array
    {
        return app(CaronteApiClient::class)->authRequest(
            method: 'post',
            endpoint: 'api/auth/password/recover',
            payload: [
                'email' => $email,
            ]
        );
    }

    /**
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function issuePasswordRecovery(string $email): array
    {
        return app(CaronteApiClient::class)->authRequest(
            method: 'post',
            endpoint: 'api/auth/password/recover/issue',
            payload: [
                'email' => $email,
            ]
        );
    }

    /**
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function validatePasswordRecovery(string $token): array
    {
        return app(CaronteApiClient::class)->authRequest(
            method: 'get',
            endpoint: 'api/auth/password/recover/' . $token
        );
    }

    /**
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function resetPassword(string $token, string $password, string $passwordConfirmation): array
    {
        return app(CaronteApiClient::class)->authRequest(
            method: 'post',
            endpoint: 'api/auth/password/recover/' . $token,
            payload: [
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ]
        );
    }

    /**
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function logout(?string $userToken = null, bool $allSessions = false): array
    {
        return app(CaronteApiClient::class)->authRequest(
            method: 'post',
            endpoint: $allSessions ? 'api/auth/logoutAll' : 'api/auth/logout',
            userToken: $userToken ?? Caronte::getToken()->toString()
        );
    }

    /**
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function exchange(string $userToken): array
    {
        return app(CaronteApiClient::class)->authRequest(
            method: 'post',
            endpoint: 'api/auth/exchange',
            userToken: $userToken
        );
    }
}
