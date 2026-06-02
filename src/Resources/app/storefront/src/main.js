import './js/custom-horizontal-scroller.js';
import './js/header-auto-scroll.js';
import NavigationFlyoutSubcategoriesPlugin from './js/navigation-flyout-subcategories.plugin';
import VideoAutoplayPlugin from './js/video-autoplay.plugin';
import ShowReviewTabPlugin from './js/show-review-tab.plugin';
import AboutUsCtaPlugin from './js/about-us-cta.plugin';

PluginManager.register('VideoAutoplayPlugin', VideoAutoplayPlugin);
PluginManager.register('ShowReviewTabPlugin', ShowReviewTabPlugin, '[data-show-review-tab-plugin]');
PluginManager.register('NavigationFlyoutSubcategoriesPlugin', NavigationFlyoutSubcategoriesPlugin, '.navigation-flyout-content');
PluginManager.register('AboutUsCtaPlugin', AboutUsCtaPlugin, '[data-about-us-cta]');
