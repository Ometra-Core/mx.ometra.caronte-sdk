<?php

namespace Ometra\Caronte\Support;

use Equidna\Toolkit\Helpers\RouteHelper;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class CaronteResponse
{
    public static function success(
        string $message,
        mixed $data = null,
        array $headers = [],
        ?string $forwardUrl = null
    ): SymfonyResponse {
        return static::respond(200, $message, [], $data, $headers, $forwardUrl);
    }

    public static function badRequest(
        string $message,
        array $errors = [],
        array $headers = [],
        ?string $forwardUrl = null
    ): SymfonyResponse {
        return static::respond(400, $message, $errors, null, $headers, $forwardUrl);
    }

    public static function unauthorized(
        string $message,
        array $errors = [],
        array $headers = [],
        ?string $forwardUrl = null
    ): SymfonyResponse {
        return static::respond(401, $message, $errors, null, $headers, $forwardUrl);
    }

    public static function forbidden(
        string $message,
        array $errors = [],
        array $headers = [],
        ?string $forwardUrl = null
    ): SymfonyResponse {
        return static::respond(403, $message, $errors, null, $headers, $forwardUrl);
    }

    public static function conflict(
        string $message,
        array $errors = [],
        mixed $data = null,
        array $headers = [],
        ?string $forwardUrl = null
    ): SymfonyResponse {
        return static::respond(409, $message, $errors, $data, $headers, $forwardUrl);
    }

    public static function notFound(
        string $message,
        array $errors = [],
        array $headers = [],
        ?string $forwardUrl = null
    ): SymfonyResponse {
        return static::respond(404, $message, $errors, null, $headers, $forwardUrl);
    }

    public static function unprocessable(
        string $message,
        array $errors = [],
        array $headers = [],
        ?string $forwardUrl = null
    ): SymfonyResponse {
        return static::respond(422, $message, $errors, null, $headers, $forwardUrl);
    }

    public static function error(
        string $message,
        array $errors = [],
        array $headers = [],
        ?string $forwardUrl = null
    ): SymfonyResponse {
        return static::respond(500, $message, $errors, null, $headers, $forwardUrl);
    }

    public static function handleException(
        Throwable $exception,
        array $errors = [],
        array $headers = [],
        ?string $forwardUrl = null
    ): SymfonyResponse {
        if ($errors === [] && method_exists($exception, 'errors')) {
            $candidate = $exception->errors();

            if (is_array($candidate)) {
                $errors = $candidate;
            }
        }

        $status = (int) $exception->getCode();

        if ($status < 400 || $status > 599) {
            $status = 500;
        }

        return match ($status) {
            400 => static::badRequest($exception->getMessage(), $errors, $headers, $forwardUrl),
            401 => static::unauthorized($exception->getMessage(), $errors, $headers, $forwardUrl),
            403 => static::forbidden($exception->getMessage(), $errors, $headers, $forwardUrl),
            404 => static::notFound($exception->getMessage(), $errors, $headers, $forwardUrl),
            409 => static::conflict($exception->getMessage(), $errors, null, $headers, $forwardUrl),
            422 => static::unprocessable($exception->getMessage(), $errors, $headers, $forwardUrl),
            default => static::error($exception->getMessage(), $errors, $headers, $forwardUrl),
        };
    }

    private static function respond(
        int $status,
        string $message,
        array $errors = [],
        mixed $data = null,
        array $headers = [],
        ?string $forwardUrl = null
    ): SymfonyResponse {
        if (RouteHelper::wantsJson()) {
            return static::json($status, $message, $errors, $data, $headers);
        }

        return static::redirect($status, $message, $errors, $data, $headers, $forwardUrl);
    }

    private static function json(
        int $status,
        string $message,
        array $errors = [],
        mixed $data = null,
        array $headers = []
    ): JsonResponse {
        $payload = [
            'status' => $status,
            'message' => static::sanitizeMessage($status, $message),
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        $sanitizedErrors = static::sanitizeErrors($status, $errors);

        if ($status >= 400 && $sanitizedErrors !== []) {
            $payload['errors'] = $sanitizedErrors;
        }

        return response()->json($payload, $status, static::stringHeaders($headers));
    }

    private static function redirect(
        int $status,
        string $message,
        array $errors = [],
        mixed $data = null,
        array $headers = [],
        ?string $forwardUrl = null
    ): SymfonyResponse {
        $sanitizedMessage = static::sanitizeMessage($status, $message);
        $sanitizedErrors = static::sanitizeErrors($status, $errors);
        $destination = $forwardUrl ?: url()->previous();

        $response = redirect()->to($destination, 302, static::stringHeaders($headers))
            ->with([
                'status' => $status,
                'message' => $sanitizedMessage,
                'data' => $data,
            ]);

        if ($status >= 400) {
            $response = $response
                ->with('error', $sanitizedMessage)
                ->withErrors($sanitizedErrors === [] ? ['general' => [$sanitizedMessage]] : static::redirectErrors($sanitizedErrors))
                ->withInput(request()->except([
                    'password',
                    'password_confirmation',
                    'current_password',
                    'new_password',
                ]));
        } else {
            $response = $response->with('success', $sanitizedMessage);
        }

        if ($forwardUrl !== null && static::isInertiaRequest()) {
            return Inertia::location($response);
        }

        return $response;
    }

    private static function sanitizeMessage(int $status, string $message): string
    {
        if ($status >= 500 && !config('app.debug', false)) {
            return 'An unexpected error occurred.';
        }

        return $message;
    }

    private static function isInertiaRequest(): bool
    {
        return app()->bound('request') && request()->headers->get('X-Inertia') === 'true';
    }

    private static function sanitizeErrors(int $status, array $errors): array
    {
        if ($status >= 500 && !config('app.debug', false)) {
            return [];
        }

        return $errors;
    }

    /**
     * @param  array<int|string, mixed>  $errors
     * @return array<string, array<int, string>|string>
     */
    private static function redirectErrors(array $errors): array
    {
        $redirectErrors = [];
        $generalErrors = [];

        foreach ($errors as $key => $value) {
            if (! is_string($key)) {
                if (is_string($value)) {
                    $generalErrors[] = $value;
                }

                continue;
            }

            if (is_string($value)) {
                $redirectErrors[$key] = $value;
                continue;
            }

            if (
                is_array($value)
                && collect($value)->every(fn(mixed $item): bool => is_string($item))
            ) {
                $redirectErrors[$key] = $value;
            }
        }

        if ($generalErrors !== []) {
            $redirectErrors['general'] = $generalErrors;
        }

        return $redirectErrors === [] ? ['general' => ['Request failed.']] : $redirectErrors;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, string>
     */
    private static function stringHeaders(array $headers): array
    {
        return collect($headers)
            ->filter(fn(mixed $value, mixed $key): bool => is_string($key) && is_string($value))
            ->all();
    }
}
