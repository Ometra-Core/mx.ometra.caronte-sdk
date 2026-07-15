<?php

namespace Tests\Feature;

use Equidna\BeeHive\Tenancy\TenantContext;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Ometra\Caronte\Caronte;
use Ometra\Caronte\Exceptions\CaronteApiException;
use Ometra\Caronte\Support\CaronteApplicationToken;
use Ometra\Caronte\Support\CaronteHttpClient;
use Tests\TestCase;

class CaronteHttpClientTest extends TestCase
{
    private CaronteHttpClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('caronte.http.timeout', 7);
        config()->set('caronte.http.retries', 1);
        config()->set('caronte.http.retry_sleep', 0);
        config()->set('caronte.tls_verify', false);

        $this->client = new class extends CaronteHttpClient {
            protected function getBaseUrl(): string
            {
                return 'https://service.test/api';
            }

            protected function makeApplicationToken(): string
            {
                return CaronteApplicationToken::make();
            }
        };

        Http::preventStrayRequests();
    }

    public function testApplicationRawRequestReturnsUnparsedBinaryResponseWithTenant(): void
    {
        $tenant = new TenantContext();
        $tenant->set('tenant-42');
        app()->instance(TenantContext::class, $tenant);

        Http::fake(['https://service.test/api/media/1' => Http::response('binary-data', 200)]);

        $response = $this->client->applicationRawRequest('GET', 'media/1');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('binary-data', $response->body());
        Http::assertSent(fn (Request $request): bool =>
            $request->hasHeader('Accept', '*/*')
            && $request->hasHeader('X-Application-Token')
            && ! $request->hasHeader('X-Group-Token')
            && $request->hasHeader('X-Tenant-Id', 'tenant-42')
        );
    }

    public function testApplicationRawRequestCanForwardAUserToken(): void
    {
        Http::fake(['https://service.test/api/jobs' => Http::response('', 202)]);

        $this->client->applicationRawRequest('POST', 'jobs', userToken: ' delegated-token ');

        Http::assertSent(fn (Request $request): bool =>
            $request->hasHeader('X-User-Token', 'delegated-token')
        );
    }

    public function testUserRawRequestUsesCurrentUserAndGroupTokenExclusively(): void
    {
        config()->set('caronte.application_group_id', 'group-alpha');
        config()->set('caronte.application_group_secret', 'group-secret-with-minimum-length-32');
        $tenant = new TenantContext();
        $tenant->set('tenant-42');
        app()->instance(TenantContext::class, $tenant);
        app()->instance(Caronte::class, new class {
            public function getToken(): object
            {
                return new class {
                    public function toString(): string
                    {
                        return 'current-user-token';
                    }
                };
            }
        });

        Http::fake(['https://service.test/api/media' => Http::response('', 204)]);

        $this->client->userRawRequest('GET', 'media');

        Http::assertSent(fn (Request $request): bool =>
            $request->hasHeader('X-Group-Token')
            && ! $request->hasHeader('X-Application-Token')
            && $request->hasHeader('X-User-Token', 'current-user-token')
            && $request->hasHeader('X-Tenant-Id', 'tenant-42')
        );
    }

    public function testJsonRequestStillParsesTheCanonicalEnvelope(): void
    {
        Http::fake(['https://service.test/api/items*' => Http::response([
            'status' => 200,
            'message' => 'ok',
            'data' => ['id' => 1],
            'errors' => [],
        ])]);

        $result = $this->client->applicationRequest('GET', 'items', query: ['active' => true]);

        $this->assertSame(['id' => 1], $result['data']);
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://service.test/api/items?active=1'
            && $request->hasHeader('Accept', 'application/json')
        );
    }

    public function testMultipartSupportsNestedFilesStreamsAndScalarValues(): void
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'stream-data');
        rewind($stream);

        Http::fake(['https://service.test/api/media' => Http::response([
            'status' => 201,
            'message' => 'created',
            'data' => [],
            'errors' => [],
        ], 201)]);

        $this->client->applicationRequest('POST', 'media', [
            'file' => UploadedFile::fake()->createWithContent('cover.txt', 'cover-data'),
            'attachments' => [
                UploadedFile::fake()->createWithContent('one.txt', 'one'),
                UploadedFile::fake()->createWithContent('two.txt', 'two'),
            ],
            'nested' => ['stream' => $stream, 'enabled' => true, 'optional' => null],
        ]);

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return str_contains($request->header('Content-Type')[0] ?? '', 'multipart/form-data')
                && str_contains($body, 'name="file"; filename="cover.txt"')
                && substr_count($body, 'name="attachments[]"') === 2
                && str_contains($body, 'name="nested[stream]"')
                && str_contains($body, 'stream-data')
                && str_contains($body, 'name="nested[enabled]"')
                && str_contains($body, "\r\n\r\n1\r\n")
                && str_contains($body, 'name="nested[optional]"');
        });
    }

    public function testUnsupportedMethodThrowsForRawAndParsedRequests(): void
    {
        $this->expectException(CaronteApiException::class);

        $this->client->applicationRawRequest('OPTIONS', 'items');
    }
}
