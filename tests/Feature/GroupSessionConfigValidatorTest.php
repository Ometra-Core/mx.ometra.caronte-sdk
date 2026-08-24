<?php

declare(strict_types=1);

namespace Tests\Feature;

use Ometra\Caronte\Support\GroupSessionConfigException;
use Ometra\Caronte\Support\GroupSessionConfigValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GroupSessionConfigValidatorTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().DIRECTORY_SEPARATOR.'apollo-group-validator-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->workspace, 0777, true));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_it_validates_env_merging_comments_quotes_crlf_optional_keys_and_rule_targets(): void
    {
        $this->writeEnv('app-one', 'base.env', "# comment\r\nMODE = old\r\nSECRET='same-secret'\r\n");
        $this->writeEnv('app-one', 'override.env', "MODE=\"shared\"\r\n");
        $this->writeEnv('app-two', 'base.env', "MODE=shared\nSECRET=same-secret\nOPTIONAL=present\n");
        $config = $this->writeConfig([
            'version' => 1,
            'group' => 'example-group',
            'applications' => [
                ['name' => 'one', 'path' => 'app-one', 'env_files' => ['base.env', 'override.env']],
                ['name' => 'two', 'path' => 'app-two', 'env_files' => ['base.env']],
            ],
            'rules' => [
                ['type' => 'equals', 'key' => 'MODE', 'value' => 'shared', 'required' => true],
                ['type' => 'same', 'key' => 'SECRET', 'required' => true, 'sensitive' => true],
                [
                    'type' => 'equals',
                    'key' => 'OPTIONAL',
                    'value' => 'present',
                    'required' => false,
                    'applications' => ['two'],
                ],
            ],
        ]);

        $result = (new GroupSessionConfigValidator)->validate($config);

        self::assertTrue($result->isValid());
        self::assertSame('example-group', $result->group);
        self::assertSame(2, $result->applicationCount);
        self::assertSame([], $result->violations);
    }

    public function test_it_reports_required_expected_and_same_rule_violations_without_values(): void
    {
        $this->writeEnv('app-one', 'session.env', "MODE=wrong\nSECRET=first-secret\n");
        $this->writeEnv('app-two', 'session.env', "SECRET=second-secret\n");
        $config = $this->writeConfig($this->baseConfig([
            ['type' => 'equals', 'key' => 'MODE', 'value' => 'expected-secret-value', 'required' => true, 'sensitive' => true],
            ['type' => 'same', 'key' => 'SECRET', 'required' => true, 'sensitive' => true],
        ]));

        $result = (new GroupSessionConfigValidator)->validate($config);
        $output = implode("\n", $result->violations);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("key 'MODE' does not match", $output);
        self::assertStringContainsString("missing required key 'MODE'", $output);
        self::assertStringContainsString("Key 'SECRET' is not identical", $output);
        self::assertStringNotContainsString('wrong', $output);
        self::assertStringNotContainsString('first-secret', $output);
        self::assertStringNotContainsString('second-secret', $output);
        self::assertStringNotContainsString('expected-secret-value', $output);
    }

    public function test_missing_env_files_are_validation_violations(): void
    {
        mkdir($this->workspace.DIRECTORY_SEPARATOR.'app-one');
        mkdir($this->workspace.DIRECTORY_SEPARATOR.'app-two');
        $config = $this->writeConfig($this->baseConfig([
            ['type' => 'same', 'key' => 'SESSION_KEY', 'required' => false],
        ]));

        $result = (new GroupSessionConfigValidator)->validate($config);

        self::assertFalse($result->isValid());
        self::assertCount(2, $result->violations);
        self::assertStringContainsString('is missing', $result->violations[0]);
    }

    /** @param  array<string, mixed>  $config */
    #[DataProvider('invalidConfigProvider')]
    public function test_it_rejects_invalid_configuration(array $config, string $message): void
    {
        $path = $this->writeConfig($config);

        $this->expectException(GroupSessionConfigException::class);
        $this->expectExceptionMessage($message);

        (new GroupSessionConfigValidator)->validate($path);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigProvider(): iterable
    {
        yield 'unknown version' => [
            ['version' => 2, 'group' => 'group', 'applications' => [], 'rules' => []],
            'Unsupported or missing configuration version',
        ];

        yield 'duplicate names' => [
            [
                'version' => 1,
                'group' => 'group',
                'applications' => [
                    ['name' => 'same', 'path' => 'one', 'env_files' => ['.env']],
                    ['name' => 'same', 'path' => 'two', 'env_files' => ['.env']],
                ],
                'rules' => [['type' => 'same', 'key' => 'KEY']],
            ],
            "Application name 'same' is duplicated",
        ];

        yield 'path traversal' => [
            [
                'version' => 1,
                'group' => 'group',
                'applications' => [['name' => 'app', 'path' => '../outside', 'env_files' => ['.env']]],
                'rules' => [['type' => 'same', 'key' => 'KEY']],
            ],
            'must be a relative path without traversal',
        ];

        yield 'unsupported rule' => [
            [
                'version' => 1,
                'group' => 'group',
                'applications' => [['name' => 'app', 'path' => 'app', 'env_files' => ['.env']]],
                'rules' => [['type' => 'different', 'key' => 'KEY']],
            ],
            'unsupported type',
        ];

        yield 'unknown target' => [
            [
                'version' => 1,
                'group' => 'group',
                'applications' => [['name' => 'app', 'path' => 'app', 'env_files' => ['.env']]],
                'rules' => [['type' => 'same', 'key' => 'KEY', 'applications' => ['unknown']]],
            ],
            'references an unknown application',
        ];
    }

    public function test_it_rejects_malformed_json(): void
    {
        $path = $this->workspace.DIRECTORY_SEPARATOR.'group-session-config.json';
        file_put_contents($path, '{invalid');

        $this->expectException(GroupSessionConfigException::class);
        $this->expectExceptionMessage('invalid JSON');

        (new GroupSessionConfigValidator)->validate($path);
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return array<string, mixed>
     */
    private function baseConfig(array $rules): array
    {
        return [
            'version' => 1,
            'group' => 'test-group',
            'applications' => [
                ['name' => 'one', 'path' => 'app-one', 'env_files' => ['session.env']],
                ['name' => 'two', 'path' => 'app-two', 'env_files' => ['session.env']],
            ],
            'rules' => $rules,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function writeConfig(array $config): string
    {
        $path = $this->workspace.DIRECTORY_SEPARATOR.'group-session-config.json';
        file_put_contents($path, json_encode($config, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return $path;
    }

    private function writeEnv(string $application, string $file, string $contents): void
    {
        $directory = $this->workspace.DIRECTORY_SEPARATOR.$application;
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($directory.DIRECTORY_SEPARATOR.$file, $contents);
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
