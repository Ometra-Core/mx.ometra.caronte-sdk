<?php

namespace Ometra\Caronte\Http\Controllers;

use Equidna\Toolkit\Helpers\RouteHelper;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;
use Ometra\Caronte\Api\AuthApi;
use Ometra\Caronte\Api\ClientApi;
use Ometra\Caronte\CaronteUserToken;
use Ometra\Caronte\Contracts\SendsPasswordRecovery;
use Ometra\Caronte\Contracts\SendsTwoFactorChallenge;
use Ometra\Caronte\Exceptions\CaronteApiException;
use Ometra\Caronte\Facades\Caronte;
use Ometra\Caronte\Support\CaronteResponse;
use Ometra\Caronte\Support\CaronteTenancy;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends BaseController
{
    private const PENDING_LOGIN_SESSION_KEY = 'caronte.pending_login';
    private const PENDING_LOGIN_TTL_SECONDS = 300;

    public function loginForm(Request $request): View|InertiaResponse|RedirectResponse
    {
        if (config('caronte.auth_mode') === 'oidc') {
            $callbackUrl = $request->query('callback_url');

            if (is_string($callbackUrl) && trim($callbackUrl) !== '') {
                return redirect()->route('caronte.oidc.login', ['callback_url' => $callbackUrl]);
            }

            return redirect()->route('caronte.oidc.login');
        }

        $view = config('caronte.use_2fa') ? 'auth.two-factor' : 'auth.login';
        $tenantOptions = (array) session('data.tenants', []);
        $pendingLogin = $tenantOptions !== [] ? $this->pendingLogin($request) : null;

        if ($tenantOptions === []) {
            $this->forgetPendingLogin($request);
        }

        $callbackUrl = $request->query('callback_url');

        if (! is_string($callbackUrl) || trim($callbackUrl) === '') {
            $callbackUrl = $request->old('callback_url', data_get($pendingLogin, 'callback_url'));
        }

        return $this->toView($view, [
            'callback_url' => $callbackUrl,
            'csrf_token' => csrf_token(),
            'branding' => $this->branding(),
            'tenant_options' => $tenantOptions,
            'pending_login' => $pendingLogin === null
                ? null
                : ['email' => $pendingLogin['email']],
            'routes' => [
                'login' => route('caronte.login'),
                'logout' => route('caronte.logout'),
                'twoFactorRequest' => route('caronte.twoFactor.request'),
                'passwordRecoverForm' => route('caronte.password.recover.form'),
            ],
        ]);
    }

    public function login(Request $request): Response
    {
        if (config('caronte.use_2fa')) {
            return $this->handleTwoFactorTokenRequest($request);
        }

        return $this->handleUserPasswordLogin($request);
    }

    public function twoFactorTokenRequest(Request $request): Response
    {
        return $this->handleTwoFactorTokenRequest($request);
    }

    public function twoFactorTokenLogin(Request $request, string $token): Response
    {
        return $this->handleTwoFactorTokenLogin($request, $token);
    }

    public function passwordRecoverRequestForm(): View|InertiaResponse
    {
        return $this->toView('auth.password-recover-request', [
            'csrf_token' => csrf_token(),
            'branding' => $this->branding(),
            'routes' => [
                'passwordRecoverRequest' => route('caronte.password.recover.request'),
                'login' => route('caronte.login.form'),
            ],
        ]);
    }

    public function passwordRecoverRequest(Request $request): Response
    {
        return $this->handlePasswordRecoverRequest($request);
    }

    public function passwordRecoverTokenValidation(string $token): Response|View|InertiaResponse
    {
        return $this->handlePasswordRecoverTokenValidation($token);
    }

    public function passwordRecover(Request $request, string $token): Response
    {
        return $this->handlePasswordRecover($request, $token);
    }

    public function logout(Request $request): Response
    {
        return $this->handleLogout($request->boolean('all'));
    }

    private function handleUserPasswordLogin(Request $request): Response
    {
        $pendingLogin = $this->pendingLogin($request);
        $tenantId = $request->input('id_tenant') !== null
            ? trim($request->string('id_tenant')->toString())
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
                errors: ['Tenant mismatch.'],
                forwardUrl: (string) config('caronte.login_url')
            );
        }

        if ($configuredTenantId !== null) {
            $tenantId = $configuredTenantId;
        }

        $requestEmail = $request->filled('email')
            ? $request->string('email')->toString()
            : null;
        $usePendingLogin = $pendingLogin !== null
            && is_string($tenantId)
            && $tenantId !== ''
            && ! $request->filled('password')
            && (
                $requestEmail === null
                || hash_equals($pendingLogin['email'], $requestEmail)
            );

        $request->validate([
            'email' => [$usePendingLogin ? 'nullable' : 'required', 'email'],
            'password' => [$usePendingLogin ? 'nullable' : 'required', 'string'],
            'id_tenant' => ['nullable', 'string'],
            'tenant_selection_token' => ['nullable', 'string'],
        ]);

        $email = $usePendingLogin
            ? $pendingLogin['email']
            : $request->string('email')->toString();
        $password = $usePendingLogin
            ? null
            : $request->string('password')->toString();
        $tenantSelectionToken = $usePendingLogin
            ? $pendingLogin['tenant_selection_token']
            : null;

        try {
            $response = AuthApi::login(
                email: $email,
                password: $password,
                tenantId: $tenantId,
                tenantSelectionToken: $tenantSelectionToken
            );

            $tokenString = (string) data_get($response, 'data.token', '');
            $token = CaronteUserToken::validateToken($tokenString, skipExchange: true);

            if ($this->isWebRequest($request)) {
                Caronte::saveToken($token->toString());
            }

            $this->forgetPendingLogin($request);

            return CaronteResponse::success(
                message: $response['message'],
                data: ['token' => $token->toString()],
                forwardUrl: $this->forwardUrl($request->input('callback_url'))
            );
        } catch (CaronteApiException $exception) {
            if (
                $exception->getCode() === 409
                && ($exception->errors()['code'] ?? null) === 'tenant_selection_required'
            ) {
                if ($this->isWebRequest($request)) {
                    $tenantSelectionToken = $exception->errors()['tenant_selection_token'] ?? null;

                    if (is_string($tenantSelectionToken) && trim($tenantSelectionToken) !== '') {
                        $this->rememberPendingLogin(
                            request: $request,
                            email: $email,
                            tenantSelectionToken: $tenantSelectionToken
                        );
                    }

                    return redirect()
                        ->to((string) config('caronte.login_url'))
                        ->with([
                            'status' => 409,
                            'message' => 'Select a tenant to continue.',
                            'info' => 'Select a tenant to continue.',
                            'data' => [
                                'tenants' => $exception->errors()['tenants'] ?? [],
                            ],
                        ])
                        ->withInput($request->except([
                            'password',
                            'password_confirmation',
                            'current_password',
                            'new_password',
                        ]));
                }

                return CaronteResponse::conflict(
                    message: $exception->getMessage(),
                    errors: $exception->errors(),
                    data: ['tenants' => $exception->errors()['tenants'] ?? []],
                    forwardUrl: (string) config('caronte.login_url')
                );
            }

            $this->forgetPendingLogin($request);

            return CaronteResponse::handleException(
                exception: $exception,
                errors: $exception->errors(),
                forwardUrl: (string) config('caronte.login_url')
            );
        }
    }

    private function handleTwoFactorTokenRequest(Request $request): Response
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $email = $request->string('email')->toString();
            $callbackUrl = $this->absoluteUrl($this->forwardUrl($request->input('callback_url')));

            if (config('caronte.notification_delivery') === 'host') {
                $response = AuthApi::issueTwoFactor(
                    email: $email,
                    callbackUrl: $callbackUrl
                );

                if ((string) data_get($response, 'data.action_url', '') === '') {
                    return CaronteResponse::success(
                        message: $response['message'],
                        data: $response['data'],
                        forwardUrl: (string) config('caronte.login_url')
                    );
                }

                app(SendsTwoFactorChallenge::class)->send(
                    email: (string) data_get($response, 'data.email', $request->string('email')->toString()),
                    actionUrl: (string) data_get($response, 'data.action_url', ''),
                    expiresAt: data_get($response, 'data.expires_at')
                );
            } else {
                $response = AuthApi::requestTwoFactor(
                    email: $email,
                    callbackUrl: $callbackUrl
                );
            }

            return CaronteResponse::success(
                message: $response['message'],
                data: $response['data'],
                forwardUrl: (string) config('caronte.login_url')
            );
        } catch (CaronteApiException $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                errors: $exception->errors(),
                forwardUrl: (string) config('caronte.login_url')
            );
        }
    }

    private function handleTwoFactorTokenLogin(Request $request, string $token): Response
    {
        try {
            $response = AuthApi::consumeTwoFactor($token);

            $tokenString = (string) data_get($response, 'data.token', '');
            $validatedToken = CaronteUserToken::validateToken($tokenString, skipExchange: true);

            if ($this->isWebRequest($request)) {
                Caronte::saveToken($validatedToken->toString());
            }

            return CaronteResponse::success(
                message: $response['message'],
                data: ['token' => $validatedToken->toString()],
                forwardUrl: $this->forwardUrl($request->input('callback_url'))
            );
        } catch (CaronteApiException $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                errors: $exception->errors(),
                forwardUrl: (string) config('caronte.login_url')
            );
        }
    }

    private function handlePasswordRecoverRequest(Request $request): Response
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $email = $request->string('email')->toString();

            if (config('caronte.notification_delivery') === 'host') {
                $response = AuthApi::issuePasswordRecovery(
                    email: $email
                );

                if ((string) data_get($response, 'data.action_url', '') === '') {
                    return CaronteResponse::success(
                        message: $response['message'],
                        data: $response['data'],
                        forwardUrl: (string) config('caronte.login_url')
                    );
                }

                app(SendsPasswordRecovery::class)->send(
                    email: (string) data_get($response, 'data.email', $request->string('email')->toString()),
                    actionUrl: (string) data_get($response, 'data.action_url', ''),
                    expiresAt: data_get($response, 'data.expires_at')
                );
            } else {
                $response = AuthApi::requestPasswordRecovery(
                    email: $email
                );
            }

            return CaronteResponse::success(
                message: $response['message'],
                data: $response['data'],
                forwardUrl: (string) config('caronte.login_url')
            );
        } catch (CaronteApiException $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                errors: $exception->errors(),
                forwardUrl: (string) config('caronte.login_url')
            );
        }
    }

    private function handlePasswordRecoverTokenValidation(string $token): Response|View|InertiaResponse
    {
        try {
            $response = AuthApi::validatePasswordRecovery($token);
        } catch (CaronteApiException $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                errors: $exception->errors(),
                forwardUrl: (string) config('caronte.login_url')
            );
        }

        if (RouteHelper::wantsJson()) {
            return CaronteResponse::success(
                message: $response['message'],
                data: $response['data']
            );
        }

        $view = config('caronte.use_inertia') ? 'auth/password-recover' : 'auth.password-recover';

        if (config('caronte.use_inertia')) {
            return inertia($view, [
                'csrf_token' => csrf_token(),
                'routes' => [
                    'passwordRecoverSubmit' => url()->current(),
                    'login' => config('caronte.login_url'),
                ],
                'branding' => config('caronte.ui.branding'),
                'token' => $token,
            ]);
        }

        return view('caronte::' . $view, [
            'csrf_token' => csrf_token(),
            'routes' => [
                'passwordRecoverSubmit' => url()->current(),
                'login' => config('caronte.login_url'),
            ],
            'branding' => config('caronte.ui.branding'),
            'token' => $token,
        ]);
    }

    private function handlePasswordRecover(Request $request, string $token): Response
    {
        $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        try {
            $response = AuthApi::resetPassword(
                token: $token,
                password: $request->string('password')->toString(),
                passwordConfirmation: $request->string('password_confirmation')->toString()
            );

            return CaronteResponse::success(
                message: $response['message'],
                data: $response['data'],
                forwardUrl: (string) config('caronte.login_url')
            );
        } catch (CaronteApiException $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                errors: $exception->errors(),
                forwardUrl: (string) config('caronte.login_url')
            );
        }
    }

    private function handleLogout(bool $logoutAllSessions = false): Response
    {
        try {
            $response = AuthApi::logout(
                allSessions: $logoutAllSessions
            );

            Caronte::clearToken();

            return CaronteResponse::success(
                message: $response['message'],
                data: $response['data'],
                forwardUrl: (string) config('caronte.login_url')
            );
        } catch (CaronteApiException $exception) {
            return CaronteResponse::handleException(
                exception: $exception,
                errors: $exception->errors(),
                forwardUrl: (string) config('caronte.login_url')
            );
        }
    }

    private function forwardUrl(?string $candidate): string
    {
        $value = is_string($candidate) ? trim($candidate) : '';

        if ($value !== '') {
            $decoded = base64_decode($value, true);

            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }

            return $value;
        }

        return (string) config('caronte.success_url', '/');
    }

    private function absoluteUrl(string $url): string
    {
        if (preg_match('/^https?:\/\//i', $url) === 1) {
            return $url;
        }

        return url($url);
    }

    private function isWebRequest(Request $request): bool
    {
        return ! RouteHelper::wantsJson();
    }

    /**
     * @return array{email: string, tenant_selection_token: string, callback_url: string|null, created_at: int}|null
     */
    private function pendingLogin(Request $request): ?array
    {
        $pendingLogin = $request->session()->get(self::PENDING_LOGIN_SESSION_KEY);

        if (! is_array($pendingLogin)) {
            return null;
        }

        $email = $pendingLogin['email'] ?? null;
        $tenantSelectionToken = $pendingLogin['tenant_selection_token'] ?? null;
        $createdAt = $pendingLogin['created_at'] ?? null;
        $callbackUrl = $pendingLogin['callback_url'] ?? null;

        if (
            ! is_string($email)
            || ! is_string($tenantSelectionToken)
            || ! is_int($createdAt)
            || time() - $createdAt > self::PENDING_LOGIN_TTL_SECONDS
        ) {
            $this->forgetPendingLogin($request);

            return null;
        }

        return [
            'email' => $email,
            'tenant_selection_token' => $tenantSelectionToken,
            'callback_url' => is_string($callbackUrl) ? $callbackUrl : null,
            'created_at' => $createdAt,
        ];
    }

    private function rememberPendingLogin(Request $request, string $email, string $tenantSelectionToken): void
    {
        $callbackUrl = $request->input('callback_url');

        $request->session()->put(self::PENDING_LOGIN_SESSION_KEY, [
            'email' => $email,
            'tenant_selection_token' => $tenantSelectionToken,
            'callback_url' => is_string($callbackUrl) && trim($callbackUrl) !== ''
                ? $callbackUrl
                : null,
            'created_at' => time(),
        ]);
    }

    private function forgetPendingLogin(Request $request): void
    {
        $request->session()->forget(self::PENDING_LOGIN_SESSION_KEY);
    }
}
