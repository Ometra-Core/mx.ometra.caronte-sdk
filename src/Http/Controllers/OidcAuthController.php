<?php

namespace Ometra\Caronte\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Ometra\Caronte\Facades\Caronte;
use Ometra\Caronte\Oidc\Base64Url;
use Ometra\Caronte\Oidc\OidcClient;
use Ometra\Caronte\Oidc\OidcTokenValidator;
use Ometra\Caronte\Oidc\Pkce;
use Ometra\Caronte\Support\CaronteCallbackUrl;
use Ometra\Caronte\Support\CaronteResponse;
use Symfony\Component\HttpFoundation\Response;

class OidcAuthController extends BaseController
{
    private const CALLBACK_URL_SESSION_KEY = 'caronte.oidc.callback_url';

    public function redirect(Request $request, OidcClient $client): RedirectResponse
    {
        $state = Base64Url::encode(random_bytes(32));
        $nonce = Base64Url::encode(random_bytes(32));
        $verifier = Pkce::verifier();
        $callbackUrl = CaronteCallbackUrl::normalize($request, $request->query('callback_url'));

        $request->session()->put('caronte.oidc.state', $state);
        $request->session()->put('caronte.oidc.nonce', $nonce);
        $request->session()->put('caronte.oidc.code_verifier', $verifier);

        if (is_string($callbackUrl) && trim($callbackUrl) !== '') {
            $request->session()->put(self::CALLBACK_URL_SESSION_KEY, $callbackUrl);
        } else {
            $request->session()->forget(self::CALLBACK_URL_SESSION_KEY);
        }

        return redirect()->away($client->authorizationUrl($state, $nonce, $verifier));
    }

    public function callback(Request $request, OidcClient $client, OidcTokenValidator $validator): Response
    {
        $state = trim((string) $request->query('state', ''));
        $expectedState = trim((string) $request->session()->pull('caronte.oidc.state', ''));
        $verifier = trim((string) $request->session()->pull('caronte.oidc.code_verifier', ''));
        $nonce = trim((string) $request->session()->pull('caronte.oidc.nonce', ''));
        $callbackUrl = $request->session()->pull(self::CALLBACK_URL_SESSION_KEY);

        if ($state === '' || $expectedState === '' || ! hash_equals($expectedState, $state)) {
            return CaronteResponse::unauthorized(
                message: 'Invalid OIDC state.',
                forwardUrl: (string) config('caronte.routes.login_url')
            );
        }

        if ($verifier === '' || $nonce === '') {
            return CaronteResponse::unauthorized(
                message: 'Invalid OIDC login session.',
                forwardUrl: (string) config('caronte.routes.login_url')
            );
        }

        try {
            $tokens = $client->exchangeCode((string) $request->query('code', ''), $verifier);
            $idToken = (string) ($tokens['id_token'] ?? '');
            $refreshToken = (string) ($tokens['refresh_token'] ?? '');

            $validator->validate($idToken, $nonce);
            Caronte::saveToken($idToken);

            if ($refreshToken !== '') {
                $request->session()->put('caronte.oidc.refresh_token', $refreshToken);
            }

            return CaronteResponse::success(
                message: 'OIDC login successful',
                data: ['token_type' => $tokens['token_type'] ?? 'Bearer'],
                forwardUrl: CaronteCallbackUrl::resolve($request, $callbackUrl)
            );
        } catch (\Throwable $exception) {
            Caronte::clearToken();

            return CaronteResponse::unauthorized(
                message: $exception->getMessage(),
                forwardUrl: (string) config('caronte.routes.login_url')
            );
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        $idToken = $request->session()->get((string) config('caronte.session_key', 'caronte.user_token'));
        Caronte::clearToken();
        $request->session()->forget('caronte.oidc.refresh_token');

        $issuer = rtrim((string) config('caronte.oidc.issuer'), '/');
        $url = $issuer . '/oauth/logout?' . http_build_query(array_filter([
            'id_token_hint' => is_string($idToken) ? $idToken : '',
            'post_logout_redirect_uri' => url((string) config('caronte.routes.login_url')),
        ]));

        return redirect()->away($url);
    }

}
