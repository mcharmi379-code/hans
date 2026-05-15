<?php declare(strict_types=1);

namespace HansAndKniebesTheme;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\ThemeInterface;

class HansAndKniebesTheme extends Plugin implements ThemeInterface
{
    public const B2B_MAIL_TEMPLATE_TECHNICAL_NAME = 'hans_kniebes_b2b_access_request';

    public function postInstall(InstallContext $installContext): void
    {
        $this->createB2bMailTemplate($installContext->getContext());
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
        $this->createB2bMailTemplate($updateContext->getContext());
    }

    private function createB2bMailTemplate(Context $context): void
    {
        /** @var EntityRepository $mailTemplateTypeRepo */
        $mailTemplateTypeRepo = $this->container->get('mail_template_type.repository');
        /** @var EntityRepository $mailTemplateRepo */
        $mailTemplateRepo = $this->container->get('mail_template.repository');
        /** @var SystemConfigService $systemConfig */
        $systemConfig = $this->container->get(SystemConfigService::class);

        // Check if our custom template type already exists
        $existingType = $mailTemplateTypeRepo->search(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', self::B2B_MAIL_TEMPLATE_TECHNICAL_NAME))->setLimit(1),
            $context
        )->first();

        if ($existingType !== null) {
            // Already exists — just ensure config points to the type ID
            $this->setDefaultConfig($systemConfig, $existingType->getId());
            return;
        }

        $typeId     = Uuid::randomHex();
        $templateId = Uuid::randomHex();

        // Create the mail template type
        $mailTemplateTypeRepo->create([[
            'id'           => $typeId,
            'name'         => 'B2B Access Request',
            'technicalName' => self::B2B_MAIL_TEMPLATE_TECHNICAL_NAME,
            'availableEntities' => ['contactFormData' => null],
            'translations' => [
                'en-GB' => ['name' => 'B2B Access Request'],
                'de-DE' => ['name' => 'B2B Zugriffsanfrage'],
            ],
        ]], $context);

        // Create the mail template with HTML/plain content
        $mailTemplateRepo->create([[
            'id'                 => $templateId,
            'mailTemplateTypeId' => $typeId,
            'systemDefault'      => false,
            'translations'       => [
                'en-GB' => [
                    'senderName'   => '{{ salesChannel.name }}',
                    'subject'      => 'New B2B Access Request – {{ contactFormData.subject }}',
                    'contentHtml'  => $this->getHtmlTemplate(),
                    'contentPlain' => $this->getPlainTemplate(),
                ],
                'de-DE' => [
                    'senderName'   => '{{ salesChannel.name }}',
                    'subject'      => 'Neue B2B Zugriffsanfrage – {{ contactFormData.subject }}',
                    'contentHtml'  => $this->getHtmlTemplateDe(),
                    'contentPlain' => $this->getPlainTemplateDe(),
                ],
            ],
        ]], $context);

        $this->setDefaultConfig($systemConfig, $typeId);
    }

    private function setDefaultConfig(SystemConfigService $systemConfig, string $templateId): void
    {
        // Only set if not already configured
        $current = $systemConfig->get('HansAndKniebesTheme.config.b2bAccessRequestMailTemplateId');
        if (empty($current)) {
            $systemConfig->set('HansAndKniebesTheme.config.b2bAccessRequestMailTemplateId', $templateId);
        }
    }

    private function getHtmlTemplate(): string
    {
        return <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333;">

    <div style="background-color: #8a1a17; padding: 20px 30px;">
        <h1 style="color: #fff; margin: 0; font-size: 22px;">New B2B Access Request</h1>
    </div>

    <div style="padding: 30px; background: #f9f9f9;">
        <p>A new B2B access request has been submitted. Please review the details below:</p>

        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 10px; font-weight: bold; width: 40%;">Contact Person</td>
                <td style="padding: 10px;">{{ contactFormData.firstName }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd; background: #fff;">
                <td style="padding: 10px; font-weight: bold;">Email</td>
                <td style="padding: 10px;">{{ contactFormData.email }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 10px; font-weight: bold;">Details</td>
                <td style="padding: 10px; white-space: pre-line;">{{ contactFormData.comment }}</td>
            </tr>
        </table>
    </div>

</div>
HTML;
    }

    private function getPlainTemplate(): string
    {
        return <<<PLAIN
New B2B Access Request

Contact Person: {{ contactFormData.firstName }}
Email: {{ contactFormData.email }}
Details:
{{ contactFormData.comment }}
PLAIN;
    }

    private function getHtmlTemplateDe(): string
    {
        return <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333;">

    <div style="background-color: #8a1a17; padding: 20px 30px;">
        <h1 style="color: #fff; margin: 0; font-size: 22px;">Neue B2B Zugriffsanfrage</h1>
    </div>

    <div style="padding: 30px; background: #f9f9f9;">
        <p>Eine neue B2B-Zugriffsanfrage wurde eingereicht. Bitte prüfen Sie die folgenden Details:</p>

        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 10px; font-weight: bold; width: 40%;">Ansprechpartner</td>
                <td style="padding: 10px;">{{ contactFormData.firstName }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd; background: #fff;">
                <td style="padding: 10px; font-weight: bold;">E-Mail</td>
                <td style="padding: 10px;">{{ contactFormData.email }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 10px; font-weight: bold;">Details</td>
                <td style="padding: 10px; white-space: pre-line;">{{ contactFormData.comment }}</td>
            </tr>
        </table>
    </div>

</div>
HTML;
    }

    private function getPlainTemplateDe(): string
    {
        return <<<PLAIN
Neue B2B Zugriffsanfrage

Ansprechpartner: {{ contactFormData.firstName }}
E-Mail: {{ contactFormData.email }}
Details:
{{ contactFormData.comment }}
PLAIN;
    }
}
