<?php

namespace Ometra\Caronte\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Ometra\Caronte\Facades\Caronte;
use Ometra\Caronte\Oidc\Base64Url;
use Ometra\Caronte\Oidc\OidcClient;
use Ometra\Caronte\Oidc\OidcTokenValidator;
use Ometra\Caronte\Oidc\Pkce;
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
        $callbackUrl = $request->query('callback_url');

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
        if ((string) $request->query('state', '') !== (string) $request->session()->pull('caronte.oidc.state', '')) {
            return CaronteResponse::unauthorized(
                message: 'Invalid OIDC state.',
                forwardUrl: (string) config('caronte.login_url')
            );
        }

        $verifier = (string) $request->session()->pull('caronte.oidc.code_verifier', '');

        try {
            $tokens = $client->exchangeCode((string) $request->query('code', ''), $verifier);
            $idToken = (string) ($tokens['id_token'] ?? '');
            $refreshToken = (string) ($tokens['refresh_token'] ?? '');

            $validator->validate($idToken);
            Caronte::saveToken($idToken);

            if ($refreshToken !== '') {
                $request->session()->put('caronte.oidc.refresh_token', $refreshToken);
            }

            return CaronteResponse::success(
                message: 'OIDC login successful',
                data: ['token_type' => $tokens['token_type'] ?? 'Bearer'],
                forwardUrl: $this->forwardUrl($request->session()->pull(self::CALLBACK_URL_SESSION_KEY))
            );
        } catch (\Throwable $exception) {
            Caronte::clearToken();

            return CaronteResponse::unauthorized(
                message: $exception->getMessage(),
                forwardUrl: (string) config('caronte.login_url')
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
            'post_logout_redirect_uri' => url((string) config('caronte.login_url')),
        ]));

        return redirect()->away($url);
    }

    private function forwardUrl(mixed $candidate): string
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
}
