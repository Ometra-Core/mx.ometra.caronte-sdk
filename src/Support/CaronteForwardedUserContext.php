<?php

namespace Ometra\Caronte\Support;

use stdClass;

class CaronteForwardedUserContext
{
    public function __construct(
        public readonly string $userToken,
        public readonly stdClass $user,
        public readonly ?string $tenantId = null,
        public readonly ?string $tokenId = null,
    ) {
        //
    }
}
