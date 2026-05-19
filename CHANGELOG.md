# 1.3.29
- Added customer confirmation email on B2B registration form submission (no-VAT flow) using new `hans_kniebes_b2b_pending_registration` mail template in EN and DE.
- Auto-accept flow (valid VAT) now dispatches `CustomerGroupRegistrationAccepted` event to trigger Shopware's built-in acceptance email.
- Added `b2bPendingRegistrationMailTemplateId` config dropdown in theme settings.
- Added `b2bAccessRequestCustomerMailTemplateId` config for customer confirmation on access request form.

# 1.3.28
- Unhide the rating on the product listing page, and add a read more link to redirect to the product details page if there is a lot of content in review.

# 1.3.27
- Added B2B access request email template with EN/DE translations.
- Replaced registration config fields with entity dropdown selectors.
- Auto-approve customer group registration when VAT ID is provided.

# 1.3.26
- Added a new CTA button in the header to redirect users to the B2B customer registration flow.
- Added a new customer request form for existing ERP users.
- Integrated the customer request form with the contact form email flow.
- Added a theme configuration option to select the email template for customer request notifications.
-Implemented initial support for automatic user acceptance based on valid VAT ID verification.

# 1.3.25
- Replaced the default register form on the login page with the Customer Group Registration form, configurable per sales channel via theme settings.
- Login form remains fully functional alongside the B2B registration form.

# 1.3.24
- Added colour swatches for Lenz group main products on the listing page.
- New `LenzGroupColorSubscriber` loads sibling products sharing the same `lenz_variant_manager_product_group_identifier` custom field and extracts their "Colour" property in a single DB query.
- Colour swatches link to each sibling product's detail page.
- Active product swatch is highlighted with `is-active` class.
- Respects the 5-swatch limit with a `+N` expand button, consistent with the existing MaxiaListingVariants6 colour limit behaviour.

# 1.3.23
- Improved flyout navigation subcategory behavior: right-side submenu positioning, hover persistence, and indicator for subcategories.
- Removed the "category details" From navigation.

# 1.3.20
- Limited listing color swatches to 5 visible options in one line and displayed the expand `+` indicator for remaining variants.

# 1.3.19
- Fixed listing variant display to respect the property display type (color now shows even when variant media exists by overriding the plugin file).

# 1.3.18
- Added auto-scroll functionality for header USP bar items in mobile view
- Implemented smooth scrolling with pause and reset behavior
- Auto-scroll stops on user interaction (touch/scroll)
- Hidden scrollbars for cleaner mobile experience

# 1.3.17
- Removed white space on the homepage. Improved the visualization of the product details page.

# 1.3.15
- Added the free gift message for newsletter subscription on the registration page, checkout page, and newsletter subscription form.

# 1.3.14
- Updated the product details page structure: the product name and rating are now displayed together above the product price.
- Added design changes to the Our Brands page.

# 1.3.13
- Removed the “Holiday & System Update Notice Across Webshop” from the home & order history page.

# 1.3.12
- Added the “Holiday & System Update Notice Across Webshop” to the home & order history page.

# 1.3.11
- Fixed newsletter subscription to work immediately on registration and checkout.
- Removed deferred processing (KernelEvents::TERMINATE) for instant subscription.
- Fixed duplicate newsletter emails during checkout by tracking processed emails.

# 1.3.9
- Updated review discount flow: Email sent after order placement with discount code information.
- Discount code (HK15DANKE) activated only after review approval by admin.
- Added customer purchase verification before activating discount.
- Tag 'discount_email_sent' prevents duplicate emails on subsequent orders.
- Tag 'received_HK15DANKE' enables voucher redemption after review approval.

# 1.3.5
- Added default-checked newsletter subscription checkbox on registration and checkout pages.
- Implemented automatic newsletter subscription/unsubscription based on checkbox state.
- Newsletter subscription now includes customer details (firstName, lastName, zipCode, city).
- Optimized newsletter processing to run in background for faster registration and checkout.

# 1.3.4
- Added functionality to send a 15% discount code to customers after submitting a review.

# 1.3.3
- Added the static image links in the homepage category slider for category listings. 

# 1.3.1
- Added a quick link button on the product details page to open the review tab.
- Improved the styling of the homepage category listing and product details page.
- Mobile Product Listing: Adjusted the layout to display at least four products per screen for better visibility and user experience.

# 1.2.1
- Added functionality to display the product video on the product detail page.
- Implemented autoplay for product videos on the product detail page.

# 1.1.0
- Added a horizontal scroller below the product slider. 
- To enable this feature, add the CSS class "custom-product-horizontal-scroller" to the product slider element layout. 

# 1.0.0
- Initial theme development with custom header and footer overrides.
- Implemented functionality to display categories in the header bar.
