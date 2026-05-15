<?php declare(strict_types=1);

namespace HansAndKniebesTheme\Storefront\Subscriber;

use Shopware\Core\Checkout\Customer\SalesChannel\AbstractCustomerGroupRegistrationSettingsRoute;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class B2bLoginPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly AbstractCustomerGroupRegistrationSettingsRoute $customerGroupRegistrationRoute,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AccountLoginPageLoadedEvent::class => 'onLoginPageLoaded',
        ];
    }

    public function onLoginPageLoaded(AccountLoginPageLoadedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
        $groupId = (string) ($this->systemConfigService->get('HansAndKniebesTheme.config.customerGroupRegistrationId', $salesChannelId) ?? '');

        if ($groupId === '') {
            return;
        }

        $request = $event->getRequest();
        $isB2bRequest = $request->query->get('b2b') === '1'
            || $request->request->get('requestedGroupId') === $groupId;

        // Mark b2b mode on direct /account/login?b2b=1 requests and on validation forwards from the B2B form.
        if ($isB2bRequest) {
            $event->getPage()->addExtension('b2bMode', new ArrayStruct(['active' => true, 'groupId' => $groupId]));
        }

        // Always load group entity so title/intro render on the form
        try {
            $group = $this->customerGroupRegistrationRoute
                ->load($groupId, $event->getSalesChannelContext())
                ->getRegistration();
            $event->getPage()->addExtension('b2bGroup', new ArrayStruct(['group' => $group]));
        } catch (\Throwable $e) {
            // Log for debugging
            $event->getPage()->addExtension('b2bGroupError', new ArrayStruct(['error' => $e->getMessage()]));
        }
    }
}
