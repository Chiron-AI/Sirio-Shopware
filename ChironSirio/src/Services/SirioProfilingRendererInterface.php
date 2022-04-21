<?php declare(strict_types=1);

namespace Chiron\Sirio\Services;

interface SirioProfilingRendererInterface
{
    public function renderSirioProfiling(string $route): sirioProfilingRendererInterface;

    public function getVariables(string $route): array;

    public function setVariables(string $route, $variables): sirioProfilingRendererInterface;

    public function getSirioProfiling($route): ?string;
}
