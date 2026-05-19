const SELECTOR = '[data-hk-love-slider]';

function getStep(track) {
    const firstItem = track?.querySelector('.hk-love-slider__item');
    if (!firstItem) return track?.parentElement?.clientWidth || 0;

    const itemStyles = window.getComputedStyle(track);
    const gap = parseFloat(itemStyles.columnGap || itemStyles.gap || '0') || 0;
    return firstItem.getBoundingClientRect().width + gap;
}

function scrollByStep(track, direction) {
    const step = getStep(track);
    track.scrollBy({ left: direction * step, behavior: 'smooth' });
}

function updateButtons(track, prevBtn, nextBtn) {
    const maxScrollLeft = Math.max(0, track.scrollWidth - track.clientWidth);
    const left = track.scrollLeft;
    const epsilon = 2;

    const disablePrev = left <= epsilon;
    const disableNext = left >= (maxScrollLeft - epsilon);

    prevBtn.disabled = disablePrev;
    nextBtn.disabled = disableNext;
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll(SELECTOR).forEach((root) => {
        const track = root.querySelector('[data-hk-love-slider-track]') || root.querySelector('.hk-love-slider__track');
        const prevBtn = root.querySelector('[data-hk-love-slider-prev]');
        const nextBtn = root.querySelector('[data-hk-love-slider-next]');

        if (!track || !prevBtn || !nextBtn) return;

        const refresh = () => updateButtons(track, prevBtn, nextBtn);

        prevBtn.addEventListener('click', () => {
            scrollByStep(track, -1);
            window.setTimeout(refresh, 250);
        });

        nextBtn.addEventListener('click', () => {
            scrollByStep(track, 1);
            window.setTimeout(refresh, 250);
        });

        track.addEventListener('scroll', refresh, { passive: true });
        window.addEventListener('resize', refresh, { passive: true });
        refresh();
    });
});
