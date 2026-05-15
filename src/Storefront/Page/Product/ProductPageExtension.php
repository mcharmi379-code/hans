<?php declare(strict_types=1);

namespace HansAndKniebesTheme\Storefront\Page\Product;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductPageExtension implements EventSubscriberInterface
{
    private EntityRepository $orderRepository;

    public function __construct(EntityRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded'
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $canWriteReview = false;
        $customer = $event->getSalesChannelContext()->getCustomer();
        if ($customer && !$customer->getGuest()) {
            $canWriteReview = $this->hasCustomerPurchasedProduct(
                $customer->getId(),
                $event->getPage()->getProduct()->getId(),
                $event->getContext()
            );
        }
        $event->getPage()->addExtension('canWriteReview', new ArrayStruct(['value' => $canWriteReview]));
    }

    private function hasCustomerPurchasedProduct(string $customerId, string $productId, $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('orderCustomer.customer.id', $customerId),
                new EqualsFilter('lineItems.productId', $productId),
//                new EqualsFilter('stateMachineState.technicalName', 'completed')
            ])
        );
        $criteria->setLimit(1);

        return $this->orderRepository->search($criteria, $context)->getTotal() > 0;
    }
}