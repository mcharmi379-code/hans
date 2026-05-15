<?php declare(strict_types=1);

namespace HansAndKniebesTheme\Storefront\Subscriber;

use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LenzGroupColorSubscriber implements EventSubscriberInterface
{
    private const COLOUR_GROUP_NAME = 'Color';
    private const COLOUR_LIMIT = 5;
    private const LENZ_FIELD = 'customFields.lenz_variant_manager_product_group_identifier';

    public function __construct(private readonly EntityRepository $productRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingResultEvent::class => ['onListingResult', -20],
            ProductSearchResultEvent::class  => ['onSearchResult', -20],
        ];
    }

    public function onListingResult(ProductListingResultEvent $event): void
    {
        $this->attachColours($event->getResult()->getEntities()->getElements(), $event->getContext());
    }

    public function onSearchResult(ProductSearchResultEvent $event): void
    {
        $this->attachColours($event->getResult()->getEntities()->getElements(), $event->getContext());
    }

    private function attachColours(array $products, $context): void
    {
        // Collect group identifiers from main products (no parentId = main/standalone product)
        $groupMap = []; // groupIdentifier => [productId, ...]

        foreach ($products as $product) {
            if ($product->getParentId() !== null) {
                continue;
            }

            $groupId = $product->getTranslated()['customFields']['lenz_variant_manager_product_group_identifier'] ?? null;

            if (empty($groupId)) {
                continue;
            }

            $groupMap[$groupId][] = $product->getId();
        }

        if (empty($groupMap)) {
            return;
        }

        // Load all sibling products for all group identifiers in one query
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsAnyFilter(self::LENZ_FIELD, array_keys($groupMap)),
            new EqualsFilter('active', true),
            new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('childCount', 0),
                new EqualsFilter('childCount', null),
            ]),
        ]));
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('cover');
        $criteria->setLimit(500);

        $siblings = $this->productRepository->search($criteria, $context)->getEntities();

        // Build a map: groupIdentifier => list of colour data per sibling product
        $coloursByGroup = []; // groupIdentifier => [ ['productId'=>, 'name'=>, 'hex'=>, 'url'=>], ... ]

        foreach ($siblings->getElements() as $sibling) {
            $siblingGroupId = $sibling->getTranslated()['customFields']['lenz_variant_manager_product_group_identifier'] ?? null;

            if (empty($siblingGroupId) || !isset($groupMap[$siblingGroupId])) {
                continue;
            }

            $colourOption = null;

            if ($sibling->getProperties()) {
                foreach ($sibling->getProperties() as $option) {
                    if ($option->getGroup() && $option->getGroup()->getName() === self::COLOUR_GROUP_NAME) {
                        $colourOption = $option;
                        break;
                    }
                }
            }

            if ($colourOption === null) {
                continue;
            }

            $coloursByGroup[$siblingGroupId][] = [
                'productId' => $sibling->getId(),
                'name'      => $colourOption->getTranslated()['name'] ?? $colourOption->getName(),
                'hex'       => $colourOption->getColorHexCode(),
            ];
        }

        // Attach the colour data to each matching product in the listing
        foreach ($products as $product) {
            if ($product->getParentId() !== null) {
                continue;
            }

            $groupId = $product->getTranslated()['customFields']['lenz_variant_manager_product_group_identifier'] ?? null;

            if (empty($groupId) || empty($coloursByGroup[$groupId])) {
                continue;
            }

            $colours = $coloursByGroup[$groupId];
            $total   = count($colours);
            $limited = array_slice($colours, 0, self::COLOUR_LIMIT);

            $product->addExtension('lenzGroupColours', new ArrayStruct([
                'colours'   => $limited,
                'total'     => $total,
                'remaining' => max(0, $total - self::COLOUR_LIMIT),
            ]));
        }
    }
}
