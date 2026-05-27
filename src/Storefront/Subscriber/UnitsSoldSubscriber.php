<?php declare(strict_types=1);

namespace HansAndKniebesTheme\Storefront\Subscriber;

use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UnitsSoldSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly EntityRepository $orderLineItemRepository) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
            ProductListingResultEvent::class => 'onProductListingResult',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $product = $event->getPage()->getProduct();
        $counts = $this->fetchUnitsSoldLast30Days([$product->getId()], $event->getSalesChannelContext()->getContext());
        $sold = $counts[$product->getId()] ?? 0;
        $product->addExtension('unitsSold', new ArrayStruct(['count' => $sold, 'debug' => ['source' => 'order_line_item_30d']]));
    }

    public function onProductListingResult(ProductListingResultEvent $event): void
    {
        $products = $event->getResult()->getEntities();
        $ids = array_values($products->getIds());
        if (empty($ids)) {
            return;
        }

        $counts = $this->fetchUnitsSoldLast30Days($ids, $event->getContext());
        foreach ($products as $product) {
            $sold = $counts[$product->getId()] ?? 0;
            $product->addExtension('unitsSold', new ArrayStruct(['count' => $sold, 'debug' => ['source' => 'order_line_item_30d']]));
        }
    }

    /**
     * Returns units sold per product within the last 30 days.
     *
     * @return array<string, int> productId => quantity
     */
    private function fetchUnitsSoldLast30Days(array $productIds, Context $context): array
    {
        $since = (new \DateTimeImmutable('-30 days'))->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $criteria = new Criteria();
        $criteria->addAssociation('order.stateMachineState');
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsAnyFilter('productId', $productIds),
            new EqualsAnyFilter('referencedId', $productIds),
        ]));
        $criteria->addFilter(new EqualsFilter('type', 'product'));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('order.stateMachineState.technicalName', 'cancelled'),
        ]));
        $criteria->addFilter(new RangeFilter('order.orderDateTime', [RangeFilter::GTE => $since]));

        $result = $this->orderLineItemRepository->search($criteria, $context);

        $counts = [];
        foreach ($result->getEntities() as $lineItem) {
            $pid = $lineItem->getProductId() ?? $lineItem->getReferencedId();
            if ($pid === null) {
                continue;
            }
            $counts[$pid] = ($counts[$pid] ?? 0) + $lineItem->getQuantity();
        }

        return $counts;
    }
}
