import Plugin from 'src/plugin-system/plugin.class';

export default class VideoAutoplayPlugin extends Plugin {
    init() {
        this.videos = this.el.querySelectorAll('.gallery-slider-item-container video');

        if (!this.videos.length) {
            return;
        }

        // Wait until all videos are fully loaded before activating autoplay behavior
        const videoLoadPromises = Array.from(this.videos).map(video => {
            return new Promise(resolve => {
                if (video.readyState >= 4) {
                    // already loaded
                    resolve();
                } else {
                    video.addEventListener('canplaythrough', resolve, { once: true });
                }
            });
        });

        Promise.all(videoLoadPromises).then(() => {
            this._registerEvents();
        });
    }

    _registerEvents() {
        this.videos.forEach(video => {
            // Autoplay the visible video when all are ready
            if (this._isElementInViewport(video)) {
                this._playVideo(video);
            }

            // Observe when the video enters or exits the viewport
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this._playVideo(video);
                    } else {
                        this._pauseVideo(video);
                    }
                });
            }, { threshold: 0.5 });

            observer.observe(video);
        });
    }

    _playVideo(video) {
        if (video.paused) {
            video.muted = true; // browsers require mute for autoplay
            video.play().catch(err => console.warn('Autoplay blocked:', err));
        }
    }

    _pauseVideo(video) {
        if (!video.paused) {
            video.pause();
        }
    }

    _isElementInViewport(el) {
        const rect = el.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }
}
