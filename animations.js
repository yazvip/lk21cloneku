// animations.js - Advanced Scroll Animations & Interactions

// ========================================
// INTERSECTION OBSERVER FOR SCROLL ANIMATIONS
// ========================================
function initScrollAnimations() {
    const animatedElements = document.querySelectorAll(
        '.fade-in-up, .scale-in, .slide-in-left, .slide-in-right'
    );

    if (!animatedElements.length) return;

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('visible');
                    }, index * 50);
                    observer.unobserve(entry.target);
                }
            });
        },
        {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        }
    );

    animatedElements.forEach((el) => observer.observe(el));
}

// ========================================
// PARALLAX EFFECT FOR HERO SECTION
// ========================================
function initParallax() {
    const heroBg = document.getElementById('heroBg');
    if (!heroBg) return;

    let ticking = false;

    function updateParallax() {
        const scrolled = window.pageYOffset;
        const parallaxSpeed = 0.5;

        requestAnimationFrame(() => {
            heroBg.style.transform = `translateY(${scrolled * parallaxSpeed}px) scale(1.1)`;
            ticking = false;
        });
    }

    window.addEventListener('scroll', () => {
        if (!ticking && window.pageYOffset < window.innerHeight) {
            ticking = true;
            updateParallax();
        }
    }, { passive: true });
}

// ========================================
// ENHANCED LAZY LOADING FOR IMAGES
// ========================================
function initLazyLoading() {
    const images = document.querySelectorAll('img[loading="lazy"]');

    const imageObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const img = entry.target;

                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                    }

                    img.addEventListener('load', () => {
                        img.classList.add('loaded');
                    }, { once: true });

                    imageObserver.unobserve(img);
                }
            });
        },
        {
            rootMargin: '50px'
        }
    );

    images.forEach((img) => imageObserver.observe(img));
}

// ========================================
// SMOOTH SCROLL TO TOP
// ========================================
function initScrollToTop() {
    const scrollBtn = document.createElement('button');
    scrollBtn.className = 'scroll-to-top';
    scrollBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
    scrollBtn.setAttribute('aria-label', 'Scroll to top');
    document.body.appendChild(scrollBtn);

    let isVisible = false;

    const toggleVisibility = throttle(() => {
        const shouldShow = window.pageYOffset > 500;

        if (shouldShow && !isVisible) {
            scrollBtn.classList.add('visible');
            isVisible = true;
        } else if (!shouldShow && isVisible) {
            scrollBtn.classList.remove('visible');
            isVisible = false;
        }
    }, 100);

    window.addEventListener('scroll', toggleVisibility, { passive: true });

    scrollBtn.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

// ========================================
// PAGE TRANSITION EFFECT
// ========================================
function initPageTransitions() {
    const overlay = document.createElement('div');
    overlay.className = 'page-transition';
    document.body.appendChild(overlay);

    window.addEventListener('beforeunload', () => {
        overlay.classList.add('active');
    });

    setTimeout(() => {
        overlay.classList.remove('active');
    }, 100);
}

// ========================================
// CARD TILT EFFECT (3D)
// ========================================
function initCardTilt() {
    const cards = document.querySelectorAll('.video-card');

    cards.forEach((card) => {
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            const centerX = rect.width / 2;
            const centerY = rect.height / 2;

            const rotateX = (y - centerY) / 20;
            const rotateY = (centerX - x) / 20;

            requestAnimationFrame(() => {
                card.style.transform = `
                    perspective(1000px)
                    rotateX(${rotateX}deg)
                    rotateY(${rotateY}deg)
                    scale3d(1.02, 1.02, 1.02)
                    translateY(-8px)
                `;
            });
        });

        card.addEventListener('mouseleave', () => {
            requestAnimationFrame(() => {
                card.style.transform = '';
            });
        });
    });
}

// ========================================
// INTERACTIVE CURSOR (Desktop Only)
// ========================================
function initCustomCursor() {
    if ('ontouchstart' in window) return;

    const cursor = document.createElement('div');
    cursor.className = 'custom-cursor';
    document.body.appendChild(cursor);

    const cursorFollower = document.createElement('div');
    cursorFollower.className = 'cursor-follower';
    document.body.appendChild(cursorFollower);

    let mouseX = 0, mouseY = 0;
    let followerX = 0, followerY = 0;

    document.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;

        requestAnimationFrame(() => {
            cursor.style.left = mouseX + 'px';
            cursor.style.top = mouseY + 'px';
        });
    });

    function animateFollower() {
        const dx = mouseX - followerX;
        const dy = mouseY - followerY;

        followerX += dx * 0.1;
        followerY += dy * 0.1;

        cursorFollower.style.left = followerX + 'px';
        cursorFollower.style.top = followerY + 'px';

        requestAnimationFrame(animateFollower);
    }
    animateFollower();

    const interactiveElements = document.querySelectorAll('a, button, .video-card, .ep-btn');

    interactiveElements.forEach((el) => {
        el.addEventListener('mouseenter', () => {
            cursor.classList.add('active');
            cursorFollower.classList.add('active');
        });

        el.addEventListener('mouseleave', () => {
            cursor.classList.remove('active');
            cursorFollower.classList.remove('active');
        });
    });
}

// ========================================
// PULL TO REFRESH (Mobile)
// ========================================
function initPullToRefresh() {
    if (!('ontouchstart' in window) || window.innerWidth > 768) return;

    let startY = 0;
    let pulling = false;
    const threshold = 80;

    const refreshIndicator = document.createElement('div');
    refreshIndicator.className = 'pull-refresh-indicator';
    refreshIndicator.innerHTML = '<i class="fas fa-sync-alt"></i>';
    document.body.insertBefore(refreshIndicator, document.body.firstChild);

    document.addEventListener('touchstart', (e) => {
        if (window.pageYOffset === 0) {
            startY = e.touches[0].pageY;
            pulling = true;
        }
    }, { passive: true });

    document.addEventListener('touchmove', (e) => {
        if (!pulling) return;

        const currentY = e.touches[0].pageY;
        const pullDistance = currentY - startY;

        if (pullDistance > 0) {
            refreshIndicator.style.transform = `translateY(${Math.min(pullDistance, threshold)}px)`;
            refreshIndicator.style.opacity = Math.min(pullDistance / threshold, 1);
        }
    }, { passive: true });

    document.addEventListener('touchend', () => {
        if (!pulling) return;

        const pullDistance = event.changedTouches[0].pageY - startY;

        if (pullDistance > threshold) {
            refreshIndicator.classList.add('refreshing');
            location.reload();
        } else {
            refreshIndicator.style.transform = '';
            refreshIndicator.style.opacity = '0';
        }

        pulling = false;
    });
}

// ========================================
// PERFORMANCE MONITOR (Development)
// ========================================
function initPerformanceMonitor() {
    if (window.location.hostname !== 'localhost') return;

    let fps = 0;
    let lastTime = performance.now();
    let frames = 0;

    function countFPS() {
        frames++;
        const currentTime = performance.now();

        if (currentTime >= lastTime + 1000) {
            fps = Math.round((frames * 1000) / (currentTime - lastTime));
            frames = 0;
            lastTime = currentTime;

            console.log(`FPS: ${fps}`);
        }

        requestAnimationFrame(countFPS);
    }

    requestAnimationFrame(countFPS);
}

// ========================================
// INITIALIZE ALL ANIMATIONS
// ========================================
function initAllAnimations() {
    initScrollAnimations();
    initParallax();
    initLazyLoading();
    initScrollToTop();
    initPageTransitions();

    if (window.innerWidth > 768) {
        initCardTilt();
        initCustomCursor();
    }

    initPullToRefresh();

    if (window.location.hostname === 'localhost') {
        initPerformanceMonitor();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAllAnimations);
} else {
    initAllAnimations();
}

window.reinitAnimations = initAllAnimations;
