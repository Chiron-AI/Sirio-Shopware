<?php declare(strict_types=1);

namespace Chiron\Sirio\Subscriber;

use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Chiron\Sirio\Services\SirioProfilingModules;
use Chiron\Sirio\Services\SirioProfilingRenderer;
use Chiron\Sirio\Utility\SessionUtility;

class StorefrontRenderSubscriber implements EventSubscriberInterface
{
    /**
     * @var SirioProfilingModules
     */
    private $modules;

    /**
     * @var SirioProfilingRenderer
     */
    private $sirioProfilingRenderer;

    /**
     * @var SessionInterface
     */
    private $session;

    public function __construct(
        SirioProfilingModules $modules,
        SirioProfilingRenderer $sirioProfilingRenderer,
        SessionInterface $session
    ) {
        $this->modules = $modules;
        $this->sirioProfilingRenderer = $sirioProfilingRenderer;
        $this->session = $session;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => ['onRender', -1],
        ];
    }

    public function onRender(StorefrontRenderEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannel()->getId();
        $isActive = $this->modules->isActive($salesChannelId);
        $route = $event->getRequest()->attributes->get('_route');

        if($route == null){
            return;
        }

        if (!$isActive) {
            return;
        }

        if ($this->session->has(SessionUtility::ATTRIBUTE_NAME)) {
            $sirioProfiling = $this->session->get(SessionUtility::ATTRIBUTE_NAME);
        } else {
            $parameters = $event->getParameters();

            $sirioProfiling = $this->sirioProfilingRenderer->setVariables($route, $parameters)->renderSirioProfiling($route);  
            $sirioProfiling = $sirioProfiling->getSirioProfiling($route);
        }
        
        if (!$event->getRequest()->isXmlHttpRequest()) {
            $event->setParameter(
                'chironSirioConfig',
                [   
                    'sirioUrl' => $this->modules->getSirioUrl($salesChannelId),
                    'isActive' => $isActive
                ]
            );
            
            if (!empty($sirioProfiling)) {
                $event->setParameter(
                    'sirioProfiling',
                    $sirioProfiling
                );
            }
        }
    }
}
