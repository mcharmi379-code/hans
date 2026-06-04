<?php declare(strict_types=1);

namespace HansAndKniebesTheme\Storefront\Page;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class categoryNavigationSubscriber implements EventSubscriberInterface
{
    private const B2B_FLIPBOOK_CATEGORY_NAME = 'B2BPDFFlipbook';

    private EntityRepository $categoryRepository;
    private SystemConfigService $systemConfigService;

    public function __construct(
        EntityRepository $categoryRepository,
        SystemConfigService $systemConfigService
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->systemConfigService = $systemConfigService;

    }

    public static function getSubscribedEvents()
    {
        return [
            NavigationPageLoadedEvent::class => 'NavigationPageLoaded',
            HeaderPageletLoadedEvent::class => 'onHeaderPageLoaded',
            StorefrontRenderEvent::class => 'onStorefrontRender',
        ];
    }

    public function NavigationPageLoaded(NavigationPageLoadedEvent $event): void
    {
        $catalogData = $this->getB2bCatalogData($event->getSalesChannelContext());
        $event->getPage()->addExtension('hkB2bCatalog', new ArrayStruct($catalogData));

        $salesChannelId = $event->getSalesChannelContext()->getSalesChannel()->getId();
        $categoryConfig = $this->systemConfigService->get('HansAndKniebesTheme.config.Category', $salesChannelId);
        if ($categoryConfig) {
            $categoryId = $categoryConfig;
            $page = $event->getPage();

            $criteria = new Criteria();
            $criteria->addAssociation('children');
            $criteria->addFilter(new EqualsFilter('id', $categoryId));
            $categories = $this->categoryRepository->search($criteria, $event->getSalesChannelContext()->getContext());
            $mainCategory = $categories->getEntities()->getElements();

            foreach ($mainCategory as $category) {
                $child = $category->children;
                $page->addExtension('headerCategories', $child);
            }
        }
    }

    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $event->setParameter(
            'hkB2bCatalog',
            $this->getB2bCatalogData($event->getSalesChannelContext())
        );
    }
    
    public function onHeaderPageLoaded(HeaderPageletLoadedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannel()->getId();
        $categoryConfig = $this->systemConfigService->get('HansAndKniebesTheme.config.Category', $salesChannelId);
        if($categoryConfig) {
            $categoryId = $categoryConfig;
            $page = $event->getPagelet();
            $criteria = new Criteria();
            $criteria->addAssociation('children');
            $criteria->addFilter(new EqualsFilter('id', $categoryId));
            $categories = $this->categoryRepository->search($criteria, $event->getSalesChannelContext()->getContext());
            $mainCategory = $categories->getEntities()->getElements();
            foreach ($mainCategory as $category) {
                $child = $category->children;
                $page->addExtension('headerCategories', $child);
            }
        }
        
        // Move last category to end
        $this->moveLastCategoryToEndInHeader($event);
    }
    
    private function moveLastCategoryToEndInHeader(HeaderPageletLoadedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannel()->getId();
        $lastCategoryIds = $this->systemConfigService->get('HansAndKniebesTheme.config.LastCategories', $salesChannelId);
        
        if (!$lastCategoryIds || !is_array($lastCategoryIds) || empty($lastCategoryIds)) {
            return;
        }
        
        $pagelet = $event->getPagelet();
        $navigation = $pagelet->getNavigation();
        
        if (!$navigation) {
            return;
        }
        
        $tree = $navigation->getTree();
        
        if (empty($tree)) {
            return;
        }
        
        $this->reorderTreeItems($tree, $lastCategoryIds);
        $navigation->setTree($tree);
    }
    
    private function reorderTreeItems(array &$treeItems, array $lastCategoryIds): void
    {
        $itemsToMove = [];
        
        // Find all TreeItems to move
        foreach ($lastCategoryIds as $categoryId) {
            foreach ($treeItems as $key => $item) {
                if ($item->getCategory()->getId() === $categoryId) {
                    $itemsToMove[] = $item;
                    unset($treeItems[$key]);
                    break;
                }
            }
        }
        
        // Add them to the end in order
        foreach ($itemsToMove as $item) {
            $treeItems[] = $item;
        }
    }

    private function getB2bCatalogData(SalesChannelContext $salesChannelContext): array
    {
        $customer = $salesChannelContext->getCustomer();
        $currentCustomerGroupId = $salesChannelContext->getCurrentCustomerGroup()->getId();
        $defaultCustomerGroupId = $salesChannelContext->getSalesChannel()->getCustomerGroupId();
        $categoryId = $this->getB2bFlipbookCategoryId($salesChannelContext->getContext());

        return [
            'categoryId' => $categoryId,
            'isEligible' => $customer !== null
                && !$customer->getGuest()
                && $currentCustomerGroupId !== $defaultCustomerGroupId
                && $categoryId !== null,
        ];
    }

    private function getB2bFlipbookCategoryId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translations.name', self::B2B_FLIPBOOK_CATEGORY_NAME));
        $criteria->setLimit(1);

        return $this->categoryRepository->searchIds($criteria, $context)->firstId();
    }
}
