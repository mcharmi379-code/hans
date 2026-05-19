<?php declare(strict_types=1);

namespace HansAndKniebesTheme\Storefront\Page\Checkout;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutConfirmPageExtension implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $newsletterRecipientRepository
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmPageLoaded'
        ];
    }

    public function onCheckoutConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $customer = $event->getSalesChannelContext()->getCustomer();
        if (!$customer) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $customer->getEmail()));
        
        $recipient = $this->newsletterRecipientRepository->search($criteria, $event->getContext())->first();
        
        $isSubscribed = false;
        if ($recipient) {
            $status = $recipient->getStatus();
            if ($status === 'direct' || $status === 'optIn' || $status === 'notSet') {
                $isSubscribed = true;
            }
        }
        
        $event->getPage()->addExtension('newsletterSubscription', new ArrayStruct(['isSubscribed' => $isSubscribed]));
    }
}
