<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class GroupSessionConfigCommandTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().DIRECTORY_SEPARATOR.'apollo-group-command-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->workspace, 0777, true));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_command_returns_zero_for_a_valid_group(): void
    {
        $config = $this->createGroup('same-secret', 'same-secret');

        $result = $this->runCommand(['--config', $config]);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString("Group 'command-group' session configuration is consistent", $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public function test_command_returns_one_for_violations_without_leaking_sensitive_values(): void
    {
        $config = $this->createGroup('first-secret', 'second-secret');

        $result = $this->runCommand(['--config', $config]);

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertStringContainsString("Key 'SECRET' is not identical", $result['stderr']);
        self::assertStringNotContainsString('first-secret', $result['stderr']);
        self::assertStringNotContainsString('second-secret', $result['stderr']);
    }

    public function test_command_returns_two_for_usage_and_configuration_errors(): void
    {
        $missingOption = $this->runCommand([]);
        self::assertSame(2, $missingOption['exit']);
        self::assertStringContainsString('--config option is required', $missingOption['stderr']);

        $invalidConfig = $this->workspace.DIRECTORY_SEPARATOR.'invalid.json';
        file_put_contents($invalidConfig, '{invalid');
        $malformed = $this->runCommand(['--config', $invalidConfig]);
        self::assertSame(2, $malformed['exit']);
        self::assertStringContainsString('invalid JSON', $malformed['stderr']);
    }

    private function createGroup(string $firstSecret, string $secondSecret): string
    {
        foreach (['one' => $firstSecret, 'two' => $secondSecret] as $application => $secret) {
            $directory = $this->workspace.DIRECTORY_SEPARATOR.$application;
            mkdir($directory);
            file_put_contents($directory.DIRECTORY_SEPARATOR.'.env', "SECRET={$secret}\n");
        }

        $config = [
            'version' => 1,
            'group' => 'command-group',
            'applications' => [
                ['name' => 'one', 'path' => 'one', 'env_files' => ['.env']],
                ['name' => 'two', 'path' => 'two', 'env_files' => ['.env']],
            ],
            'rules' => [
                ['type' => 'same', 'key' => 'SECRET', 'required' => true, 'sensitive' => true],
            ],
        ];
        $path = $this->workspace.DIRECTORY_SEPARATOR.'group-session-config.json';
        file_put_contents($path, json_encode($config, JSON_THROW_ON_ERROR));

        return $path;
    }

    /**
     * @param  list<string>  $arguments
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function runCommand(array $arguments): array
    {
        $command = [PHP_BINARY, __DIR__.'/../../bin/validate-group-session-config', ...$arguments];
        $pipes = [];
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            __DIR__.'/../..',
        );
        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        self::assertIsString($stdout);
        self::assertIsString($stderr);

        return compact('exit', 'stdout', 'stderr');
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
