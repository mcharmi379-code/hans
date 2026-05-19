<?php declare(strict_types=1);

namespace HansAndKniebesTheme\Storefront\Controller;

use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['storefront']])]
class B2bAccessRequestController extends StorefrontController
{
    public function __construct(
        private readonly AbstractMailService $mailService,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    #[Route(
        path: '/b2b-access-request',
        name: 'frontend.b2b.access.request',
        methods: ['POST'],
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true]
    )]
    public function submit(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $company   = trim((string) $data->get('company', ''));
        $contact   = trim((string) $data->get('contact', ''));
        $email     = trim((string) $data->get('email', ''));
        $vat       = trim((string) $data->get('vat', ''));
        $reference = trim((string) $data->get('reference', ''));

        if ($company === '' || $contact === '' || $email === '') {
            $this->addFlash('danger', $this->trans('b2b.requestAccess.errorMessage'));
            return $this->redirectToRoute('frontend.account.login.page');
        }

        $salesChannelId = $context->getSalesChannelId();
        $adminEmail     = (string) (
            $this->systemConfigService->get('core.basicInformation.email', $salesChannelId)
            ?: 'shop@hanskniebes.de'
        );

        $templateData = [
            'contactFormData' => [
                'salutation'  => '',
                'firstName'   => $contact,
                'lastName'    => '',
                'email'       => $email,
                'phone'       => '',
                'subject'     => $company,
                'comment'     => implode("\n", array_filter([
                    'Company: '   . $company,
                    $vat       ? 'VAT: '       . $vat       : '',
                    $reference ? 'Reference: ' . $reference : '',
                ])),
            ],
        ];

        // 1. Send admin notification email
        $this->sendViaTemplate(
            'HansAndKniebesTheme.config.b2bAccessRequestMailTemplateId',
            'contact_form',
            [$adminEmail => $adminEmail],
            $templateData,
            $salesChannelId,
            $context
        );

        // 2. Send customer confirmation email
        $this->sendViaTemplate(
            'HansAndKniebesTheme.config.b2bAccessRequestCustomerMailTemplateId',
            'hans_kniebes_b2b_access_request_customer',
            [$email => $contact],
            $templateData,
            $salesChannelId,
            $context
        );

        $this->addFlash('success', $this->trans('b2b.requestAccess.successMessage'));
        return $this->redirectToRoute('frontend.account.login.page');
    }

    /**
     * Load a mail template by configured type ID (or fallback technical name) and send it.
     *
     * @param array<string, string> $recipients
     * @param array<string, mixed>  $templateData
     */
    private function sendViaTemplate(
        string $configKey,
        string $fallbackTechnicalName,
        array $recipients,
        array $templateData,
        string $salesChannelId,
        SalesChannelContext $context
    ): void {
        $configuredTypeId = (string) ($this->systemConfigService->get($configKey, $salesChannelId) ?? '');

        $criteria = new Criteria();
        $criteria->addAssociation('mailTemplateType');
        if ($configuredTypeId !== '') {
            $criteria->addFilter(new EqualsFilter('mailTemplateTypeId', $configuredTypeId));
        } else {
            $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', $fallbackTechnicalName));
        }
        $criteria->setLimit(1);

        $mailTemplate = $this->mailTemplateRepository
            ->search($criteria, $context->getContext())
            ->first();

        if ($mailTemplate === null) {
            return;
        }

        $this->mailService->send(
            [
                'recipients'     => $recipients,
                'senderName'     => $mailTemplate->getTranslation('senderName') ?: 'Hans & Kniebes Shop',
                'subject'        => $mailTemplate->getTranslation('subject') ?: 'B2B Access Request',
                'contentHtml'    => $mailTemplate->getTranslation('contentHtml'),
                'contentPlain'   => $mailTemplate->getTranslation('contentPlain'),
                'salesChannelId' => $salesChannelId,
            ],
            $context->getContext(),
            $templateData
        );
    }
}
