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
            $this->addFlash('danger', 'Please fill in all required fields.');
            return $this->redirectToRoute('frontend.account.login.page');
        }

        $salesChannelId = $context->getSalesChannelId();
        $recipient = (string) (
            $this->systemConfigService->get('core.basicInformation.email', $salesChannelId)
            ?: 'shop@hanskniebes.de'
        );

        // Use configured template ID, or fall back to contact_form by technical name
        $configuredTemplateId = (string) ($this->systemConfigService->get('HansAndKniebesTheme.config.b2bAccessRequestMailTemplateId', $salesChannelId) ?? '');

        $criteria = new Criteria();
        $criteria->addAssociation('mailTemplateType');
        if ($configuredTemplateId !== '') {
            // Config stores mail_template_type ID — find template by type
            $criteria->addFilter(new EqualsFilter('mailTemplateTypeId', $configuredTemplateId));
        } else {
            $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', 'contact_form'));
        }
        $criteria->setLimit(1);

        $mailTemplate = $this->mailTemplateRepository
            ->search($criteria, $context->getContext())
            ->first();

        if ($mailTemplate === null) {
            // Fallback: send plain mail if template not found
            $this->sendFallback($recipient, $company, $contact, $email, $vat, $reference, $context);
            $this->addFlash('success', 'Your request has been sent. We will contact you shortly.');
            return $this->redirectToRoute('frontend.account.login.page');
        }

        // Template data — available as {{ contactFormData.* }} in the mail template
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

        $this->mailService->send(
            [
                'recipients'   => [$recipient => $recipient],
                'senderName'   => $mailTemplate->getTranslation('senderName') ?: 'Hans & Kniebes Shop',
                'subject'      => $mailTemplate->getTranslation('subject') ?: 'B2B Access Request – ' . $company,
                'contentHtml'  => $mailTemplate->getTranslation('contentHtml'),
                'contentPlain' => $mailTemplate->getTranslation('contentPlain'),
                'salesChannelId' => $salesChannelId,
            ],
            $context->getContext(),
            $templateData
        );

        $this->addFlash('success', 'Your request has been sent. We will contact you shortly.');
        return $this->redirectToRoute('frontend.account.login.page');
    }

    private function sendFallback(
        string $recipient,
        string $company,
        string $contact,
        string $email,
        string $vat,
        string $reference,
        SalesChannelContext $context
    ): void {
        $html = sprintf(
            '<h2>New B2B Access Request</h2>
            <p><strong>Company:</strong> %s</p>
            <p><strong>Contact:</strong> %s</p>
            <p><strong>Email:</strong> %s</p>
            <p><strong>VAT:</strong> %s</p>
            <p><strong>Reference:</strong> %s</p>',
            htmlspecialchars($company), htmlspecialchars($contact), htmlspecialchars($email),
            htmlspecialchars($vat) ?: '—', htmlspecialchars($reference) ?: '—'
        );

        $this->mailService->send(
            [
                'recipients'   => [$recipient => $recipient],
                'senderName'   => 'Hans & Kniebes Shop',
                'subject'      => 'B2B Access Request – ' . $company,
                'contentHtml'  => $html,
                'contentPlain' => strip_tags($html),
                'salesChannelId' => $context->getSalesChannelId(),
            ],
            $context->getContext()
        );
    }
}
