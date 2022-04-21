<?php declare(strict_types=1);

namespace Chiron\Sirio\Twig;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Chiron\Sirio\Utility\SessionUtility;

class SirioExtension extends AbstractExtension
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var CartRuleLoader
     */
    private $cartRuleLoader;

    /**
     * @var SalesChannelContextServiceInterface
     */
    private $salesChannelContextService;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var array
     */
    private $_cache = [];

    public function __construct(
        SalesChannelContextServiceInterface $salesChannelContextService,
        RequestStack $requestStack
    ) {
        $this->salesChannelContextService = $salesChannelContextService;
        $this->requestStack = $requestStack;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('languageid', [$this, 'languageid'], ['needs_context' => true]),
            new TwigFunction('currencyiso', [$this, 'currencyiso'], ['needs_context' => true]),
            new TwigFunction('getparam', [$this, 'getparam']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('uuid2bytes', [$this, 'uuid2bytes']),
        ];
    }


    public function uuid2bytes(?string $uuid = ''): string
    {
        if ($uuid) {
            return Uuid::fromHexToBytes($uuid);
        }

        return '';
    }

    public function languageid($twigContext): string
    {
        $context = $this->getSalesChannelContext($twigContext)->getContext();

        return $this->uuid2bytes($context->getLanguageId());
    }

    public function currencyiso($twigContext)
    {
        $salesChannelContext = $this->getSalesChannelContext($twigContext);

        return $salesChannelContext->getCurrency()->getIsoCode();
    }

    public function getparam(string $param)
    {
        $parameters = array_merge(
            $this->requestStack->getCurrentRequest()->request->all(),
            $this->requestStack->getCurrentRequest()->get('_route_params')
        );

        return @$parameters[$param];
    }

    private function getSalesChannelContext($twigContext): SalesChannelContext
    {
        if (
            !array_key_exists('context', $twigContext)
            || !$twigContext['context'] instanceof SalesChannelContext
        ) {
            $masterRequest = $this->requestStack->getMasterRequest();
            $salesChannelId = $masterRequest
                ->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
            $contextToken = $masterRequest
                ->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
            $languageId = $masterRequest
                ->headers->get(PlatformRequest::HEADER_LANGUAGE_ID);
            if ((float)substr(Kernel::SHOPWARE_FALLBACK_VERSION, 0,3) >= 6.4 ) {
                $parameters = new \Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters(
                    $salesChannelId,
                    $contextToken,
                    $languageId
                );
                $twigContext['context'] = $this->salesChannelContextService->get($parameters);
            } else {
                $twigContext['context'] = $this->salesChannelContextService->get(
                    $salesChannelId,
                    $contextToken,
                    $languageId
                );
            }
        }

        return $twigContext['context'];
    }
}
