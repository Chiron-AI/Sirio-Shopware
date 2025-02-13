<?php declare(strict_types=1);

namespace Chiron\Sirio\Cookie;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;

class CustomCookieProvider implements CookieProviderInterface
{
    public const CHIRON_SIRIO_ENABLED_COOKIE_NAME = 'sirio_cart';

    private const CHIRON_SIRIO_ENABLED_COOKIE_DATA = [
        'snippet_name' => 'chironSirio.cookie.groupStatisticalSirio',
        'cookie' => self::CHIRON_SIRIO_ENABLED_COOKIE_NAME,
        'value' => '1',
        'expiration' => '90',
    ];

    private const CHIRON_SIRIO_COOKIE_GROUP_DATA = [
        'snippet_name' => 'chironSirio.cookie.groupStatistical',
        'snippet_description' => 'chironSirio.cookie.groupStatisticalDescription',
        'entries' => [
            self::CHIRON_SIRIO_ENABLED_COOKIE_DATA,
        ],
    ];

    /**
     * @var CookieProviderInterface
     */
    private $originalService;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        CookieProviderInterface $service,
        SystemConfigService $systemConfigService
    ) {
        $this->originalService = $service;
        $this->systemConfigService = $systemConfigService;
    }

    public function getCookieGroups(): array
    {
        if (!$this->systemConfigService->get('chironSirio.config.hasSWConsentSupport')) {
            return $this->originalService->getCookieGroups();
        }

        return $this->addEntryToStatisticalGroup();
    }

    protected function addEntryToStatisticalGroup(): array
    {
        $cookieGroups = $this->originalService->getCookieGroups();

        foreach ($cookieGroups as &$group) {
            if ($group['snippet_name'] !== 'cookie.groupStatistical') {
                continue;
            }

            $group['entries'] = array_merge($group['entries'], [self::CHIRON_SIRIO_ENABLED_COOKIE_DATA]);

            return $cookieGroups;
        }

        return array_merge($cookieGroups, [self::CHIRON_SIRIO_COOKIE_GROUP_DATA]);
    }
}
