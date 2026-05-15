<?php declare(strict_types=1);

namespace HansAndKniebesTheme\Storefront\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterSubscribeRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class NewsletterSubscriber implements EventSubscriberInterface
{
    private array $processedEmails = [];

    public function __construct(
        private readonly AbstractNewsletterSubscribeRoute $subscribeRoute,
        private readonly RequestStack $requestStack
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
            OrderEvents::ORDER_WRITTEN_EVENT => 'onOrderWritten',
        ];
    }

    public function onCustomerWritten(EntityWrittenEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || $request->get('option') !== 'subscribe') {
            return;
        }

        $context = $request->attributes->get('sw-sales-channel-context');
        if (!$context instanceof SalesChannelContext) {
            return;
        }

        $email = $request->get('email');
        $data = [
            'option' => 'direct',
            'email' => $email,
            'storefrontUrl' => $request->attributes->get('sw-storefront-url'),
            'firstName' => $request->get('billingAddress')['firstName'] ?? null,
            'lastName' => $request->get('billingAddress')['lastName'] ?? null,
            'zipCode' => $request->get('billingAddress')['zipcode'] ?? null,
            'city' => $request->get('billingAddress')['city'] ?? null,
        ];

        try {
            $dataBag = new RequestDataBag($data);
            $this->subscribeRoute->subscribe($dataBag, $context, true);
        } catch (\Exception $e) {
            // Ignore errors
        }
    }

    public function onOrderWritten(EntityWrittenEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || $request->get('option') !== 'subscribe') {
            return;
        }

        $context = $request->attributes->get('sw-sales-channel-context');
        if (!$context instanceof SalesChannelContext || !$context->getCustomer()) {
            return;
        }

        $customer = $context->getCustomer();
        $email = $customer->getEmail();

        if (in_array($email, $this->processedEmails, true)) {
            return;
        }

        $this->processedEmails[] = $email;

        $data = [
            'option' => 'direct',
            'email' => $email,
            'storefrontUrl' => $request->attributes->get('sw-storefront-url'),
            'firstName' => $customer->getFirstName(),
            'lastName' => $customer->getLastName(),
        ];

        if ($customer->getDefaultBillingAddress()) {
            $data['zipCode'] = $customer->getDefaultBillingAddress()->getZipcode();
            $data['city'] = $customer->getDefaultBillingAddress()->getCity();
        }

        try {
            $dataBag = new RequestDataBag($data);
            $this->subscribeRoute->subscribe($dataBag, $context, true);
        } catch (\Exception $e) {
            // Ignore errors
        }
    }
}
