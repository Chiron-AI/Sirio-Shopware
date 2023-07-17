<?php declare(strict_types=1);

namespace Chiron\Sirio\Subscriber;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Chiron\Sirio\Services\SirioProfilingModulesInterface;
use Chiron\Sirio\Services\SirioProfilingRendererInterface;
use Chiron\Sirio\Utility\SessionUtility;

class KernelEventsSubscriber implements EventSubscriberInterface
{
    /**
     * @var SirioProfilingModulesInterface
     */
    private $modules;

    /**
     * @var SirioProfilingRendererInterface
     */
    private $sirioProfilingRenderer;

    /**
     * @var SessionInterface
     */
    private $session;

    public function __construct(
        SirioProfilingModulesInterface $modules,
        SirioProfilingRendererInterface $sirioProfilingRenderer,
        SessionInterface $session
    ) {
        $this->modules = $modules;
        $this->sirioProfilingRenderer = $sirioProfilingRenderer;
        $this->session = $session;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['getSirioProfilingForXmlHttpRequest', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE_POST],
            ],
            KernelEvents::RESPONSE => [
                ['prependSirioProfilingToResponse', -1],
            ],
        ];
    }

    public function getSirioProfilingForXmlHttpRequest(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->isStorefrontRequest($request)) {
            return;
        }

        $route = $request->attributes->get('_route');

        if($route == null){
            return;
        }

        $salesChannelId = $request->get('sw-sales-channel-id');
        
        $isActive = $this->modules->isActive($salesChannelId);
        
        if (!$isActive) {
            return;
        }

        $this->sirioProfilingRenderer->setVariables($route, []);//->renderSirioProfiling($route);
    }

    public function prependSirioProfilingToResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();
        if (!$this->isStorefrontRequest($request)) {
            return;
        }


        $route = $request->attributes->get('_route');
        $salesChannelId = $request->get('sw-sales-channel-id');
        $sirioProfiling = $this->sirioProfilingRenderer->getSirioProfiling($route,$salesChannelId);
        
        if (!empty($sirioProfiling) && $response->isRedirect()) {
            $this->session->set(SessionUtility::ATTRIBUTE_NAME, $sirioProfiling);
            return;
        }

        if (!$request->isXmlHttpRequest()) {
            return;
        }

        $storedSirioProfiling = $this->session->get(SessionUtility::ATTRIBUTE_NAME);
        $this->session->remove(SessionUtility::ATTRIBUTE_NAME);
        if ($storedSirioProfiling && in_array($route, $this->modules->getResponseRoutes(), true)) {
            $sirioProfiling = $storedSirioProfiling;
        }

        if (empty($sirioProfiling)) {
            return;
        }

        $sirioCustomObjectScriptTag = sprintf(
            '<script id="chiron-sirio-profiling">%s</script>',
            $sirioProfiling
        );

        $content = $sirioCustomObjectScriptTag . PHP_EOL . $response->getContent();
        $response->setContent($content);

        $event->setResponse($response);
    }

    private function isStorefrontRequest(Request $request)
    {
        if ($request->attributes->has('_routeScope')
            && $request->attributes->get('_routeScope') instanceof RouteScope
            && $request->attributes->get('_routeScope')->hasScope('storefront')
        ) {
            return true;
        }

        return false;
    }
}
