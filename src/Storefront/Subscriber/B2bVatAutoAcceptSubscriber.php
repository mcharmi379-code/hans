<?php declare(strict_types=1);

namespace HansAndKniebesTheme\Storefront\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class B2bVatAutoAcceptSubscriber implements EventSubscriberInterface
{
    /** @param EntityRepository<CustomerCollection> $customerRepository */
    public function __construct(
        private readonly EntityRepository $customerRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run after AcrisTaxCS VAT validation (priority -10)
            CustomerRegisterEvent::class => ['onCustomerRegister', -10],
            AccountOverviewPageLoadedEvent::class => 'onAccountOverviewPageLoaded',
        ];
    }

    public function onCustomerRegister(CustomerRegisterEvent $event): void
    {
        $this->autoAcceptCustomerGroup(
            $event->getCustomer()->getId(),
            $event->getSalesChannelContext()->getContext()
        );
    }

    public function onAccountOverviewPageLoaded(AccountOverviewPageLoadedEvent $event): void
    {
        $customer = $event->getSalesChannelContext()->getCustomer();

        if (!$customer instanceof CustomerEntity) {
            return;
        }

        $acceptedGroupId = $this->autoAcceptCustomerGroup(
            $customer->getId(),
            $event->getSalesChannelContext()->getContext()
        );

        if ($acceptedGroupId === null) {
            return;
        }

        $customer->setGroupId($acceptedGroupId);
        $customer->setRequestedGroupId(null);
    }

    private function autoAcceptCustomerGroup(string $customerId, Context $context): ?string
    {
        // Reload fresh from DB so we get the latest customFields set by AcrisTaxCS
        $criteria = (new Criteria([$customerId]))
            ->addAssociation('defaultBillingAddress');

        $customer = $this->customerRepository
            ->search($criteria, $context)
            ->getEntities()
            ->first();

        if ($customer === null) {
            return null;
        }

        // Only process customer group registration requests
        $requestedGroupId = $customer->getRequestedGroupId();
        if ($requestedGroupId === null) {
            return null;
        }

        // Must have a non-empty VAT ID
        $vatIds = array_filter($customer->getVatIds() ?? [], fn($v) => trim((string) $v) !== '');
        if (empty($vatIds)) {
            // No VAT provided - leave pending for admin to accept manually
            return null;
        }

        if (!$this->hasValidAcrisVatStatus($customer)) {
            // VAT not confirmed valid - leave pending for admin
            return null;
        }

        // VAT is confirmed valid by AcrisTaxCS - auto-accept the group request
        $this->customerRepository->update([[
            'id'               => $customerId,
            'groupId'          => $requestedGroupId,
            'requestedGroupId' => null,
        ]], $context);
        
        return $requestedGroupId;
    }

    private function hasValidAcrisVatStatus(CustomerEntity $customer): bool
    {
        $customerCustomFields = $customer->getCustomFields() ?? [];
        if (($customerCustomFields['acris_tax_vat_id_status'] ?? null) === 'valid') {
            return true;
        }

        $billingAddress = $customer->getDefaultBillingAddress();
        if ($billingAddress === null) {
            return false;
        }

        $billingCustomFields = $billingAddress->getCustomFields() ?? [];

        return ($billingCustomFields['acris_tax_vat_id_status'] ?? null) === 'valid';
    }
}
