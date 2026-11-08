<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Contracts;

/**
 * Read access to the generic key-value settings a plugin declared in its
 * manifest's "settings" schema — see "Rozruch i rozszerzalność"
 * (08-extensions.md#projekt-techniczny-p1-012). Writing happens only
 * through the panel (Presentation\Controllers\Panel\ExtensionController),
 * never from plugin code itself — a plugin only ever reads its own
 * settings, it does not set them programmatically.
 */
interface ExtensionSettingsStore
{
    public function get(string $extensionId, string $key, ?string $default = null): ?string;

    /** @return array<string, string> */
    public function all(string $extensionId): array;
}
