<?php declare(strict_types=1);

namespace HansAndKniebesTheme\Storefront\Subscriber;

use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UnitsSoldSubscriber implements EventSubscriberInterface
{
    public function __construct() {}

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
        $sold = (int) ($product->getSales() ?? 0);
        $product->addExtension('unitsSold', new ArrayStruct(['count' => $sold, 'debug' => ['source' => 'product.sales']]));
    }

    public function onProductListingResult(ProductListingResultEvent $event): void
    {
        $products = $event->getResult()->getEntities();
        foreach ($products as $product) {
            $sold = (int) ($product->getSales() ?? 0);
            $product->addExtension('unitsSold', new ArrayStruct(['count' => $sold, 'debug' => ['source' => 'product.sales']]));
        }
    }
}
