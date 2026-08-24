<?php

declare(strict_types=1);

namespace Ometra\Caronte\Support;

use JsonException;

final class GroupSessionConfigValidator
{
    public function validate(string $configPath, ?string $workspace = null): GroupSessionValidationResult
    {
        $configPath = $this->existingFile($configPath, 'Configuration file');
        $config = $this->readConfig($configPath);
        $workspacePath = $this->existingDirectory(
            $workspace ?? dirname($configPath),
            'Workspace',
        );

        $group = $this->requiredString($config, 'group');
        $applications = $this->applications($config, $workspacePath);
        $rules = $this->rules($config, array_keys($applications));
        $violations = [];
        $valuesByApplication = [];

        foreach ($applications as $name => $application) {
            $valuesByApplication[$name] = $this->readApplicationValues(
                $name,
                $application,
                $workspacePath,
                $violations,
            );
        }

        foreach ($rules as $rule) {
            $targets = $rule['applications'] ?? array_keys($applications);
            $this->applyRule($rule, $targets, $valuesByApplication, $violations);
        }

        return new GroupSessionValidationResult(
            $group,
            count($applications),
            array_values(array_unique($violations)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readConfig(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new GroupSessionConfigException('Configuration file cannot be read.');
        }

        try {
            $config = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GroupSessionConfigException('Configuration file contains invalid JSON.', previous: $exception);
        }

        if (! is_array($config)) {
            throw new GroupSessionConfigException('Configuration root must be a JSON object.');
        }

        if (($config['version'] ?? null) !== 1) {
            throw new GroupSessionConfigException('Unsupported or missing configuration version.');
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, array{path: string, env_files: list<string>}>
     */
    private function applications(array $config, string $workspace): array
    {
        $items = $config['applications'] ?? null;
        if (! is_array($items) || $items === []) {
            throw new GroupSessionConfigException('Applications must be a non-empty JSON array.');
        }

        $applications = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw new GroupSessionConfigException("Application at index {$index} must be an object.");
            }

            $name = $this->requiredString($item, 'name', "Application at index {$index}");
            if (isset($applications[$name])) {
                throw new GroupSessionConfigException("Application name '{$name}' is duplicated.");
            }

            $path = $this->requiredString($item, 'path', "Application '{$name}'");
            $this->assertRelativePath($path, "Application '{$name}' path");
            $this->assertContainedPath($workspace, $path, "Application '{$name}' path");

            $files = $item['env_files'] ?? null;
            if (! is_array($files) || $files === []) {
                throw new GroupSessionConfigException("Application '{$name}' env_files must be a non-empty array.");
            }

            $envFiles = [];
            foreach ($files as $file) {
                if (! is_string($file) || trim($file) === '') {
                    throw new GroupSessionConfigException("Application '{$name}' contains an invalid env file path.");
                }

                $this->assertRelativePath($file, "Application '{$name}' env file");
                $envFiles[] = $file;
            }

            $applications[$name] = ['path' => $path, 'env_files' => $envFiles];
        }

        return $applications;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $applicationNames
     * @return list<array{type: 'equals'|'same', key: string, value?: string, required: bool, sensitive: bool, applications?: list<string>}>
     */
    private function rules(array $config, array $applicationNames): array
    {
        $items = $config['rules'] ?? null;
        if (! is_array($items) || $items === []) {
            throw new GroupSessionConfigException('Rules must be a non-empty JSON array.');
        }

        $rules = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw new GroupSessionConfigException("Rule at index {$index} must be an object.");
            }

            $type = $item['type'] ?? null;
            if ($type !== 'equals' && $type !== 'same') {
                throw new GroupSessionConfigException("Rule at index {$index} has an unsupported type.");
            }

            $key = $this->requiredString($item, 'key', "Rule at index {$index}");
            $required = $item['required'] ?? true;
            $sensitive = $item['sensitive'] ?? false;
            if (! is_bool($required) || ! is_bool($sensitive)) {
                throw new GroupSessionConfigException("Rule '{$key}' required and sensitive values must be booleans.");
            }

            $rule = compact('type', 'key', 'required', 'sensitive');
            if ($type === 'equals') {
                if (! array_key_exists('value', $item) || ! is_string($item['value'])) {
                    throw new GroupSessionConfigException("Equals rule '{$key}' requires a string value.");
                }
                $rule['value'] = $item['value'];
            }

            if (array_key_exists('applications', $item)) {
                if (! is_array($item['applications']) || $item['applications'] === []) {
                    throw new GroupSessionConfigException("Rule '{$key}' applications must be a non-empty array.");
                }

                $targets = [];
                foreach ($item['applications'] as $target) {
                    if (! is_string($target) || ! in_array($target, $applicationNames, true)) {
                        throw new GroupSessionConfigException("Rule '{$key}' references an unknown application.");
                    }
                    if (in_array($target, $targets, true)) {
                        throw new GroupSessionConfigException("Rule '{$key}' contains a duplicated application.");
                    }
                    $targets[] = $target;
                }
                $rule['applications'] = $targets;
            }

            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * @param  array{path: string, env_files: list<string>}  $application
     * @param  list<string>  $violations
     * @return array<string, string>
     */
    private function readApplicationValues(
        string $name,
        array $application,
        string $workspace,
        array &$violations,
    ): array {
        $root = $this->join($workspace, $application['path']);
        if (! is_dir($root)) {
            $violations[] = "Application '{$name}' directory is missing.";

            return [];
        }

        $realRoot = realpath($root);
        if ($realRoot === false || ! $this->isContained($workspace, $realRoot)) {
            throw new GroupSessionConfigException("Application '{$name}' path resolves outside the workspace.");
        }

        $values = [];
        foreach ($application['env_files'] as $file) {
            $this->assertContainedPath($realRoot, $file, "Application '{$name}' env file");
            $path = $this->join($realRoot, $file);
            if (! is_file($path)) {
                $violations[] = "Application '{$name}' env file '{$file}' is missing.";

                continue;
            }

            $realFile = realpath($path);
            if ($realFile === false || ! $this->isContained($realRoot, $realFile)) {
                throw new GroupSessionConfigException("Application '{$name}' env file resolves outside its directory.");
            }

            foreach ($this->readEnvironmentFile($realFile) as $key => $value) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    private function readEnvironmentFile(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $values = [];
        foreach ($lines as $line) {
            $line = rtrim($line, "\r");
            if (preg_match('/^\s*#/', $line) === 1 || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }

            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * @param  array{type: 'equals'|'same', key: string, value?: string, required: bool, sensitive: bool, applications?: list<string>}  $rule
     * @param  list<string>  $targets
     * @param  array<string, array<string, string>>  $valuesByApplication
     * @param  list<string>  $violations
     */
    private function applyRule(array $rule, array $targets, array $valuesByApplication, array &$violations): void
    {
        $present = [];
        foreach ($targets as $application) {
            if (! array_key_exists($rule['key'], $valuesByApplication[$application])) {
                if ($rule['required']) {
                    $violations[] = "Application '{$application}' is missing required key '{$rule['key']}'.";
                }

                continue;
            }

            $value = $valuesByApplication[$application][$rule['key']];
            $present[$application] = $rule['sensitive'] ? hash('sha256', $value) : $value;

            if ($rule['type'] === 'equals') {
                $expected = $rule['sensitive'] ? hash('sha256', $rule['value'] ?? '') : ($rule['value'] ?? '');
                if (! hash_equals($expected, $present[$application])) {
                    $violations[] = "Application '{$application}' key '{$rule['key']}' does not match its expected value.";
                }
            }
        }

        if ($rule['type'] === 'same' && count(array_unique(array_values($present), SORT_STRING)) > 1) {
            $violations[] = "Key '{$rule['key']}' is not identical across its target applications.";
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function requiredString(array $values, string $key, string $context = 'Configuration'): string
    {
        $value = $values[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new GroupSessionConfigException("{$context} requires a non-empty '{$key}' string.");
        }

        return $value;
    }

    private function existingFile(string $path, string $label): string
    {
        $resolved = realpath($path);
        if ($resolved === false || ! is_file($resolved)) {
            throw new GroupSessionConfigException("{$label} does not exist.");
        }

        return $resolved;
    }

    private function existingDirectory(string $path, string $label): string
    {
        $resolved = realpath($path);
        if ($resolved === false || ! is_dir($resolved)) {
            throw new GroupSessionConfigException("{$label} does not exist or is not a directory.");
        }

        return rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    private function assertRelativePath(string $path, string $label): void
    {
        $normalized = str_replace('\\', '/', $path);
        if (
            $normalized === ''
            || str_starts_with($normalized, '/')
            || preg_match('/^[A-Za-z]:\//', $normalized) === 1
            || in_array('..', explode('/', $normalized), true)
        ) {
            throw new GroupSessionConfigException("{$label} must be a relative path without traversal.");
        }
    }

    private function assertContainedPath(string $root, string $relative, string $label): void
    {
        $candidate = $this->join($root, $relative);
        $existingAncestor = $candidate;
        while (! file_exists($existingAncestor) && dirname($existingAncestor) !== $existingAncestor) {
            $existingAncestor = dirname($existingAncestor);
        }

        $resolvedAncestor = realpath($existingAncestor);
        if ($resolvedAncestor === false || ! $this->isContained($root, $resolvedAncestor)) {
            throw new GroupSessionConfigException("{$label} resolves outside its allowed directory.");
        }
    }

    private function isContained(string $root, string $path): bool
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $path = str_replace('\\', '/', $path);

        return $path === $root || str_starts_with($path, $root.'/');
    }

    private function join(string $root, string $relative): string
    {
        return rtrim($root, '/\\').DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    }
}
