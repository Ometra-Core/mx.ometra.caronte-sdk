<?php

namespace Ometra\Caronte\Support;

use Illuminate\Support\Facades\Log;

final class LegacyDeprecation
{
    /** @var array<string, true> */
    private static array $reported = [];

    public static function warn(string $feature, string $replacement): void
    {
        if (isset(self::$reported[$feature])) {
            return;
        }

        self::$reported[$feature] = true;

        Log::warning(sprintf(
            'Deprecated Caronte SDK compatibility [%s] was used; migrate to [%s] before SDK 5.',
            $feature,
            $replacement
        ));
    }
}
