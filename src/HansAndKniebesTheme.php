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
    public const B2B_ADMIN_TEMPLATE_TECHNICAL_NAME    = 'hans_kniebes_b2b_access_request';
    public const B2B_CUSTOMER_TEMPLATE_TECHNICAL_NAME = 'hans_kniebes_b2b_access_request_customer';
    public const B2B_PENDING_TEMPLATE_TECHNICAL_NAME  = 'hans_kniebes_b2b_pending_registration';

    public function postInstall(InstallContext $installContext): void
    {
        $this->createMailTemplates($installContext->getContext());
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
        $this->createMailTemplates($updateContext->getContext());
    }

    private function createMailTemplates(Context $context): void
    {
        /** @var EntityRepository $mailTemplateTypeRepo */
        $mailTemplateTypeRepo = $this->container->get('mail_template_type.repository');
        /** @var EntityRepository $mailTemplateRepo */
        $mailTemplateRepo = $this->container->get('mail_template.repository');
        /** @var SystemConfigService $systemConfig */
        $systemConfig = $this->container->get(SystemConfigService::class);

        $this->createAdminTemplate($mailTemplateTypeRepo, $mailTemplateRepo, $systemConfig, $context);
        $this->createCustomerTemplate($mailTemplateTypeRepo, $mailTemplateRepo, $systemConfig, $context);
        $this->createPendingTemplate($mailTemplateTypeRepo, $mailTemplateRepo, $systemConfig, $context);
    }

    private function createAdminTemplate(
        EntityRepository $typeRepo,
        EntityRepository $templateRepo,
        SystemConfigService $systemConfig,
        Context $context
    ): void {
        $existing = $typeRepo->search(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', self::B2B_ADMIN_TEMPLATE_TECHNICAL_NAME))->setLimit(1),
            $context
        )->first();

        if ($existing !== null) {
            $this->setDefaultConfig($systemConfig, 'b2bAccessRequestMailTemplateId', $existing->getId());
            $this->updateTemplateContent($templateRepo, $existing->getId(), [
                'en-GB' => [
                    'senderName'   => '{{ salesChannel.name }}',
                    'subject'      => 'New Business Access Request Received',
                    'contentHtml'  => $this->getAdminHtmlEn(),
                    'contentPlain' => $this->getAdminPlainEn(),
                ],
                'de-DE' => [
                    'senderName'   => '{{ salesChannel.name }}',
                    'subject'      => 'Neue Geschäftszugangsanfrage eingegangen',
                    'contentHtml'  => $this->getAdminHtmlDe(),
                    'contentPlain' => $this->getAdminPlainDe(),
                ],
            ], $context);
            return;
        }

        $typeId     = Uuid::randomHex();
        $templateId = Uuid::randomHex();

        $typeRepo->create([[
            'id'                => $typeId,
            'name'              => 'B2B Access Request (Admin)',
            'technicalName'     => self::B2B_ADMIN_TEMPLATE_TECHNICAL_NAME,
            'availableEntities' => ['contactFormData' => null],
            'translations'      => [
                'en-GB' => ['name' => 'B2B Access Request (Admin)'],
                'de-DE' => ['name' => 'B2B Zugriffsanfrage (Admin)'],
            ],
        ]], $context);

        $templateRepo->create([[
            'id'                 => $templateId,
            'mailTemplateTypeId' => $typeId,
            'systemDefault'      => false,
            'translations'       => [
                'en-GB' => [
                    'senderName'   => '{{ salesChannel.name }}',
                    'subject'      => 'New Business Access Request Received',
                    'contentHtml'  => $this->getAdminHtmlEn(),
                    'contentPlain' => $this->getAdminPlainEn(),
                ],
                'de-DE' => [
                    'senderName'   => '{{ salesChannel.name }}',
                    'subject'      => 'Neue Geschäftszugangsanfrage eingegangen',
                    'contentHtml'  => $this->getAdminHtmlDe(),
                    'contentPlain' => $this->getAdminPlainDe(),
                ],
            ],
        ]], $context);

        $this->setDefaultConfig($systemConfig, 'b2bAccessRequestMailTemplateId', $typeId);
    }

    private function createCustomerTemplate(
        EntityRepository $typeRepo,
        EntityRepository $templateRepo,
        SystemConfigService $systemConfig,
        Context $context
    ): void {
        $existing = $typeRepo->search(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', self::B2B_CUSTOMER_TEMPLATE_TECHNICAL_NAME))->setLimit(1),
            $context
        )->first();

        if ($existing !== null) {
            $this->setDefaultConfig($systemConfig, 'b2bAccessRequestCustomerMailTemplateId', $existing->getId());
            $this->updateTemplateContent($templateRepo, $existing->getId(), [
                'en-GB' => [
                    'senderName'   => '{{ salesChannel.name }}',
                    'subject'      => 'Your Access Request Has Been Submitted | Hans Kniebes GmbH',
                    'contentHtml'  => $this->getCustomerHtmlEn(),
                    'contentPlain' => $this->getCustomerPlainEn(),
                ],
                'de-DE' => [
                    'senderName'   => '{{ salesChannel.name }}',
                    'subject'      => 'Ihre Zugriffsanfrage wurde eingereicht | Hans Kniebes GmbH',
                    'contentHtml'  => $this->getCustomerHtmlDe(),
                    'contentPlain' => $this->getCustomerPlainDe(),
                ],
            ], $context);
            return;
        }

        $typeId     = Uuid::randomHex();
        $templateId = Uuid::randomHex();

        $typeRepo->create([[
            'id'                => $typeId,
            'name'              => 'B2B Access Request (Customer Confirmation)',
            'technicalName'     => self::B2B_CUSTOMER_TEMPLATE_TECHNICAL_NAME,
            'availableEntities' => ['contactFormData' => null],
            'translations'      => [
                'en-GB' => ['name' => 'B2B Access Request (Customer Confirmation)'],
                'de-DE' => ['name' => 'B2B Zugriffsanfrage (Kundenbestätigung)'],
            ],
        ]], $context);

        $templateRepo->create([[
            'id'                 => $templateId,
            'mailTemplateTypeId' => $typeId,
            'systemDefault'      => false,
            'translations'       => [
                'en-GB' => [
                    'senderName'   => '{{ salesChannel.name }}',
                    'subject'      => 'Your Access Request Has Been Submitted | Hans Kniebes GmbH',
                    'contentHtml'  => $this->getCustomerHtmlEn(),
                    'contentPlain' => $this->getCustomerPlainEn(),
                ],
                'de-DE' => [
                    'senderName'   => '{{ salesChannel.name }}',
                    'subject'      => 'Ihre Zugriffsanfrage wurde eingereicht | Hans Kniebes GmbH',
                    'contentHtml'  => $this->getCustomerHtmlDe(),
                    'contentPlain' => $this->getCustomerPlainDe(),
                ],
            ],
        ]], $context);

        $this->setDefaultConfig($systemConfig, 'b2bAccessRequestCustomerMailTemplateId', $typeId);
    }

    /**
     * Find the mail_template linked to the given type ID and upsert its translations.
     *
     * @param array<string, array<string, string>> $translations
     */
    private function updateTemplateContent(
        EntityRepository $templateRepo,
        string $typeId,
        array $translations,
        Context $context
    ): void {
        $template = $templateRepo->search(
            (new Criteria())->addFilter(new EqualsFilter('mailTemplateTypeId', $typeId))->setLimit(1),
            $context
        )->first();

        if ($template === null) {
            return;
        }

        $templateRepo->update([[
            'id'           => $template->getId(),
            'translations' => $translations,
        ]], $context);
    }

    private function setDefaultConfig(SystemConfigService $systemConfig, string $key, string $typeId): void
    {
        $current = $systemConfig->get('HansAndKniebesTheme.config.' . $key);
        if (empty($current)) {
            $systemConfig->set('HansAndKniebesTheme.config.' . $key, $typeId);
        }
    }

    // -------------------------------------------------------------------------
    // Admin templates
    // -------------------------------------------------------------------------

    private function getAdminHtmlEn(): string
    {
        return <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333;">
    <div style="background-color: #8a1a17; padding: 12px; text-align: center;">
        <h2 style="color: #fff; margin: 0; font-size: 16px;">New Business Access Request Received</h2>
    </div>
    <div style="padding: 30px; background: #f9f9f9;">
        <p>Dear Admin,</p>
        <p>A new business access request has been submitted on the webshop.</p>
        <h3 style="margin-top: 20px; margin-bottom: 10px;">Customer Details:</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 10px; font-weight: bold; width: 40%;">Customer Name</td>
                <td style="padding: 10px;">{{ contactFormData.firstName }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd; background: #fff;">
                <td style="padding: 10px; font-weight: bold;">Company Name</td>
                <td style="padding: 10px;">{{ contactFormData.subject }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 10px; font-weight: bold;">Email Address</td>
                <td style="padding: 10px;">{{ contactFormData.email }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd; background: #fff;">
                <td style="padding: 10px; font-weight: bold;">Additional Details</td>
                <td style="padding: 10px; white-space: pre-line;">{{ contactFormData.comment }}</td>
            </tr>
        </table>
        <p style="margin-top: 25px;">Please review the submitted information and approve or reject the request from the backend.</p>
        <p>After approval, the customer will gain access to retailer pricing.</p>
    </div>
</div>
HTML;
    }

    private function getAdminPlainEn(): string
    {
        return <<<PLAIN
New Business Access Request Received

Dear Admin,

A new business access request has been submitted on the webshop.

Customer Details:
Customer Name: {{ contactFormData.firstName }}
Company Name: {{ contactFormData.subject }}
Email Address: {{ contactFormData.email }}
{{ contactFormData.comment }}

Please review the submitted information and approve or reject the request from the backend.
After approval, the customer will gain access to retailer pricing.
PLAIN;
    }

    private function getAdminHtmlDe(): string
    {
        return <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333;">
    <div style="background-color: #8a1a17; padding: 12px; text-align: center;">
        <h2 style="color: #fff; margin: 0; font-size: 16px;">Neue Geschäftszugangsanfrage eingegangen</h2>
    </div>
    <div style="padding: 30px; background: #f9f9f9;">
        <p>Sehr geehrter Admin,</p>
        <p>Eine neue Geschäftszugangsanfrage wurde im Webshop eingereicht.</p>
        <h3 style="margin-top: 20px; margin-bottom: 10px;">Kundendaten:</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 10px; font-weight: bold; width: 40%;">Kundenname</td>
                <td style="padding: 10px;">{{ contactFormData.firstName }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd; background: #fff;">
                <td style="padding: 10px; font-weight: bold;">Firmenname</td>
                <td style="padding: 10px;">{{ contactFormData.subject }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 10px; font-weight: bold;">E-Mail-Adresse</td>
                <td style="padding: 10px;">{{ contactFormData.email }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd; background: #fff;">
                <td style="padding: 10px; font-weight: bold;">Weitere Details</td>
                <td style="padding: 10px; white-space: pre-line;">{{ contactFormData.comment }}</td>
            </tr>
        </table>
        <p style="margin-top: 25px;">Bitte prüfen Sie die eingereichten Informationen und genehmigen oder lehnen Sie die Anfrage im Backend ab.</p>
        <p>Nach der Genehmigung erhält der Kunde Zugang zu Händlerpreisen.</p>
    </div>
</div>
HTML;
    }

    private function getAdminPlainDe(): string
    {
        return <<<PLAIN
Neue Geschäftszugangsanfrage eingegangen

Sehr geehrter Admin,

Eine neue Geschäftszugangsanfrage wurde im Webshop eingereicht.

Kundendaten:
Kundenname: {{ contactFormData.firstName }}
Firmenname: {{ contactFormData.subject }}
E-Mail: {{ contactFormData.email }}
{{ contactFormData.comment }}

Bitte prüfen Sie die Anfrage im Backend und genehmigen oder lehnen Sie diese ab.
Nach der Genehmigung erhält der Kunde Zugang zu Händlerpreisen.
PLAIN;
    }

    // -------------------------------------------------------------------------
    // Customer confirmation templates
    // -------------------------------------------------------------------------

    private function getCustomerHtmlEn(): string
    {
        return <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333;">
    <div style="background-color: #8a1a17; padding: 12px; text-align: center;">
        <h2 style="color: #fff; margin: 0; font-size: 16px;">Your Access Request Has Been Submitted</h2>
    </div>
    <div style="padding: 30px; background: #f9f9f9;">
        <p>Dear {{ contactFormData.firstName }},</p>
        <p>Thank you for submitting your business access request to Hans Kniebes GmbH.</p>
        <p>We have successfully received your details and our team will review your application shortly.</p>
        <p>Please note that access to retailer pricing will only be available after verification and approval of your request.</p>
        <p>Once your application has been reviewed, you will receive a confirmation email regarding the status of your account.</p>
        <p>If you have any questions in the meantime, feel free to contact us:</p>
        <p>
            Email: <a href="mailto:info@hanskniebes.de">info@hanskniebes.de</a><br>
            Tel: +49 (0) 2224 6487
        </p>
        <p>Thank you for your interest in partnering with Hans Kniebes GmbH.</p>
        <p>Kind regards,<br><strong>Hans Kniebes GmbH</strong><br>
        <a href="https://www.hanskniebes.de/">https://www.hanskniebes.de/</a></p>
    </div>
</div>
HTML;
    }

    private function getCustomerPlainEn(): string
    {
        return <<<PLAIN
Your Access Request Has Been Submitted | Hans Kniebes GmbH

Dear {{ contactFormData.firstName }},

Thank you for submitting your business access request to Hans Kniebes GmbH.

We have successfully received your details and our team will review your application shortly.

Please note that access to retailer pricing will only be available after verification and approval of your request.

Once your application has been reviewed, you will receive a confirmation email regarding the status of your account.

If you have any questions in the meantime, feel free to contact us:
Email: info@hanskniebes.de
Tel: +49 (0) 2224 6487

Thank you for your interest in partnering with Hans Kniebes GmbH.

Kind regards,
Hans Kniebes GmbH
https://www.hanskniebes.de/
PLAIN;
    }

    private function getCustomerHtmlDe(): string
    {
        return <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333;">
    <div style="background-color: #8a1a17; padding: 12px; text-align: center;">
        <h2 style="color: #fff; margin: 0; font-size: 16px;">Ihre Zugriffsanfrage wurde eingereicht</h2>
    </div>
    <div style="padding: 30px; background: #f9f9f9;">
        <p>Sehr geehrte/r {{ contactFormData.firstName }},</p>
        <p>vielen Dank für Ihre Geschäftszugangsanfrage bei Hans Kniebes GmbH.</p>
        <p>Wir haben Ihre Daten erfolgreich erhalten und unser Team wird Ihre Anfrage in Kürze prüfen.</p>
        <p>Bitte beachten Sie, dass der Zugang zu Händlerpreisen erst nach Überprüfung und Genehmigung Ihrer Anfrage möglich ist.</p>
        <p>Sobald Ihre Anfrage geprüft wurde, erhalten Sie eine Bestätigungs-E-Mail über den Status Ihres Kontos.</p>
        <p>Wenn Sie in der Zwischenzeit Fragen haben, können Sie uns gerne kontaktieren:</p>
        <p>
            E-Mail: <a href="mailto:info@hanskniebes.de">info@hanskniebes.de</a><br>
            Tel: +49 (0) 2224 6487
        </p>
        <p>Vielen Dank für Ihr Interesse an einer Partnerschaft mit Hans Kniebes GmbH.</p>
        <p>Mit freundlichen Grüßen,<br><strong>Hans Kniebes GmbH</strong><br>
        <a href="https://www.hanskniebes.de/">https://www.hanskniebes.de/</a></p>
    </div>
</div>
HTML;
    }

    private function getCustomerPlainDe(): string
    {
        return <<<PLAIN
Ihre Zugriffsanfrage wurde eingereicht | Hans Kniebes GmbH

Sehr geehrte/r {{ contactFormData.firstName }},

vielen Dank für Ihre Geschäftszugangsanfrage bei Hans Kniebes GmbH.

Wir haben Ihre Daten erfolgreich erhalten und unser Team wird Ihre Anfrage in Kürze prüfen.

Bitte beachten Sie, dass der Zugang zu Händlerpreisen erst nach Überprüfung und Genehmigung Ihrer Anfrage möglich ist.

Sobald Ihre Anfrage geprüft wurde, erhalten Sie eine Bestätigungs-E-Mail über den Status Ihres Kontos.

Wenn Sie Fragen haben, kontaktieren Sie uns:
E-Mail: info@hanskniebes.de
Tel: +49 (0) 2224 6487

Vielen Dank für Ihr Interesse an einer Partnerschaft mit Hans Kniebes GmbH.

Mit freundlichen Grüßen,
Hans Kniebes GmbH
https://www.hanskniebes.de/
PLAIN;
    }

    // -------------------------------------------------------------------------
    // Pending registration templates (no VAT — waiting for admin approval)
    // -------------------------------------------------------------------------

    private function createPendingTemplate(
        EntityRepository $typeRepo,
        EntityRepository $templateRepo,
        SystemConfigService $systemConfig,
        Context $context
    ): void {
        $existing = $typeRepo->search(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', self::B2B_PENDING_TEMPLATE_TECHNICAL_NAME))->setLimit(1),
            $context
        )->first();

        if ($existing !== null) {
            $this->setDefaultConfig($systemConfig, 'b2bPendingRegistrationMailTemplateId', $existing->getId());
            $this->updateTemplateContent($templateRepo, $existing->getId(), [
                'en-GB' => [
                    'senderName'   => '{{ salesChannel.name }}',
                    'subject'      => 'Your Business Registration Request Has Been Received | Hans Kniebes GmbH',
                    'contentHtml'  => $this->getPendingHtmlEn(),
                    'contentPlain' => $this->getPendingPlainEn(),
                ],
                'de-DE' => [
                    'senderName'   => '{{ salesChannel.name }}',
                    'subject'      => 'Ihre Geschäftsregistrierungsanfrage ist eingegangen | Hans Kniebes GmbH',
                    'contentHtml'  => $this->getPendingHtmlDe(),
                    'contentPlain' => $this->getPendingPlainDe(),
                ],
            ], $context);
            return;
        }

        $typeId     = Uuid::randomHex();
        $templateId = Uuid::randomHex();

        $typeRepo->create([[
            'id'                => $typeId,
            'name'              => 'B2B Pending Registration',
            'technicalName'     => self::B2B_PENDING_TEMPLATE_TECHNICAL_NAME,
            'availableEntities' => ['customer' => 'customer'],
            'translations'      => [
                'en-GB' => ['name' => 'B2B Pending Registration'],
                'de-DE' => ['name' => 'B2B Registrierung ausstehend'],
            ],
        ]], $context);

        $templateRepo->create([[
            'id'                 => $templateId,
            'mailTemplateTypeId' => $typeId,
            'systemDefault'      => false,
            'translations'       => [
                'en-GB' => [
                    'senderName'   => '{{ salesChannel.name }}',
                    'subject'      => 'Your Business Registration Request Has Been Received | Hans Kniebes GmbH',
                    'contentHtml'  => $this->getPendingHtmlEn(),
                    'contentPlain' => $this->getPendingPlainEn(),
                ],
                'de-DE' => [
                    'senderName'   => '{{ salesChannel.name }}',
                    'subject'      => 'Ihre Geschäftsregistrierungsanfrage ist eingegangen | Hans Kniebes GmbH',
                    'contentHtml'  => $this->getPendingHtmlDe(),
                    'contentPlain' => $this->getPendingPlainDe(),
                ],
            ],
        ]], $context);

        $this->setDefaultConfig($systemConfig, 'b2bPendingRegistrationMailTemplateId', $typeId);
    }

    private function getPendingHtmlEn(): string
    {
        return <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333;">
    <div style="background-color: #8a1a17; padding: 12px; text-align: center;">
        <h2 style="color: #fff; margin: 0; font-size: 16px;">Your Business Registration Request Has Been Received</h2>
    </div>
    <div style="padding: 30px; background: #f9f9f9;">
        <p>Dear {{ customer.firstName }} {{ customer.lastName }},</p>
        <p>Thank you for registering with Hans Kniebes.</p>
        <p>We have successfully received your B2B retailer registration request. Our team will now review and verify your application.</p>
        <p>Please note that retailer pricing will become visible only after your account has been approved by our team.</p>
        <p>Once your request is approved, you will receive a confirmation email with access to retailer pricing.</p>
        <p>If you are interested in becoming an export partner, feel free to contact us using the details below:</p>
        <p>
            Email: <a href="mailto:info@hanskniebes.de">info@hanskniebes.de</a><br>
            Tel: +49 (0) 2224 6487
        </p>
        <p>Thank you for your interest in partnering with us.</p>
        <p>Kind regards,<br><strong>Hans Kniebes GmbH</strong><br>
        <a href="https://www.hanskniebes.de/">https://www.hanskniebes.de/</a></p>
    </div>
</div>
HTML;
    }

    private function getPendingPlainEn(): string
    {
        return <<<PLAIN
Your Business Registration Request Has Been Received | Hans Kniebes GmbH

Dear {{ customer.firstName }} {{ customer.lastName }},

Thank you for registering with Hans Kniebes.

We have successfully received your B2B retailer registration request. Our team will now review and verify your application.

Please note that retailer pricing will become visible only after your account has been approved by our team.

Once your request is approved, you will receive a confirmation email with access to retailer pricing.

If you are interested in becoming an export partner, feel free to contact us:
Email: info@hanskniebes.de
Tel: +49 (0) 2224 6487

Thank you for your interest in partnering with us.

Kind regards,
Hans Kniebes GmbH
https://www.hanskniebes.de/
PLAIN;
    }

    private function getPendingHtmlDe(): string
    {
        return <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333;">
    <div style="background-color: #8a1a17; padding: 12px; text-align: center;">
        <h2 style="color: #fff; margin: 0; font-size: 16px;">Ihre Geschäftsregistrierungsanfrage ist eingegangen</h2>
    </div>
    <div style="padding: 30px; background: #f9f9f9;">
        <p>Sehr geehrte/r {{ customer.firstName }} {{ customer.lastName }},</p>
        <p>vielen Dank für Ihre Registrierung bei Hans Kniebes.</p>
        <p>Wir haben Ihre B2B-Händlerregistrierungsanfrage erfolgreich erhalten. Unser Team wird Ihre Anfrage nun prüfen und verifizieren.</p>
        <p>Bitte beachten Sie, dass Händlerpreise erst nach Genehmigung Ihres Kontos durch unser Team sichtbar werden.</p>
        <p>Sobald Ihre Anfrage genehmigt wurde, erhalten Sie eine Bestätigungs-E-Mail mit Zugang zu Händlerpreisen.</p>
        <p>Wenn Sie Interesse daran haben, Exportpartner zu werden, kontaktieren Sie uns gerne:</p>
        <p>
            E-Mail: <a href="mailto:info@hanskniebes.de">info@hanskniebes.de</a><br>
            Tel: +49 (0) 2224 6487
        </p>
        <p>Vielen Dank für Ihr Interesse an einer Partnerschaft mit uns.</p>
        <p>Mit freundlichen Grüßen,<br><strong>Hans Kniebes GmbH</strong><br>
        <a href="https://www.hanskniebes.de/">https://www.hanskniebes.de/</a></p>
    </div>
</div>
HTML;
    }

    private function getPendingPlainDe(): string
    {
        return <<<PLAIN
Ihre Geschäftsregistrierungsanfrage ist eingegangen | Hans Kniebes GmbH

Sehr geehrte/r {{ customer.firstName }} {{ customer.lastName }},

vielen Dank für Ihre Registrierung bei Hans Kniebes.

Wir haben Ihre B2B-Händlerregistrierungsanfrage erfolgreich erhalten. Unser Team wird Ihre Anfrage nun prüfen und verifizieren.

Bitte beachten Sie, dass Händlerpreise erst nach Genehmigung Ihres Kontos sichtbar werden.

Sobald Ihre Anfrage genehmigt wurde, erhalten Sie eine Bestätigungs-E-Mail mit Zugang zu Händlerpreisen.

Kontakt:
E-Mail: info@hanskniebes.de
Tel: +49 (0) 2224 6487

Vielen Dank für Ihr Interesse an einer Partnerschaft mit uns.

Mit freundlichen Grüßen,
Hans Kniebes GmbH
https://www.hanskniebes.de/
PLAIN;
    }
}
