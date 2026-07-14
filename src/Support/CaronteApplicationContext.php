<?php

namespace Ometra\Caronte\Support;

class CaronteApplicationContext
{
    public function __construct(
        public readonly string $appCn,
        public readonly string $appId,
        public readonly ?string $applicationToken,
        public readonly bool $authenticatedAsGroup = false,
        public readonly ?string $groupId = null,
        public readonly ?string $sourceAppId = null,
        public readonly ?string $sourceAppCn = null,
        public readonly ?string $groupTokenId = null,
        public readonly ?string $applicationTokenId = null,
    ) {
        //
    }
}
