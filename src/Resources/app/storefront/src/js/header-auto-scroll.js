document.addEventListener('DOMContentLoaded', function() {
    const uspBarItems = document.querySelector('.usp-bar-items');
    
    if (!uspBarItems) return;
    
    // Only apply auto-scroll on mobile devices
    function isMobile() {
        return window.innerWidth <= 1060;
    }
    
    let scrollInterval;
    let isScrolling = false;
    
    function startAutoScroll() {
        if (!isMobile() || isScrolling) return;
        
        isScrolling = true;
        const scrollSpeed = 1;
        const pauseDuration = 1500; // 1.5 seconds pause at each end
        let direction = 1; // 1 for right, -1 for left
        let isPaused = false;
        
        scrollInterval = setInterval(() => {
            if (isPaused) return;
            
            const maxScroll = uspBarItems.scrollWidth - uspBarItems.clientWidth;
            
            uspBarItems.scrollLeft += scrollSpeed * direction;
            
            // Pause and reverse direction when reaching either end
            if (uspBarItems.scrollLeft >= maxScroll && direction === 1) {
                isPaused = true;
                setTimeout(() => {
                    direction = -1; // Start scrolling left
                    isPaused = false;
                }, pauseDuration);
            } else if (uspBarItems.scrollLeft <= 0 && direction === -1) {
                isPaused = true;
                setTimeout(() => {
                    direction = 1; // Start scrolling right
                    isPaused = false;
                }, pauseDuration);
            }
        }, 20);
    }
    
    function stopAutoScroll() {
        clearInterval(scrollInterval);
        isScrolling = false;
    }
    
    // Start auto-scroll on mobile
    if (isMobile()) {
        setTimeout(startAutoScroll, 1000);
    }
    
    // Stop auto-scroll on user interaction
    uspBarItems.addEventListener('touchstart', stopAutoScroll);
    uspBarItems.addEventListener('scroll', () => {
        if (isScrolling) return;
        stopAutoScroll();
    });
    
    // Handle resize
    window.addEventListener('resize', () => {
        stopAutoScroll();
        if (isMobile()) {
            setTimeout(startAutoScroll, 500);
        }
    });
});