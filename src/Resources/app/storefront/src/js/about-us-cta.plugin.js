import Plugin from 'src/plugin-system/plugin.class';

export default class AboutUsCtaPlugin extends Plugin {
    init() {
        this.cta = document.getElementById('about-us-cta');
        this.footer = document.querySelector('.footer-main');
        this.aboutSection = document.querySelector('.about-banner');
        this.isFooterVisible = false;

        if (!this.cta || !this.aboutSection) {
            return;
        }

        this.handleScroll = this.handleScroll.bind(this);
        window.addEventListener('scroll', this.handleScroll, { passive: true });

        if (this.footer && 'IntersectionObserver' in window) {
            this.observeFooter();
        }

        this.handleScroll();
    }

    handleScroll() {
        const scrollPos = window.scrollY + window.innerHeight;
        const totalHeight = document.documentElement.scrollHeight;
        const scrollPercent = (scrollPos / totalHeight) * 100;

        if (scrollPercent > 30 && !this.isFooterVisible) {
            this.showCta();
            return;
        }

        this.hideCta();
    }

    observeFooter() {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                this.isFooterVisible = entry.isIntersecting;
                this.handleScroll();
            });
        }, {
            threshold: 0.05,
        });

        observer.observe(this.footer);
    }

    showCta() {
        this.cta.classList.remove('d-none');
    }

    hideCta() {
        this.cta.classList.add('d-none');
    }
}
