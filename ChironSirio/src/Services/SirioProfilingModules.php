<?php declare(strict_types=1);

namespace Chiron\Sirio\Services;

use Doctrine\DBAL\Connection;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SirioProfilingModules implements SirioProfilingModulesInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var array
     */
    private $modules;


    const SIRIO_URL_STAGE = "api.sirio-stage.chiron.ai";
    const SIRIO_URL_PRODUCTION = "api.sirio.chiron.ai";

    public function __construct(
        Connection $connection,
        SystemConfigService $systemConfigService
    ) {
        $this->connection = $connection;
        $this->systemConfigService = $systemConfigService;
    }

    public function getResponseRoutes(): array
    {
        $modules = [];
        foreach ($this->getModules() as $key => $module) {
            if (!empty($module)) {
                $modules = array_merge($modules, explode(',', $module));
            }
        }

        return $modules;
    }

    public function isActive(?string $salesChannelId = null): ?bool
    {
        return $this->systemConfigService->get(
            'ChironSirio.config.enable',
            $salesChannelId
        );
    }

    public function getSirioUrl(?string $salesChannelId = null){
        return "https://".($this->getDevMode($salesChannelId)?self::SIRIO_URL_STAGE:self::SIRIO_URL_PRODUCTION);
    }

    public function getDevMode(?string $salesChannelId = null){
        return $this->systemConfigService->get(
            'ChironSirio.config.enableDevMode',
            $salesChannelId
        );
    }

    public function getDebugMode(?string $salesChannelId = null){
        return $this->systemConfigService->get(
            'ChironSirio.config.debugEnable',
            $salesChannelId
        );
    }

    public function hasSWConsentSupport(?string $salesChannelId = null): int
    {
        return $this->systemConfigService->get(
            'ChironSirio.config.hasSWConsentSupport',
            $salesChannelId
        ) ? 1 : 0;
    }

}
