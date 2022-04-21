<?php

declare(strict_types=1);

namespace Chiron\Sirio\Subscriber\Cart;

use Shopware\Core\Checkout\Cart\Event\AfterLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\AfterLineItemQuantityChangedEvent;
use Shopware\Core\Checkout\Cart\Event\AfterLineItemRemovedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Chiron\Sirio\Services\SirioProfilingModules;
use Chiron\Sirio\Services\SirioProfilingRenderer;
use Chiron\Sirio\Utility\SessionUtility;

class DefaultSubscriber implements EventSubscriberInterface
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
    public static function getSubscribedEvents()
    {
        return [
            AfterLineItemAddedEvent::class => 'onAfterLineItemAdded',
            AfterLineItemQuantityChangedEvent::class => 'onAfterLineItemQuantityChanged',
            AfterLineItemRemovedEvent::class => 'onAfterLineItemRemoved',
            CustomerLoginEvent::class => 'onCustomerLogin'
        ];
    }

    protected function callSirio($event, $eventName)
    {   
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannel()->getId();
        $isActive = $this->modules->isActive($salesChannelId);
        
        if (!$isActive) {
            return;
        }

        try {
            $this->sirioProfilingRenderer->setSirioCart($eventName);
            if (json_last_error() > 0) {
                throw new \Exception(json_last_error_msg(), 1620985321);
            }
        } catch (\Exception $e) {
            print_r($e->getMessage());
        }
        
    }

    public function onCustomerLogin(CustomerLoginEvent $event)
    {   
        $this->callSirio($event, "CustomerLoginEvent");        
    }

    public function onAfterLineItemAdded(AfterLineItemAddedEvent $event)
    {   
        $this->callSirio($event, "AfterLineItemAddedEvent");        
    }

    public function onAfterLineItemQuantityChanged(AfterLineItemQuantityChangedEvent $event)
    {   
        $this->callSirio($event, "AfterLineItemQuantityChangedEvent");        
    }

    public function onAfterLineItemRemoved(AfterLineItemRemovedEvent $event)
    {   
        $this->callSirio($event, "AfterLineItemRemovedEvent");        
    }
}
