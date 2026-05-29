<?php declare(strict_types=1);

namespace HansAndKniebesTheme\Storefront\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Event\PasswordRecoveryUrlEvent;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class B2bCustomerPasswordRecoverySubscriber implements EventSubscriberInterface
{
    private const ACTIVATION_TEMPLATE_TECHNICAL_NAME = 'hans_kniebes_b2b_account_activated';

    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     * @param EntityRepository<CustomerRecoveryCollection> $customerRecoveryRepository
     * @param EntityRepository<MailTemplateCollection> $mailTemplateRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EntityRepository $customerRecoveryRepository,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly SalesChannelContextRestorer $salesChannelContextRestorer,
        private readonly SystemConfigService $systemConfigService,
        private readonly AbstractMailService $mailService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
        ];
    }

    public function onCustomerWritten(EntityWrittenEvent $event): void
    {
        if (!$event->getContext()->getSource() instanceof AdminApiSource) {
            return;
        }

        foreach ($event->getPayloads() as $payload) {
            if (empty($payload['createdAt']) || !\is_string($payload['id'])) {
                continue;
            }

            try {
                $this->sendActivationMailForCustomer($payload['id'], $event->getContext());
            } catch (\Throwable $exception) {
                $this->logger->error('Could not send B2B account activation mail after admin customer creation.', [
                    'customerId' => $payload['id'],
                    'exception' => $exception,
                ]);
            }
        }
    }

    private function sendActivationMailForCustomer(string $customerId, Context $context): void
    {
    
        $salesChannelContext = $this->salesChannelContextRestorer->restoreByCustomer($customerId, $context);

        $customer = $this->customerRepository->search(
            (new Criteria([$customerId]))
                ->addAssociation('salutation'),
            $salesChannelContext->getContext()
        )->getEntities()->first();

        if ($customer === null || $this->isSalesChannelDefaultCustomerGroup($salesChannelContext, $customer)) {
            return;
        }

        $storefrontUrl = $this->getStorefrontUrl($salesChannelContext);
        if ($storefrontUrl === null) {
            return;
        }

        $customerRecovery = $this->createCustomerRecovery($customer->getId(), $salesChannelContext->getContext());
        $customerRecovery->setCustomer($customer);

        $resetUrl = $this->getRecoverUrl($salesChannelContext, $customerRecovery, $storefrontUrl);

        $this->sendActivationMail($salesChannelContext, $customer, $resetUrl);
    }

    private function createCustomerRecovery(string $customerId, Context $context): CustomerRecoveryEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('customerId', $customerId));

        $existingRecovery = $this->customerRecoveryRepository->search($criteria, $context)->getEntities()->first();
        if ($existingRecovery !== null) {
            $this->customerRecoveryRepository->delete([['id' => $existingRecovery->getId()]], $context);
        }

        $this->customerRecoveryRepository->create([[
            'customerId' => $customerId,
            'hash' => Random::getAlphanumericString(32),
        ]], $context);

        $customerRecovery = $this->customerRecoveryRepository->search($criteria, $context)->getEntities()->first();
        if (!$customerRecovery instanceof CustomerRecoveryEntity) {
            throw new \RuntimeException(sprintf('Could not create password recovery for customer "%s".', $customerId));
        }

        return $customerRecovery;
    }

    private function getRecoverUrl(SalesChannelContext $salesChannelContext, CustomerRecoveryEntity $customerRecovery, string $storefrontUrl): string
    {
        $hash = $customerRecovery->getHash();
        $urlTemplate = $this->systemConfigService->get(
            'core.loginRegistration.pwdRecoverUrl',
            $salesChannelContext->getSalesChannelId()
        );

        if (!\is_string($urlTemplate)) {
            $urlTemplate = '/account/recover/password?hash=%%RECOVERHASH%%';
        }

        $urlEvent = new PasswordRecoveryUrlEvent($salesChannelContext, $urlTemplate, $hash, $storefrontUrl, $customerRecovery);
        $this->eventDispatcher->dispatch($urlEvent);

        return rtrim($storefrontUrl, '/') . str_replace('%%RECOVERHASH%%', $hash, $urlEvent->getRecoveryUrl());
    }

    private function getStorefrontUrl(SalesChannelContext $salesChannelContext): ?string
    {
        $domains = $salesChannelContext->getSalesChannel()->getDomains();
        if ($domains === null) {
            return null;
        }

        $domain = $domains->first();
        if (!$domain instanceof SalesChannelDomainEntity) {
            return null;
        }

        return rtrim($domain->getUrl(), '/');
    }

    private function sendActivationMail(SalesChannelContext $salesChannelContext, CustomerEntity $customer, string $resetUrl): void
    {
        $context = $salesChannelContext->getContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $template = $this->getMailTemplate($context);

        if ($template === null) {
            return;
        }

        $recipientName = trim($customer->getFirstName() . ' ' . $customer->getLastName());
        $mailData = [
            'recipients' => [$customer->getEmail() => $recipientName !== '' ? $recipientName : $customer->getEmail()],
            'senderName' => $template->getTranslation('senderName'),
            'subject' => $template->getTranslation('subject'),
            'contentHtml' => $template->getTranslation('contentHtml'),
            'contentPlain' => $template->getTranslation('contentPlain'),
            'salesChannelId' => $salesChannelId,
        ];

        $this->mailService->send($mailData, $context, [
            'customer' => $customer,
            'resetUrl' => $resetUrl,
            'shopName' => $salesChannelContext->getSalesChannel()->getTranslation('name'),
            'salesChannel' => $salesChannelContext->getSalesChannel(),
        ]);
    }

    private function getMailTemplate(Context $context): ?MailTemplateEntity
    {
        $criteria = (new Criteria())
            ->addAssociation('mailTemplateType')
            ->addFilter(new EqualsFilter('mailTemplateType.technicalName', self::ACTIVATION_TEMPLATE_TECHNICAL_NAME))
            ->setLimit(1);

        $template = $this->mailTemplateRepository->search($criteria, $context)->first();

        return $template instanceof MailTemplateEntity ? $template : null;
    }

    private function isSalesChannelDefaultCustomerGroup(SalesChannelContext $salesChannelContext, CustomerEntity $customer): bool
    {
        return $customer->getGroupId() === $salesChannelContext->getSalesChannel()->getCustomerGroupId();
    }
}
