<?php declare(strict_types=1);

namespace HansAndKniebesTheme\Storefront\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractResetPasswordRoute;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

class B2bResetPasswordRouteDecorator extends AbstractResetPasswordRoute
{
    private const PASSWORD_CREATED_TEMPLATE_TECHNICAL_NAME = 'hans_kniebes_b2b_password_created';

    /**
     * @param EntityRepository<CustomerRecoveryCollection> $customerRecoveryRepository
     * @param EntityRepository<MailTemplateCollection> $mailTemplateRepository
     */
    public function __construct(
        private readonly AbstractResetPasswordRoute $decorated,
        private readonly EntityRepository $customerRecoveryRepository,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly AbstractMailService $mailService,
    ) {
    }

    public function getDecorated(): AbstractResetPasswordRoute
    {
        return $this->decorated;
    }

    public function resetPassword(RequestDataBag $data, SalesChannelContext $context): SuccessResponse
    {
        $customer = $this->getCustomerByRecoveryHash((string) $data->get('hash'), $context);

        $response = $this->decorated->resetPassword($data, $context);

        if ($customer !== null && $customer->getGroupId() !== $context->getSalesChannel()->getCustomerGroupId()) {
            $this->sendPasswordCreatedMail($customer, $context);
        }

        return $response;
    }

    private function getCustomerByRecoveryHash(string $hash, SalesChannelContext $context): ?CustomerEntity
    {
        if ($hash === '') {
            return null;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('hash', $hash))
            ->addAssociation('customer.salutation')
            ->setLimit(1);

        $recovery = $this->customerRecoveryRepository->search($criteria, $context->getContext())->first();
        if ($recovery === null) {
            return null;
        }

        $customer = $recovery->getCustomer();

        return $customer instanceof CustomerEntity ? $customer : null;
    }

    private function sendPasswordCreatedMail(CustomerEntity $customer, SalesChannelContext $context): void
    {
        $template = $this->getMailTemplate($context);
        if ($template === null) {
            return;
        }

        $recipientName = trim($customer->getFirstName() . ' ' . $customer->getLastName());

        $this->mailService->send([
            'recipients' => [$customer->getEmail() => $recipientName !== '' ? $recipientName : $customer->getEmail()],
            'senderName' => $template->getTranslation('senderName'),
            'subject' => $template->getTranslation('subject'),
            'contentHtml' => $template->getTranslation('contentHtml'),
            'contentPlain' => $template->getTranslation('contentPlain'),
            'salesChannelId' => $context->getSalesChannelId(),
        ], $context->getContext(), [
            'customer' => $customer,
            'shopName' => $context->getSalesChannel()->getTranslation('name'),
            'salesChannel' => $context->getSalesChannel(),
        ]);
    }

    private function getMailTemplate(SalesChannelContext $context): ?MailTemplateEntity
    {
        $criteria = (new Criteria())
            ->addAssociation('mailTemplateType')
            ->addFilter(new EqualsFilter('mailTemplateType.technicalName', self::PASSWORD_CREATED_TEMPLATE_TECHNICAL_NAME))
            ->setLimit(1);

        $template = $this->mailTemplateRepository->search($criteria, $context->getContext())->first();

        return $template instanceof MailTemplateEntity ? $template : null;
    }
}
