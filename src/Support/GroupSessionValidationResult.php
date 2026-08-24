<?php

declare(strict_types=1);

namespace Ometra\Caronte\Support;

final readonly class GroupSessionValidationResult
{
    /**
     * @param  list<string>  $violations
     */
    public function __construct(
        public string $group,
        public int $applicationCount,
        public array $violations,
    ) {}

    public function isValid(): bool
    {
        return $this->violations === [];
    }
}
