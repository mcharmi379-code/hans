<?php declare(strict_types=1);

namespace HansAndKniebesTheme\Storefront\Subscriber;

use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReviewApprovalSubscriber implements EventSubscriberInterface
{
    private EntityRepository $productReviewRepository;
    private EntityRepository $customerRepository;
    private EntityRepository $tagRepository;
    private EntityRepository $orderRepository;
    private EntityRepository $languageRepository;
    private AbstractMailService $mailService;

    public function __construct(
        EntityRepository $productReviewRepository,
        EntityRepository $customerRepository,
        EntityRepository $tagRepository,
        EntityRepository $orderRepository,
        EntityRepository $languageRepository,
        AbstractMailService $mailService
    )
    {
        $this->productReviewRepository = $productReviewRepository;
        $this->customerRepository = $customerRepository;
        $this->tagRepository = $tagRepository;
        $this->orderRepository = $orderRepository;
        $this->languageRepository = $languageRepository;
        $this->mailService = $mailService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'product_review.written' => 'onReviewWritten',
            OrderEvents::ORDER_WRITTEN_EVENT => 'onOrderWritten',
        ];
    }

    public function onOrderWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() === 'insert') {
                $orderId = $writeResult->getPrimaryKey();
                $this->tagCustomerForDiscount($orderId, $event->getContext());
            }
        }
    }

    public function onReviewWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();

            if (isset($payload['status'])) {
                if ($payload['status'] === true) {
                    $reviewId = $writeResult->getPrimaryKey();
                    $this->activateDiscountCode($reviewId, $event->getContext());
                }
            }
        }
    }

    private function tagCustomerForDiscount(string $orderId, $context): void
    {
        try {
            $criteria = new Criteria([$orderId]);
            $criteria->addAssociation('orderCustomer.customer');
            $order = $this->orderRepository->search($criteria, $context)->first();

            if (!$order || !$order->getOrderCustomer() || !$order->getOrderCustomer()->getCustomer()) {
                return;
            }

            $customer = $order->getOrderCustomer()->getCustomer();
            
            if (!$this->hasTag($customer->getId(), 'discount_email_sent', $context)) {
                $this->manageCustomerTag($customer->getId(), 'discount_email_sent', true, $context);
                $this->sendThankYouEmail($customer, $context);
            }
        } catch (\Exception $e) {
            // Silently fail to not break order placement
        }
    }

    private function activateDiscountCode(string $reviewId, $context): void
    {
        try {
            $review = $this->getReviewWithCustomer($reviewId, $context);
            if (!$review || !$review->getCustomer()) {
                return;
            }

            $customer = $review->getCustomer();
            
            if ($this->hasTag($customer->getId(), 'received_HK15DANKE', $context)) {
                return;
            }

            if ($this->hasCustomerPurchasedProduct($customer->getId(), $review->getProductId(), $context)) {
                $this->manageCustomerTag($customer->getId(), 'received_HK15DANKE', true, $context);
            }
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    private function getReviewWithCustomer(string $reviewId, $context)
    {
        $criteria = new Criteria([$reviewId]);
        $criteria->addAssociation('customer');
        return $this->productReviewRepository->search($criteria, $context)->first();
    }

    private function manageCustomerTag(string $customerId, string $tagName, bool $add, $context): void
    {
        $tagCriteria = new Criteria();
        $tagCriteria->addFilter(new EqualsFilter('name', $tagName));
        $tag = $this->tagRepository->search($tagCriteria, $context)->first();

        if (!$tag && $add) {
            $this->tagRepository->create([['name' => $tagName]], $context);
            $tag = $this->tagRepository->search($tagCriteria, $context)->first();
        }

        if ($tag && $add) {
            $this->customerRepository->update([
                [
                    'id' => $customerId,
                    'tags' => [
                        ['id' => $tag->getId()]
                    ]
                ]
            ], $context);
        }
    }

    private function hasTag(string $customerId, string $tagName, $context): bool
    {
        $customerCriteria = new Criteria([$customerId]);
        $customerCriteria->addAssociation('tags');
        $customer = $this->customerRepository->search($customerCriteria, $context)->first();

        if ($customer && $customer->getTags()) {
            foreach ($customer->getTags() as $tag) {
                if ($tag->getName() === $tagName) {
                    return true;
                }
            }
        }
        return false;
    }

    private function hasCustomerPurchasedProduct(string $customerId, string $productId, $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('orderCustomer.customer.id', $customerId),
                new EqualsFilter('lineItems.productId', $productId)
            ])
        );
        $criteria->setLimit(1);

        return $this->orderRepository->search($criteria, $context)->getTotal() > 0;
    }

    private function sendThankYouEmail($customer, $context): void
    {
        // Get language with locale from database
        $languageCriteria = new Criteria([$customer->getLanguageId()]);
        $languageCriteria->addAssociation('locale');
        $language = $this->languageRepository->search($languageCriteria, $context)->first();

        $isGerman = false;
        if ($language && $language->getLocale()) {
            $localeCode = $language->getLocale()->getCode();
            $isGerman = str_starts_with($localeCode, 'de');
        }

        $subject = $isGerman ? 'Vielen Dank für Ihre Bestellung – HK15DANKE' : 'Thank you for your order – HK15DANKE';

        /**
         * GERMAN HTML
         */
        if ($isGerman) {
            $contentHtml = '
                <table width="100%" cellpadding="0" cellspacing="0" style="font-family: Arial, sans-serif; color:#333;">
                <tr><td style="padding:20px;">
                <p>Hallo ' . $customer->getFirstName() . ',</p>
                
                <h2 style="color:#8a1a17; font-size:26px;margin-top:10px">DANKE</h2>
                
                <p>Vielen Dank für Ihre Bestellung. Wir hoffen, dass unser Produkt Ihre Erwartungen erfüllt und Sie lange begleiten wird.</p>
                
                <p>Auch bei uns kann trotz größter Sorgfalt einmal ein Missgeschick passieren. Sollte es also ein Problem oder eine Unstimmigkeit geben, schreiben Sie uns einfach – wir kümmern uns schnell und unkompliziert darum.</p>
                
                <p><strong>info@hanskniebes.de</strong></p>
                
                <hr style="border:0;border-top:1px solid #ccc;margin:30px 0">
                
                <h3 style="font-size:20px;">Ihre Meinung ist uns wichtig!</h3>
                <p>Damit unsere Produkte auch online die Wertschätzung erhalten, freuen wir uns über Ihre Unterstützung in Form einer ehrlichen Bewertung.</p>
                
                <p><strong>So einfach geht’s:</strong></p>
                
                <ol style="padding-left:20px;">
                <li>Besuchen Sie <a href="https://www.hanskniebes.de" target="_blank">www.hanskniebes.de</a></li>
                <li>Öffnen Sie Ihr gekauftes Produkt</li>
                <li>Klicken Sie auf den Reiter „Bewertungen“</li>
                <li>Wählen Sie „Bewertung schreiben“</li>
                <li>Melden Sie sich kurz an (Spam-Schutz)</li>
                </ol>
                
                <p><strong>Als kleines Dankeschön bieten wir Ihnen gerne einen Rabattcode in Höhe von 15 % an. Diesen können Sie bei Ihrer nächsten Bestellung einlösen.</strong></p>
                
                <p style="font-size:22px;color:#8a1a17;font-weight:bold;">CODE: HK15DANKE</p>
                <p style="font-size:12px;color:#666;">(einlösbar auf <a href="https://www.hanskniebes.de" target="_blank">www.hanskniebes.de</a>)</p>
                
                </td></tr></table>
                ';

            $contentPlain = "
                Hallo " . $customer->getFirstName() . ",
                
                DANKE für Ihre Bestellung.
                
                Sollte es ein Problem geben, schreiben Sie uns einfach: info@hanskniebes.de
                
                Bewertung:
                1. www.hanskniebes.de
                2. Produkt öffnen
                3. Bewertungen
                4. Bewertung schreiben
                5. kurz anmelden
                
                15% Rabatt – Code: HK15DANKE
                ";

        } else {

            /**
             * ENGLISH HTML version
             */
            $contentHtml = '
                <table width="100%" cellpadding="0" cellspacing="0" style="font-family: Arial, sans-serif; color:#333;">
                <tr><td style="padding:20px;">
                <p>Hello ' . $customer->getFirstName() . ',</p>
                
                <h2 style="color:#8a1a17; font-size:26px;margin-top:10px">THANK YOU</h2>
                
                <p>Thank you very much for your order! We hope our products meet your expectations and accompany you for a long time.</p>
                
                <p>If there should be any issue or inconsistency, please write to us – we will take care of it quickly and easily.</p>
                
                <p><strong>info@hanskniebes.de</strong></p>
                
                <hr style="border:0;border-top:1px solid #ccc;margin:30px 0">
                
                <h3 style="font-size:20px;">Your opinion matters!</h3>
                <p>So that our products also receive appreciation online, we would be happy if you leave an honest review.</p>
                
                <p><strong>It’s that simple:</strong></p>
                
                <ol style="padding-left:20px;">
                <li>Visit <a href="https://www.hanskniebes.de" target="_blank">www.hanskniebes.de</a></li>
                <li>Open your purchased product</li>
                <li>Click on “Reviews”</li>
                <li>Select “Write a review”</li>
                <li>Quick login required (anti spam)</li>
                </ol>
                
                <p><strong>As a little thank you, we’re happy to offer you a 15% discount code below. You can use this on your next order.</strong></p>
                
                <p style="font-size:22px;color:#8a1a17;font-weight:bold;">CODE: HK15DANKE</p>
                <p style="font-size:12px;color:#666;">(redeemable at <a href="https://www.hanskniebes.de" target="_blank">www.hanskniebes.de</a>)</p>
                
                </td></tr></table>
                ';

            $contentPlain = "
                Hello " . $customer->getFirstName() . ",
                
                Thank you for your order!
                
                If there is any issue, write to us: info@hanskniebes.de
                
                Review steps:
                1. www.hanskniebes.de
                2. open product
                3. Reviews
                4. Write a review
                5. quick login
                
                15% discount – Code: HK15DANKE
                ";
        }

        $data = [
            'recipients' => [$customer->getEmail() => $customer->getFirstName() . ' ' . $customer->getLastName()],
            'senderName' => 'Hans & Kniebes',
            'subject' => $subject,
            'contentHtml' => $contentHtml,
            'contentPlain' => $contentPlain,
            'salesChannelId' => null
        ];

        $this->mailService->send($data, $context);
    }

}
