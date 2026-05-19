<?php declare(strict_types=1);

namespace HansAndKniebesTheme\Storefront\Page;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class categoryNavigationSubscriber implements EventSubscriberInterface
{
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
        ];
    }

    public function NavigationPageLoaded(NavigationPageLoadedEvent $event): void
    {
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
}
