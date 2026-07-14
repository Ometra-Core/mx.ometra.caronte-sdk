<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Ometra\Caronte\Support\CaronteCallbackUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CallbackUrlSecurityTest extends TestCase
{
    public function test_it_accepts_relative_same_origin_and_encoded_same_origin_callbacks(): void
    {
        $request = Request::create('https://client.test/login', 'GET');

        $this->assertSame('/dashboard?tab=roles#users', CaronteCallbackUrl::resolve($request, '/dashboard?tab=roles#users'));
        $this->assertSame(
            'https://client.test:443/dashboard',
            CaronteCallbackUrl::resolve($request, 'https://client.test:443/dashboard')
        );
        $this->assertSame(
            '/reports/monthly',
            CaronteCallbackUrl::resolve($request, base64_encode('/reports/monthly'))
        );
    }

    #[DataProvider('unsafeCallbacks')]
    public function test_it_rejects_unsafe_callbacks(string $callback): void
    {
        config()->set('caronte.routes.success_url', '/safe');
        $request = Request::create('https://client.test/login', 'GET');

        $this->assertSame('/safe', CaronteCallbackUrl::resolve($request, $callback));
    }

    /** @return array<string, array{string}> */
    public static function unsafeCallbacks(): array
    {
        return [
            'external origin' => ['https://evil.test/steal'],
            'encoded external origin' => [base64_encode('https://evil.test/steal')],
            'protocol relative' => ['//evil.test/steal'],
            'encoded protocol relative' => ['/%2fhttps://evil.test/steal'],
            'javascript scheme' => ['javascript:alert(1)'],
            'data scheme' => ['data:text/html,payload'],
            'userinfo' => ['https://client.test@evil.test/steal'],
            'same-origin userinfo' => ['https://user@client.test/steal'],
            'backslash' => ['/safe\\evil'],
            'encoded backslash' => ['/safe%5cevil'],
            'control character' => ["/safe\nLocation: https://evil.test"],
            'wrong port' => ['https://client.test:444/steal'],
        ];
    }
}
