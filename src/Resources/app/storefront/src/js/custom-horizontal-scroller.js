document.addEventListener('DOMContentLoaded', function() {

    // Only target elements with custom-product-horizontal-scroller class
    const customSliders = document.querySelectorAll('.custom-product-horizontal-scroller');
    
    if (customSliders.length === 0) {
        return;
    }
    
    customSliders.forEach((slider, index) => {
        
        // Find the actual slider container
        let sliderContainer = slider.querySelector('.tns-outer') || slider;
        if (slider.classList.contains('tns-outer')) {
            sliderContainer = slider;
            slider = slider.parentElement;
        }
        
        // Create scrollbar container
        const scrollbarContainer = document.createElement('div');
        scrollbarContainer.className = 'custom-horizontal-scrollbar';
        scrollbarContainer.innerHTML = '<div class="scrollbar-track"><div class="scrollbar-thumb"></div></div>';
        
        // Insert scrollbar after slider
        slider.appendChild(scrollbarContainer);
        
        const track = scrollbarContainer.querySelector('.scrollbar-track');
        const thumb = scrollbarContainer.querySelector('.scrollbar-thumb');
        
        // Get the actual slider element - try multiple selectors
        let scrollableElement = slider.querySelector('.product-slider-container') || 
                               slider.querySelector('.tns-inner') ||
                               sliderContainer.querySelector('.tns-inner') ||
                               slider.querySelector('.product-slider');
        
        if (!scrollableElement) {
            return;
        }
        
        function updateScrollbar() {
            if (!scrollableElement || !scrollableElement.children) {
                return;
            }
            
            const totalItems = scrollableElement.children.length;
            const visibleItems = Math.floor(sliderContainer.offsetWidth / 280);
            
            // Check if navigation controls exist
            const hasNavControls = slider.querySelector('.product-slider-controls-prev') && slider.querySelector('.product-slider-controls-next');
            
            // Only show scrollbar if there are navigation controls and more items than visible
            if (!hasNavControls || totalItems <= visibleItems) {
                scrollbarContainer.style.display = 'none';
                return;
            }
            
            scrollbarContainer.style.display = 'block';
            
            const thumbWidth = Math.max(10, (visibleItems / totalItems) * 100);
            
            // Get current transform to calculate position
            const transform = scrollableElement.style.transform;
            const translateMatch = transform.match(/translate3d\((-?[\d.]+)%/);
            const currentTranslate = translateMatch ? Math.abs(parseFloat(translateMatch[1])) : 0;
            
            const maxScrollableItems = totalItems - visibleItems;
            const itemsScrolled = Math.round(currentTranslate / (100 / totalItems));
            const scrollProgress = maxScrollableItems > 0 ? Math.min(1, itemsScrolled / maxScrollableItems) : 0;
            const thumbLeft = Math.min(100 - thumbWidth, Math.max(0, scrollProgress * (100 - thumbWidth)));
            
            thumb.style.width = thumbWidth + '%';
            thumb.style.left = thumbLeft + '%';
        }
        
        // Visual scrollbar only - no interaction
        
        // Listen for slider changes
        if (scrollableElement) {
            const observer = new MutationObserver(() => {
                updateScrollbar();
            });
            
            try {
                observer.observe(scrollableElement, {
                    attributes: true,
                    attributeFilter: ['style']
                });
            } catch (e) {
                console.error('Observer setup failed for slider', index + 1, e);
            }
        }
        
        // Initial update
        setTimeout(() => {
            updateScrollbar();
        }, 100);
        
        // Update on resize
        window.addEventListener('resize', updateScrollbar);
    });
});