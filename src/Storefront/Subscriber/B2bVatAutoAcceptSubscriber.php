<?php declare(strict_types=1);

namespace HansAndKniebesTheme\Storefront\Subscriber;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationAccepted;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class B2bVatAutoAcceptSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<CustomerCollection>      $customerRepository
     * @param EntityRepository<CustomerGroupCollection> $customerGroupRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EntityRepository $customerGroupRepository,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SalesChannelContextRestorer $salesChannelContextRestorer,
        private readonly AbstractMailService $mailService,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerRegisterEvent::class => ['onCustomerRegister', -10],
            AccountOverviewPageLoadedEvent::class => 'onAccountOverviewPageLoaded',
        ];
    }

    public function onCustomerRegister(CustomerRegisterEvent $event): void
    {
        $this->processRegistration(
            $event->getCustomer()->getId(),
            $event->getSalesChannelContext()->getContext(),
            $event->getSalesChannelContext()->getSalesChannelId()
        );
    }

    public function onAccountOverviewPageLoaded(AccountOverviewPageLoadedEvent $event): void
    {
        $customer = $event->getSalesChannelContext()->getCustomer();
        if (!$customer instanceof CustomerEntity) {
            return;
        }

        $this->processRegistration(
            $customer->getId(),
            $event->getSalesChannelContext()->getContext(),
            $event->getSalesChannelContext()->getSalesChannelId()
        );
    }

    private function processRegistration(string $customerId, Context $context, string $salesChannelId): void
    {
        $criteria = (new Criteria([$customerId]))->addAssociation('defaultBillingAddress');
        $customer = $this->customerRepository->search($criteria, $context)->getEntities()->first();

        if ($customer === null || $customer->getRequestedGroupId() === null) {
            return;
        }

        $requestedGroupId = $customer->getRequestedGroupId();
        $vatIds = array_filter($customer->getVatIds() ?? [], fn($v) => trim((string) $v) !== '');

        if (!empty($vatIds) && $this->hasValidAcrisVatStatus($customer)) {
            // VAT valid — auto-accept and dispatch accepted event (triggers customer.group.registration.accepted mail)
            $this->customerRepository->update([[
                'id'               => $customerId,
                'groupId'          => $requestedGroupId,
                'requestedGroupId' => null,
            ]], $context);

            $salesChannelContext = $this->salesChannelContextRestorer->restoreByCustomer($customerId, $context);
            $updatedCustomer     = $salesChannelContext->getCustomer();

            if ($updatedCustomer === null) {
                return;
            }

            $customerGroup = $this->customerGroupRepository
                ->search(new Criteria([$requestedGroupId]), $context)
                ->getEntities()->first();

            if ($customerGroup === null) {
                return;
            }

            // Dispatch standard Shopware event — triggers customer.group.registration.accepted flow/mail
            $this->eventDispatcher->dispatch(
                new CustomerGroupRegistrationAccepted($updatedCustomer, $customerGroup, $context)
            );
        } else {
            // No VAT or invalid VAT — send pending confirmation email to customer
            $this->sendPendingEmail($customer, $salesChannelId, $context);
        }
    }

    private function sendPendingEmail(CustomerEntity $customer, string $salesChannelId, Context $context): void
    {
        $configuredTypeId = (string) ($this->systemConfigService->get(
            'HansAndKniebesTheme.config.b2bPendingRegistrationMailTemplateId',
            $salesChannelId
        ) ?? '');

        $criteria = new Criteria();
        $criteria->addAssociation('mailTemplateType');
        if ($configuredTypeId !== '') {
            $criteria->addFilter(new EqualsFilter('mailTemplateTypeId', $configuredTypeId));
        } else {
            $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', 'hans_kniebes_b2b_pending_registration'));
        }
        $criteria->setLimit(1);

        $mailTemplate = $this->mailTemplateRepository->search($criteria, $context)->first();
        if ($mailTemplate === null) {
            return;
        }

        $customerEmail = $customer->getEmail();
        $customerName  = trim($customer->getFirstName() . ' ' . $customer->getLastName());

        $this->mailService->send(
            [
                'recipients'     => [$customerEmail => $customerName],
                'senderName'     => $mailTemplate->getTranslation('senderName') ?: 'Hans & Kniebes GmbH',
                'subject'        => $mailTemplate->getTranslation('subject') ?: 'Your Business Registration Request Has Been Received',
                'contentHtml'    => $mailTemplate->getTranslation('contentHtml'),
                'contentPlain'   => $mailTemplate->getTranslation('contentPlain'),
                'salesChannelId' => $salesChannelId,
            ],
            $context,
            ['customer' => $customer]
        );
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

        return ($billingAddress->getCustomFields()['acris_tax_vat_id_status'] ?? null) === 'valid';
    }
}
