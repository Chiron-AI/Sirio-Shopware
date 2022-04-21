<?php declare(strict_types=1);

namespace Chiron\Sirio\Services;

interface SirioProfilingModulesInterface
{
    public function isActive(?string $salesChannelId = null): ?bool;

    public function hasSWConsentSupport(?string $salesChannelId = null): int;
}
