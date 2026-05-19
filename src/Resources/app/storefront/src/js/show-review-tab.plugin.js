import Plugin from 'src/plugin-system/plugin.class';

export default class ShowReviewTabPlugin extends Plugin {
    init() {

        this.button = this.el.querySelector('.js-show-review-tab');

        if (!this.button) {
            return;
        }

        this.button.addEventListener('click', this._onClick.bind(this));
    }

    _onClick(e) {
        e.preventDefault();

        // Try a few fallbacks to find the tab trigger and pane
        let reviewTabTrigger = document.querySelector('[data-review-tab-trigger]');
        if (!reviewTabTrigger) reviewTabTrigger = document.querySelector('.review-tab');
        if (!reviewTabTrigger) reviewTabTrigger = document.querySelector('[href^="#review-tab"]');

        // If we have the trigger element, try to determine pane selector from href/aria-controls
        let reviewPane = null;
        if (reviewTabTrigger) {
            // find target id from href or aria-controls
            const href = reviewTabTrigger.getAttribute('href') || reviewTabTrigger.dataset.target;
            const ariaControls = reviewTabTrigger.getAttribute('aria-controls');
            const targetSelector = href && href.startsWith('#') ? href : (ariaControls ? `#${ariaControls}` : null);

            if (targetSelector) {
                reviewPane = document.querySelector(targetSelector);
            }
        }

        // Fallback: look for common review pane selectors used in your markup
        if (!reviewPane) {
            reviewPane = document.querySelector('#review-form') ||
                document.querySelector('.product-detail-review') ||
                document.querySelector('.product-detail-review-content');
        }

        // If the reviews are in an offcanvas or hidden container, try to open them first
        // Try to find a bootstrap offcanvas ancestor for the review pane
        if (reviewPane) {
            const offcanvasEl = reviewPane.closest('.offcanvas');
            if (offcanvasEl) {
                try {
                    const offcanvasInstance = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
                    offcanvasInstance.show();
                } catch (err) {
                    console.warn('ShowReviewTabPlugin: could not open offcanvas', err);
                }
            }
        }

        // Activate the review tab (if trigger exists)
        if (reviewTabTrigger) {
            // Use native click to trigger bootstrap tab switching
            reviewTabTrigger.click();
        }

        // After a short delay, center the reviewPane in viewport
        setTimeout(() => {
            if (!reviewPane) {
                console.warn('ShowReviewTabPlugin: no review pane found to scroll to');
                return;
            }

            // If the review pane contains a collapsible review form and it's collapsed, open it
            const writeTeaserBtn = reviewPane.querySelector('.product-detail-review-teaser-btn');
            if (writeTeaserBtn && writeTeaserBtn.getAttribute('aria-expanded') === 'false') {
                writeTeaserBtn.click();
            }

            // Compute scroll position to center the element
            const rect = reviewPane.getBoundingClientRect();
            const elementTop = rect.top + window.scrollY;
            const viewportHeight = window.innerHeight;
            // center the element; adjust headerOffset if you have a sticky header
            const headerOffset = 100;
            const offset = elementTop - (viewportHeight / 2) + (reviewPane.offsetHeight / 2) - headerOffset;

            window.scrollTo({
                top: Math.max(0, Math.round(offset)),
                behavior: 'smooth'
            });

            // optional visual highlight for a moment
            this._flashElement(reviewPane);
        }, 300);
    }

    _flashElement(el) {
        if (!el) return;
        const origOutline = el.style.outline;
        el.style.transition = 'box-shadow 300ms ease';
        el.style.boxShadow = '0 0 0 4px rgba(0,123,255,0.2)';
        setTimeout(() => {
            el.style.boxShadow = '';
            el.style.outline = origOutline;
        }, 900);
    }
}
