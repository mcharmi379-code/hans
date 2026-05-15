import Plugin from 'src/plugin-system/plugin.class';

export default class NavigationFlyoutSubcategoriesPlugin extends Plugin {
    init() {
        this._registerIndicators();
        this._registerHoverHandlers();
    }

    _registerIndicators() {
        const columns = this.el.querySelectorAll('.navigation-flyout-categories.is-level-0 > div');

        columns.forEach((column) => {
            const subCategories = column.querySelector('.navigation-flyout-categories.is-level-1');
            const link = column.querySelector(':scope > .nav-item');

            if (!subCategories || !link) {
                return;
            }

            link.classList.add('has-subcategories');

            if (!link.querySelector('.navigation-flyout-link-indicator')) {
                const indicator = document.createElement('span');
                indicator.className = 'navigation-flyout-link-indicator';
                indicator.textContent = '>';
                indicator.setAttribute('aria-hidden', 'true');
                link.appendChild(indicator);
            }
        });
    }

    _registerHoverHandlers() {
        const columns = Array.from(this.el.querySelectorAll('.navigation-flyout-categories.is-level-0 > div'));

        columns.forEach((column) => {
            const link = column.querySelector(':scope > .nav-item');
            const subCategories = column.querySelector('.navigation-flyout-categories.is-level-1');

            if (!link || !subCategories) {
                return;
            }

            const open = () => this._openColumn(column);

            column.addEventListener('mouseenter', open);
            subCategories.addEventListener('mouseenter', open);
            link.addEventListener('focusin', open);
            link.addEventListener('click', open);
        });

        this.el.addEventListener('mouseleave', () => this._closeAll());
    }

    _openColumn(column) {
        this._closeAll();

        const subCategories = column.querySelector('.navigation-flyout-categories.is-level-1');

        if (subCategories) {
            subCategories.classList.add('show');
        }

        column.classList.add('is-open');
    }

    _closeAll() {
        this.el.querySelectorAll('.navigation-flyout-categories.is-level-1.show').forEach((item) => {
            item.classList.remove('show');
        });

        this.el.querySelectorAll('.navigation-flyout-col.is-open').forEach((item) => {
            item.classList.remove('is-open');
        });
    }
}
