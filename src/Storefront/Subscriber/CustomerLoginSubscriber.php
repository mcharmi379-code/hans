<?php declare(strict_types=1);

namespace HansAndKniebesTheme\Storefront\Subscriber;

use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomerLoginSubscriber implements EventSubscriberInterface
{
    private EntityRepository $customerRepository;
    private EntityRepository $orderRepository;
    private EntityRepository $tagRepository;

    public function __construct(
        EntityRepository $customerRepository,
        EntityRepository $orderRepository,
        EntityRepository $tagRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->orderRepository = $orderRepository;
        $this->tagRepository = $tagRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLoginEvent::class => 'onCustomerLogin',
            CheckoutFinishPageLoadedEvent::class => 'onCheckoutFinish',
        ];
    }

    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        $customer = $event->getCustomer();
        $context = $event->getContext();

        // Check if customer has any orders
        $orderCriteria = new Criteria();
        $orderCriteria->addFilter(new EqualsFilter('orderCustomer.customer.id', $customer->getId()));
        $orderCriteria->setLimit(1);

        $orders = $this->orderRepository->search($orderCriteria, $context);
        
        if ($orders->getTotal() > 0) {
            $this->checkGotGiftTag($customer->getId(), $context);
        }
    }

    public function onCheckoutFinish(CheckoutFinishPageLoadedEvent $event): void
    {
        $order = $event->getPage()->getOrder();
        $customer = $event->getSalesChannelContext()->getCustomer();
        
        if ($customer && $order) {
            if ($this->hasFreeProduct($order)) {
                if (!$this->hasTag($customer->getId(), 'GotGift', $event->getContext())) {
                    $this->addGotGiftTag($customer->getId(), $event->getContext());
                }
            }
        }
    }

    private function checkGotGiftTag(string $customerId, Context $context): void
    {
        $orderCriteria = new Criteria();
        $orderCriteria->addFilter(new EqualsFilter('orderCustomer.customer.id', $customerId));
        $orderCriteria->addAssociation('lineItems');
        $orderCriteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $orderCriteria->setLimit(1);

        $lastOrder = $this->orderRepository->search($orderCriteria, $context)->first();
        
        if ($lastOrder && $this->hasFreeProduct($lastOrder)) {
            if (!$this->hasTag($customerId, 'GotGift', $context)) {
                $this->addGotGiftTag($customerId, $context);
            }
        }
    }

    private function hasFreeProduct($order): bool
    {
        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->getUnitPrice() == 0 && $lineItem->getType() === 'product') {
                return true;
            }
        }
        return false;
    }

    private function addGotGiftTag(string $customerId, Context $context): void
    {
        $tagCriteria = new Criteria();
        $tagCriteria->addFilter(new EqualsFilter('name', 'GotGift'));
        $tag = $this->tagRepository->search($tagCriteria, $context)->first();

        if (!$tag) {
            $this->tagRepository->create([
                ['name' => 'GotGift']
            ], $context);
            
            $tag = $this->tagRepository->search($tagCriteria, $context)->first();
        }

        $this->customerRepository->update([
            [
                'id' => $customerId,
                'tags' => [
                    ['id' => $tag->getId()]
                ]
            ]
        ], $context);
    }

    private function hasTag(string $customerId, string $tagName, Context $context): bool
    {
        $customerCriteria = new Criteria([$customerId]);
        $customerCriteria->addAssociation('tags');
        $customer = $this->customerRepository->search($customerCriteria, $context)->first();
        
        if ($customer) {
            foreach ($customer->getTags() as $tag) {
                if ($tag->getName() === $tagName) {
                    return true;
                }
            }
        }
        return false;
    }
}