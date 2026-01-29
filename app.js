// app.js - Optimized Ambient Theater + PJAX + Persistence

// Utility: Debounce function for performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Utility: Throttle function for scroll events
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Utility: Image Loading Handler
function handleImageLoad(img) {
    if (!img) return;

    const parent = img.parentElement;
    if (parent) {
        parent.classList.add('skeleton-img');
    }
    img.classList.add('img-loading');

    if (img.complete && img.naturalHeight !== 0) {
        onImageLoaded(img, parent);
    } else {
        img.addEventListener('load', () => onImageLoaded(img, parent), { once: true });
        img.addEventListener('error', () => {
            if (parent) parent.classList.remove('skeleton-img');
            img.classList.remove('img-loading');
        }, { once: true });
    }
}

function onImageLoaded(img, parent) {
    img.classList.remove('img-loading');
    img.classList.add('img-loaded');
    if (parent) {
        parent.classList.remove('skeleton-img');
    }
}

// Apply image loading to all images in container
function applyImageLoading(container) {
    const images = container.querySelectorAll('img');
    images.forEach(img => handleImageLoad(img));
}

let currentController = null;
let currentPage = 1;
let currentMode = 'latest';
let currentQuery = '';
let isFetching = false;
let currentPlayingSlug = ''; // Track active video for progress

document.addEventListener('DOMContentLoaded', () => {
    initApp();

    // Global Image Error Handler (Suggestion 6)
    window.addEventListener('error', function (e) {
        if (e.target.tagName === 'IMG') {
            e.target.src = 'https://via.placeholder.com/300x450?text=MovieTube';
            e.target.onerror = null; // Prevent infinite loop
        }
    }, true); // Capture phase

    // Initialize UX Enhancements
    initAutoHideNavbar();
    initAutocomplete();

    // Apply image loading to initial page images
    setTimeout(() => {
        applyImageLoading(document.body);
    }, 100);
});

// ========================================
// TOAST NOTIFICATIONS
// ========================================
function showToast(message, icon = 'fa-check-circle') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = `<i class="fas ${icon}"></i> ${message}`;
    container.appendChild(toast);

    setTimeout(() => toast.remove(), 4000);
}

function showLoadingSpinner() {
    let loaderOverlay = document.getElementById('loadingOverlay');
    if (!loaderOverlay) {
        loaderOverlay = document.createElement('div');
        loaderOverlay.id = 'loadingOverlay';
        loaderOverlay.className = 'loader-overlay';
        loaderOverlay.innerHTML = `
            <div class="loader-content">
                <div class="loader-spinner"></div>
                <p>Loading...</p>
            </div>
        `;
        document.body.appendChild(loaderOverlay);
    } else {
        loaderOverlay.style.display = 'flex';
    }
}

function hideLoadingSpinner() {
    const loaderOverlay = document.getElementById('loadingOverlay');
    if (loaderOverlay) {
        loaderOverlay.style.display = 'none';
    }
}

// ========================================
// OPTIMIZED AUTO-HIDE NAVBAR
// ========================================
function initAutoHideNavbar() {
    let lastScroll = 0;
    let ticking = false;
    const navbar = document.querySelector('.navbar');

    function updateNavbar(currentScroll) {
        if (currentScroll > lastScroll && currentScroll > 100) {
            navbar?.classList.add('hidden');
        } else {
            navbar?.classList.remove('hidden');
        }
        lastScroll = currentScroll;
        ticking = false;
    }

    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;

        if (!ticking) {
            window.requestAnimationFrame(() => updateNavbar(currentScroll));
            ticking = true;
        }
    }, { passive: true });
}

// ========================================
// SEARCH & NAVIGATION
// ========================================
let searchTimeout = null;

function hideSearchSuggestions() {
    const sug = document.getElementById('searchSuggestions');
    if (sug) sug.style.display = 'none';
}

function initAutocomplete() {
    const searchInput = document.getElementById('searchInput');
    const searchBox = document.querySelector('.search-box');

    if (!searchInput || !searchBox) return;

    // Create suggestions container
    let suggestBox = searchBox.querySelector('.search-suggestions');
    if (!suggestBox) {
        suggestBox = document.createElement('div');
        suggestBox.className = 'search-suggestions';
        searchBox.appendChild(suggestBox);
    }

    const debouncedSearch = debounce(async (query) => {
        if (query.length < 2) {
            suggestBox.classList.remove('active');
            return;
        }

        try {
            const data = await fetchContent('/api/v1/', { action: 'search', q: query, page: 1 });
            if (data.data?.length) {
                suggestBox.innerHTML = data.data.slice(0, 5).map(item => {
                    const slug = item.slug || item.url?.split('/').filter(p => p).pop();
                    const type = item.type || 'movie';
                    return `
                        <a href="/nonton/${slug}?type=${type}" class="suggestion-item">
                            <img src="${item.poster}" alt="${item.title}">
                            <div class="suggest-info">
                                <h4>${item.title}</h4>
                                <p>${item.year || ''} • ${type.toUpperCase()}</p>
                            </div>
                        </a>
                    `;
                }).join('');
                suggestBox.classList.add('active');
                applyImageLoading(suggestBox);
            } else {
                suggestBox.classList.remove('active');
            }
        } catch (e) {
            console.error('Autocomplete error:', e);
        }
    }, 300);

    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();
        debouncedSearch(query);
    });

    // Hide on click outside
    document.addEventListener('click', (e) => {
        if (!searchBox.contains(e.target)) {
            suggestBox.classList.remove('active');
        }
    });

    // Hide on focus out
    searchInput.addEventListener('blur', () => {
        setTimeout(() => suggestBox.classList.remove('active'), 200);
    });
}

// ========================================
// HERO BANNER
// ========================================
let heroData = [];
let heroIndex = 0;
let heroInterval = null;

function renderHeroCarousel(items) {
    if (!items || !items.length) return;
    heroData = items;
    heroIndex = 0;

    const heroBanner = document.getElementById('heroBanner');
    if (!heroBanner) return;

    // Create Slides Container if not exists (or reset)
    // We reuse existing structure but make it dynamic
    startHeroSlide();

    // Auto rotate
    if (heroInterval) clearInterval(heroInterval);
    heroInterval = setInterval(() => {
        heroIndex = (heroIndex + 1) % heroData.length;
        updateHeroSlide();
    }, 5000);
}

function startHeroSlide() {
    updateHeroSlide();
}

function updateHeroSlide() {
    const item = heroData[heroIndex];
    if (!item) return;

    const heroBg = document.getElementById('heroBg');
    const heroTitle = document.getElementById('heroTitle');
    const heroDesc = document.getElementById('heroDesc');
    const heroPlayBtn = document.getElementById('heroPlayBtn');
    const heroAddBtn = document.getElementById('heroAddBtn');
    const content = document.querySelector('.hero-content');

    if (!content || !heroBg) return;

    requestAnimationFrame(() => {
        content.style.opacity = '0';
        heroBg.style.opacity = '0';

        setTimeout(() => {
            requestAnimationFrame(() => {
                heroBg.style.backgroundImage = `url(${item.poster})`;
                if (heroTitle) heroTitle.textContent = item.title;
                if (heroDesc) heroDesc.textContent = item.synopsis || `Tonton ${item.title} Sub Indo gratis di MovieTube.`;

                const slug = item.slug || item.url?.split('/').filter(p => p).pop();
                const type = item.type || 'movie';

                if (heroPlayBtn) heroPlayBtn.href = `/nonton/${slug}?type=${type}`;
                if (heroAddBtn) {
                    heroAddBtn.onclick = () => {
                        const added = storage.toggleWatchlist(item);
                        showToast(added ? 'Ditambahkan ke Watchlist!' : 'Dihapus dari Watchlist', added ? 'fa-bookmark' : 'fa-times');
                    };
                }

                extractAmbientFromPoster(item.poster);

                content.style.opacity = '1';
                heroBg.style.opacity = '1';
            });
        }, 300);
    });
}

// --- PJAX ROUTER --- (unchanged) ...


// --- PJAX ROUTER ---
document.addEventListener('click', e => {
    const link = e.target.closest('a');
    if (link && link.href && link.href.startsWith(window.location.origin) && !link.getAttribute('target') && !link.getAttribute('onclick')) {
        if (link.pathname.startsWith('/nonton/') || link.pathname === '/' || link.pathname.startsWith('/genre/') || link.pathname.startsWith('/year/')) {
            e.preventDefault();
            navigateTo(link.href);
        }
    }
});

window.addEventListener('popstate', () => {
    loadPage(window.location.href, false);
});

async function navigateTo(url) {
    history.pushState(null, '', url);
    closeDrawer(); // Auto close on navigation
    await loadPage(url);
}

function closeDrawer() {
    const drawer = document.getElementById('persistenceDrawer');
    if (drawer) drawer.classList.remove('open');
}

async function loadPage(url, push = true) {
    const content = document.getElementById('main-content');
    content.style.opacity = '0.5';
    // Clear SSR data to forget previous page state
    window.initialData = null;

    try {
        if (currentController) currentController.abort();
        currentController = new AbortController();

        const response = await fetch(url, { signal: currentController.signal });
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        const newContent = doc.getElementById('main-content');
        if (newContent) {
            content.innerHTML = newContent.innerHTML;
            document.title = doc.title;
            reinitPageScripts(doc);
            window.scrollTo(0, 0);
            content.style.opacity = '1';
            hideLoadingSpinner();
        } else {
            window.location.reload();
        }
    } catch (e) {
        hideLoadingSpinner();
        if (e.name !== 'AbortError') window.location.reload();
    }
}

function initApp() {
    hideLoadingSpinner();
    if (document.querySelector('.watch-layout')) {
        initWatchPage();
    } else {
        const data = window.initialData || {};
        if (data.mode === 'genre' || data.mode === 'year') {
            searchContent(data.query, data.mode);
        } else if (data.mode === 'search' && data.query) {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) searchInput.value = data.query;
            searchContent(data.query, 'search');
        } else {
            loadHome();
        }
    }
    updateAmbientLight('#ffc107');

    // Bind Persistence Button
    const btn = document.getElementById('navPersistenceBtn');
    if (btn) btn.onclick = toggleDrawer;
    window.toggleDrawer = toggleDrawer;

    // Observe scroll for infinite scroll
    setupInfiniteScroll();
}

function setupInfiniteScroll() {
    const sentinel = document.getElementById('paginationLoading');
    if (!sentinel) return;

    const observer = new IntersectionObserver(entries => {
        if (entries[0].isIntersecting && !isFetching && currentMode !== 'none') {
            loadMoreContent();
        }
    }, {
        threshold: 0.1,
        rootMargin: '100px'
    });

    observer.observe(sentinel);
}

function reinitPageScripts(doc) {
    // Re-bind navbar buttons after PJAX swap
    const btn = document.getElementById('navPersistenceBtn');
    if (btn) btn.onclick = toggleDrawer;

    if (doc.querySelector('.watch-layout')) {
        const slug = window.location.pathname.split('/nonton/')[1]?.split('?')[0] || '';
        if (slug) initWatchPage();
    } else {
        const url = new URL(window.location.href);
        if (url.pathname.startsWith('/genre/')) {
            searchContent(url.pathname.split('/genre/')[1], 'genre');
        } else if (url.pathname.startsWith('/year/')) {
            searchContent(url.pathname.split('/year/')[1], 'year');
        } else if (url.pathname === '/search' || url.pathname === '/' && url.searchParams.has('q')) {
            const query = url.searchParams.get('q');
            if (query) {
                const searchInput = document.getElementById('searchInput');
                if (searchInput) searchInput.value = query;
                searchContent(query, 'search');
            } else {
                loadHome();
            }
        } else {
            loadHome();
        }
    }
}

// --- AMBIENT & COLOR EXTRACTION (SUGGESTION 5) ---
function updateAmbientLight(color) {
    const ambientInfo = document.getElementById('ambient-glow');
    if (ambientInfo) {
        ambientInfo.style.background = `radial-gradient(circle at center, ${color} 0%, transparent 70%)`;
    }
}

async function extractAmbientFromPoster(imgUrl) {
    if (!imgUrl) return;

    // Create hidden canvas to extract average color
    const img = new Image();
    img.crossOrigin = "Anonymous";
    img.src = imgUrl;

    img.onload = () => {
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = 1;
            canvas.height = 1;
            ctx.drawImage(img, 0, 0, 1, 1);
            const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data;
            const hex = "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
            updateAmbientLight(hex);
        } catch (e) {
            console.warn("Color extraction failed due to CORS", e);
        }
    };
}

// --- SKELETON LOADING (SUGGESTION 2) ---
function renderSkeletons(gridId, count = 12) {
    const grid = document.getElementById(gridId);
    if (!grid) return;

    grid.innerHTML = '';
    grid.innerHTML = '';
    for (let i = 0; i < count; i++) {
        const skel = document.createElement('div');
        // skel.style.padding = '10px'; // CSS grid handles gaps now
        skel.className = 'grid-item-wrapper';
        skel.innerHTML = `
            <div class="skeleton skeleton-card"></div>
            <div class="meta">
                 <div class="skeleton skeleton-text" style="width: 90%; height: 18px;"></div>
                 <div class="skeleton skeleton-text" style="width: 40%"></div>
            </div>
        `;
        grid.appendChild(skel);
    }
}

// --- PERSISTENCE LOGIC (SUGGESTION 3) ---
const storage = {
    get: (key) => JSON.parse(localStorage.getItem('mt_' + key) || (key === 'progress' ? '{}' : '[]')),
    set: (key, val) => localStorage.setItem('mt_' + key, JSON.stringify(val)),

    saveToHistory: (item) => {
        let history = storage.get('history');
        history = history.filter(h => h.slug !== item.slug); // Remove if exists
        history.unshift({ ...item, addedAt: new Date().getTime() }); // Add to top
        storage.set('history', history.slice(0, 40)); // Keep more for continue watching calculation
    },

    toggleWatchlist: (item) => {
        let list = storage.get('watchlist');
        const exists = list.some(l => l.slug === item.slug);
        if (exists) {
            list = list.filter(l => l.slug !== item.slug);
        } else {
            list.unshift({ ...item, addedAt: new Date().getTime() });
        }
        storage.set('watchlist', list);
        return !exists;
    },

    isInWatchlist: (slug) => storage.get('watchlist').some(l => l.slug === slug),

    removeItem: (type, slug) => {
        let list = storage.get(type);
        list = list.filter(item => {
            const itemSlug = item.slug || item.url?.split('/').filter(p => p).pop();
            return itemSlug !== slug;
        });
        storage.set(type, list);
        return list;
    },

    // Progress Tracking
    saveProgress: (slug, seconds, duration = 0) => {
        const progress = storage.get('progress');
        progress[slug] = {
            seconds: seconds,
            duration: duration,
            percent: duration > 0 ? Math.floor((seconds / duration) * 100) : 0,
            updatedAt: new Date().getTime()
        };
        storage.set('progress', progress);
    },
    getProgress: (slug) => {
        const progress = storage.get('progress');
        return progress[slug] || { seconds: 0, duration: 0, percent: 0 };
    }
};

// Periodic saving (limited due to iframe restrictions, but works if player posts messages)
window.addEventListener('message', (event) => {
    try {
        const msg = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
        if (msg.event === 'progress' || msg.event === 'timeupdate') {
            const slug = currentPlayingSlug || window.location.pathname.split('/nonton/')[1]?.split('?')[0];
            if (slug && msg.seconds) {
                storage.saveProgress(slug, Math.floor(msg.seconds), Math.floor(msg.duration || 0));
            }
        }
    } catch (e) { }
});

function checkResumePrompt(slug, playerIframe) {
    const progress = storage.getProgress(slug);
    const lastTime = progress.seconds;

    // Only prompt if more than 30s and less than 95% finished
    if (lastTime > 30 && (progress.percent < 95 || progress.duration === 0)) {
        const minutes = Math.floor(lastTime / 60);
        const seconds = lastTime % 60;

        const prompt = document.createElement('div');
        prompt.className = 'resume-prompt';
        prompt.innerHTML = `
            <p><i class="fas fa-play"></i> Lanjutkan menonton dari <b>${minutes}:${seconds.toString().padStart(2, '0')}</b>?</p>
            <div class="resume-actions">
                <button class="btn-sm btn-primary-sm" id="btnResume">Lanjutkan</button>
                <button class="btn-sm btn-secondary-sm" id="btnStartOver">Mulai Awal</button>
            </div>
        `;

        const wrapper = document.getElementById('videoWrapper');
        if (wrapper) {
            wrapper.appendChild(prompt);

            document.getElementById('btnResume').onclick = () => {
                const url = new URL(playerIframe.src);
                url.searchParams.set('t', lastTime);
                url.searchParams.set('start', lastTime);
                playerIframe.src = url.toString();
                prompt.remove();
                showToast(`Melanjutkan dari ${minutes}:${seconds.toString().padStart(2, '0')}`, 'fa-play');
            };

            document.getElementById('btnStartOver').onclick = () => {
                storage.saveProgress(slug, 0, progress.duration);
                prompt.remove();
            };

            // Auto-hide prompt after 15s
            setTimeout(() => { if (prompt.parentNode) prompt.remove(); }, 15000);
        }
    }
}

function toggleDrawer() {
    const drawer = document.getElementById('persistenceDrawer');
    if (drawer) {
        drawer.classList.toggle('open');
        if (drawer.classList.contains('open')) renderPersistenceSections();
    }
}

// Close drawer when clicking outside
document.addEventListener('mousedown', e => {
    const drawer = document.getElementById('persistenceDrawer');
    const btn = document.getElementById('navPersistenceBtn'); // Only persistence btn logic
    if (drawer && drawer.classList.contains('open') && !drawer.contains(e.target) && !btn.contains(e.target)) {
        closeDrawer();
    }

    // Close mobile search if clicking outside
    const searchBox = document.querySelector('.search-box');
    const searchBtn = document.querySelector('a[onclick*="toggleMobileSearch"]'); // Heuristic to find the button
    if (searchBox && searchBox.classList.contains('active') && !searchBox.contains(e.target)) {
        // Check if click was on the toggle button itself (to avoid immediate re-opening)
        // In this specific case, the onclick returns false, so mousedown check might be tricky if not careful,
        // but basic outside click check helps.
        // Better: Let the toggle function handle the button click, just close here if not button.
        // We won't check against button here because the button has its own handler.
        // However, if we click the button, this runs too.
        // Simple fix: rely on the toggle function for the button.
        // If we click OUTSIDE the searchbox, close it.
        if (searchBtn && searchBtn.contains(e.target)) return;
        searchBox.classList.remove('active');
    }
});

window.toggleMobileSearch = function () {
    const searchBox = document.querySelector('.search-box');
    if (searchBox) {
        searchBox.classList.toggle('active');
        if (searchBox.classList.contains('active')) {
            setTimeout(() => document.getElementById('searchInput')?.focus(), 100);
        }
    }
};

function renderPersistenceSections() {
    const watchlist = storage.get('watchlist');
    const history = storage.get('history');
    const container = document.getElementById('drawerContent');
    if (!container) return;

    if (!watchlist.length && !history.length) {
        container.innerHTML = '<p style="text-align:center; padding: 40px; color: var(--text-secondary);">Your library is empty.</p>';
        return;
    }

    container.innerHTML = '';

    if (watchlist.length) {
        const title = document.createElement('h4');
        title.innerHTML = '<i class="fas fa-bookmark"></i> My Watchlist';
        title.style.margin = '0 0 15px 0';
        container.appendChild(title);

        const list = document.createElement('div');
        container.appendChild(list);
        renderGridItems(watchlist, list, false, 'watchlist');
    }

    if (history.length) {
        const titleWrapper = document.createElement('div');
        titleWrapper.style.cssText = 'display:flex; justify-content:space-between; align-items:center; margin:25px 0 15px 0;';

        const title = document.createElement('h4');
        title.innerHTML = '<i class="fas fa-history"></i> Recent History';
        title.style.margin = '0';

        const clearBtn = document.createElement('button');
        clearBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Hapus Semua';
        clearBtn.style.cssText = 'background:transparent; border:1px solid #555; color:#aaa; padding:5px 10px; border-radius:6px; cursor:pointer; font-size:12px; transition:all 0.2s;';
        clearBtn.onmouseenter = () => { clearBtn.style.borderColor = '#e50914'; clearBtn.style.color = '#e50914'; };
        clearBtn.onmouseleave = () => { clearBtn.style.borderColor = '#555'; clearBtn.style.color = '#aaa'; };
        clearBtn.onclick = () => {
            if (confirm('Hapus semua history?')) {
                storage.set('history', []);
                renderPersistenceSections();
                showToast('History dihapus!', 'fa-trash-alt');
            }
        };

        titleWrapper.appendChild(title);
        titleWrapper.appendChild(clearBtn);
        container.appendChild(titleWrapper);

        const list = document.createElement('div');
        container.appendChild(list);
        renderGridItems(history, list, false, 'history');
    }
}

// Updated renderGridItems to handle direct element or ID
function renderGridItems(items, target, append = false, canDeleteType = null) {
    const grid = typeof target === 'string' ? document.getElementById(target) : target;
    if (!grid) return;
    if (!append) grid.innerHTML = '';

    const fragment = document.createDocumentFragment();

    items.forEach(item => {
        const container = document.createElement('div');
        container.className = 'grid-item-wrapper';
        container.style.position = 'relative';

        const card = document.createElement('a');
        card.className = 'video-card';

        const type = item.type || (item.title?.includes('Season') ? 'series' : 'movie');
        const isSeries = type === 'series' || type === 'tv';
        const slug = item.slug || item.url?.split('/').filter(p => p).pop();

        let timeStr = "";
        if (item.addedAt) {
            const d = new Date(item.addedAt);
            const mo = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            timeStr = `<p style="font-size:11px; color:var(--accent); margin: 4px 0;"><i class="far fa-clock"></i> ${d.getDate()} ${mo[d.getMonth()]} - ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}</p>`;
        }

        // Progress Bar for Continue Watching
        let progressHTML = "";
        let resumeOverlay = "";
        const progress = storage.getProgress(slug);
        if (progress.percent > 0 && progress.percent < 95) {
            progressHTML = `
                <div class="progress-container">
                    <div class="progress-bar" style="width: ${progress.percent}%"></div>
                </div>
            `;
            resumeOverlay = `
                <div class="resume-overlay">
                    <i class="fas fa-play"></i>
                </div>
            `;
        }

        card.href = `/nonton/${slug}?type=${type}`;

        card.addEventListener('click', (e) => {
            showLoadingSpinner();
        });

        let poster = item.poster;
        if (poster && !poster.startsWith('http') && !poster.startsWith('/')) {
            poster = '/' + poster;
        }
        if (!poster) poster = 'https://via.placeholder.com/300x450?text=No+Poster';

        card.innerHTML = `
            <div class="thumbnail">
                <img src="${poster}" alt="${item.title}" loading="lazy" onerror="this.src='https://via.placeholder.com/300x450?text=Error'">
                <span class="badge-type ${isSeries ? 'badge-series' : 'badge-movie'}">${isSeries ? 'SERIES' : 'MOVIE'}</span>
                ${resumeOverlay}
                ${progressHTML}
                <div class="card-meta-overlay">
                    <h3 class="video-title">${item.title}</h3>
                    <p class="video-stats">${item.year || ''}</p>
                </div>
            </div>
        `;

        if (canDeleteType) {
            const delBtn = document.createElement('button');
            delBtn.className = 'delete-lib-btn';
            delBtn.innerHTML = '<i class="fas fa-times"></i>';
            delBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (confirm('Hapus dari ' + (canDeleteType === 'history' ? 'Riwayat' : 'Watchlist') + '?')) {
                    storage.removeItem(canDeleteType, slug);
                    if (canDeleteType === 'history') {
                        // Also clear progress if history is removed? Maybe not.
                        renderContinueWatching();
                    }
                    renderPersistenceSections(); // Refresh UI
                }
            };
            container.appendChild(delBtn);
        }

        container.appendChild(card);
        fragment.appendChild(container);
    });

    grid.appendChild(fragment);
    applyImageLoading(grid);
}

function renderContinueWatching() {
    const history = storage.get('history');
    const container = document.getElementById('section-continue');
    const grid = document.getElementById('grid-continue');
    if (!container || !grid) return;

    const continueItems = history.filter(item => {
        const slug = item.slug || item.url?.split('/').filter(p => p).pop();
        const progress = storage.getProgress(slug);
        // Show if watched > 30s and < 95%
        return progress.seconds > 30 && progress.percent < 95;
    }).slice(0, 10);

    if (continueItems.length > 0) {
        container.style.display = 'block';
        renderGridItems(continueItems, grid);
    } else {
        container.style.display = 'none';
        grid.innerHTML = '';
    }
}

async function loadHome() {
    currentMode = 'latest';
    currentQuery = '';
    currentPage = 1;

    // Toggle Views
    const homeSec = document.getElementById('homeSections');
    const videoGrid = document.getElementById('videoGrid');
    const header = document.getElementById('gridHeader');

    if (homeSec) homeSec.style.display = 'block';
    if (videoGrid) videoGrid.style.display = 'none';
    if (header) header.style.display = 'none';

    // Set Loading Skeletons
    renderSkeletons('grid-latest', 6);
    renderSkeletons('grid-top-series', 6);
    renderSkeletons('grid-new-series', 6);
    renderSkeletons('grid-popular', 6);

    const sections = ['latest', 'top_series', 'new_series', 'popular'];

    // Fetch all sections concurrently
    sections.forEach(sec => {
        // Fix ID mapping: top_series -> top-series
        const elementId = 'grid-' + sec.replace(/_/g, '-');

        fetchContent('/api/v1/', { action: 'home_section', section: sec })
            .then(res => {
                const container = document.getElementById(elementId);
                if (!container) {
                    console.warn(`Container not found: ${elementId}`);
                    return;
                }

                // Check if we have data (even if error flag is set)
                const hasData = res.data && Array.isArray(res.data) && res.data.length > 0;

                if (!hasData) {
                    console.warn(`No data for ${sec}:`, res.error || 'unknown');
                    container.innerHTML = `<div style="padding: 20px; text-align: center; color: #999;"><i class="fas fa-info-circle"></i><p>Tidak ada data untuk ${sec}</p></div>`;
                    return;
                }

                try {
                    // Update Hero with first item of Latest if sec is latest
                    if (sec === 'latest' && res.data.length) {
                        renderHeroCarousel(res.data.slice(0, 5));
                    }
                    // Render Grid
                    renderGridItems(res.data, container);
                    console.log(`✓ Rendered ${res.data.length} items for ${sec}`);
                } catch (renderError) {
                    console.error(`Render error for ${sec}:`, renderError);
                    container.innerHTML = '<p class="text-muted">Gagal render data.</p>';
                }
            })
            .catch(err => {
                console.error(`Fetch error for ${sec}:`, err);
                const container = document.getElementById(elementId);
                if (container) {
                    container.innerHTML = '<p class="text-muted">Gagal memuat.</p>';
                }
            });
    });

    // Sidebar Trending - Fetch Popular
    fetchContent('/api/v1/', { action: 'home_section', section: 'popular' })
        .then(res => {
            const trendingList = document.getElementById('trendingList');
            if (!trendingList) return;

            const hasData = res.data && Array.isArray(res.data) && res.data.length > 0;

            if (!hasData) {
                console.warn('Trending:', res.error || 'No data');
                trendingList.innerHTML = '<p style="color: #999; font-size: 13px;">Tidak ada trending</p>';
                return;
            }

            try {
                trendingList.innerHTML = '';
                // Render top 10 for sidebar
                renderSidebarItems(res.data.slice(0, 10), trendingList);
                console.log(`✓ Rendered ${Math.min(10, res.data.length)} trending items`);
            } catch (err) {
                console.error('Sidebar render error:', err);
                trendingList.innerHTML = '<p style="color: #999; font-size: 13px;">Gagal render</p>';
            }
        })
        .catch(e => {
            console.error('Sidebar load failed:', e);
            const trendingList = document.getElementById('trendingList');
            if (trendingList) {
                trendingList.innerHTML = '<p style="color: #999; font-size: 13px;">Gagal memuat</p>';
            }
        });

    // Render Continue Watching
    renderContinueWatching();
}

function showSearchResultsView() {
    const homeSec = document.getElementById('homeSections');
    const videoGrid = document.getElementById('videoGrid');
    if (homeSec) homeSec.style.display = 'none';
    if (videoGrid) {
        videoGrid.style.display = 'grid';
        videoGrid.innerHTML = ''; // Clear previous
    }
}

function updateGridTitle(title) {
    const header = document.getElementById('gridHeader');
    if (header) {
        if (title) {
            header.innerHTML = `<h1 class="page-title">${title}</h1>`;
            header.style.display = 'block';
        } else {
            header.style.display = 'none';
            header.innerHTML = '';
        }
    }
}

function renderSidebarItems(items, container) {
    const fragment = document.createDocumentFragment();

    items.forEach((item, idx) => {
        const type = item.type || 'movie';
        const slug = item.slug || item.url?.split('/').filter(p => p).pop();

        const card = document.createElement('a');
        card.className = 'sidebar-item';
        card.href = `/nonton/${slug}?type=${type}`;
        card.innerHTML = `
            <span class="sidebar-rank">${idx + 1}</span>
            <img src="${item.poster}" alt="${item.title}" loading="lazy">
            <div class="sidebar-meta">
                <h4>${item.title}</h4>
                <p>${item.year || ''}</p>
            </div>
        `;
        fragment.appendChild(card);
    });

    container.appendChild(fragment);
    applyImageLoading(container);
}

function renderSection(container, title, items, id) {
    const sec = document.createElement('div');
    sec.innerHTML = `
        <div class="section-title"><span>${title}</span></div>
        <div class="horizontal-scroll" id="${id}"></div>
    `;
    container.appendChild(sec);
    renderGridItems(items, id);
}

async function searchContent(query, mode = 'search') {
    showSearchResultsView(); // Toggle View
    currentMode = mode;
    currentQuery = query;
    currentPage = 1;
    renderSkeletons('videoGrid');

    document.querySelectorAll('.chip').forEach(c => {
        c.classList.remove('active');
        if (c.textContent.toLowerCase() === query.toLowerCase()) c.classList.add('active');
    });

    const title = `Search: ${query}`;
    document.title = title;
    updateGridTitle(title);

    // grid.innerHTML = `<h1 class="page-title">Search: "${query}"</h1><div class="loader"></div>`; 
    // ^ Removed old logic
    const grid = document.getElementById('videoGrid');
    if (grid) grid.innerHTML = '<div class="loader"></div>';

    try {
        const data = await fetchContent('/api/v1/', { action: 'search', q: query, page: 1 });
        if (!data.error && data.data?.length) {
            renderGridItems(data.data, 'videoGrid');
        } else {
            document.getElementById('videoGrid').innerHTML = '<p style="text-align:center;">No results found.</p>';
        }
    } catch (e) {
        console.error(e);
    }
}

// Browse by Genre
let currentGenre = '';
let currentGenrePage = 1;

async function browseGenre(genre, page = 1) {
    showSearchResultsView(); // Toggle View
    currentMode = 'genre';
    currentGenre = genre;
    currentGenrePage = page;

    // Update active chip
    document.querySelectorAll('.filter-chips .chip').forEach(c => c.classList.remove('active'));
    event?.target?.classList.add('active');

    const grid = document.getElementById('videoGrid');
    if (page === 1) {
        const title = `Genre: ${genre.charAt(0).toUpperCase() + genre.slice(1)}`;
        document.title = title;
        updateGridTitle(title);
        grid.innerHTML = '<div class="loader"></div>';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    try {
        // Fetch genre data and trending in parallel
        const [genreData, trendingData] = await Promise.all([
            fetchContent('/api/v1/', { action: 'genre', genre: genre, page: page }),
            page === 1 ? fetchContent('/api/v1/', { action: 'search', q: '2025', page: 1 }) : null
        ]);

        // Update Trending Sidebar (only on first page load)
        if (page === 1 && trendingData?.data?.length) {
            const trendingList = document.getElementById('trendingList');
            if (trendingList) {
                trendingList.innerHTML = '';
                renderSidebarItems(trendingData.data.slice(0, 8), trendingList);
            }
            // Also update hero banner
            renderHeroCarousel(trendingData.data.slice(0, 5));
        }

        if (!genreData.error && genreData.data?.length) {
            if (page === 1) {
                grid.innerHTML = '';
            }
            renderGridItems(genreData.data, 'videoGrid', page > 1);

            // Update pagination info
            const sentinel = document.getElementById('paginationLoading');
            if (sentinel) {
                if (page < genreData.total_pages) {
                    sentinel.innerHTML = '<div class="loader"></div>';
                    sentinel.style.display = 'block';
                } else {
                    sentinel.innerHTML = '<p style="color:#666; font-size:12px;">Sudah mencapai akhir daftar.</p>';
                }
            }
        } else {
            if (page === 1) {
                grid.innerHTML = '<p style="text-align:center;">Tidak ada film di genre ini.</p>';
            }
        }
    } catch (e) {
        console.error(e);
        if (page === 1) {
            grid.innerHTML = '<p style="text-align:center;">Gagal memuat data.</p>';
        }
    }
}

// Browse by Type (Series/Movie)
let currentType = '';
let currentTypePage = 1;

async function browseType(type, page = 1) {
    showSearchResultsView(); // Toggle View
    currentMode = 'type';
    currentType = type;
    currentTypePage = page;

    // Update active chip in header if any
    document.querySelectorAll('.nav-right .chip').forEach(c => {
        if (c.textContent.toLowerCase().includes(type)) c.classList.add('active');
        else c.classList.remove('active');
    });

    const grid = document.getElementById('videoGrid');
    if (page === 1) {
        const title = type === 'movie' ? 'Movies' : 'TV Series';
        document.title = `Top ${title}`;
        updateGridTitle(title);
        grid.innerHTML = '<div class="loader"></div>';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    try {
        // Parallel fetch for type data and trending
        const [typeData, trendingData] = await Promise.all([
            fetchContent('/api/v1/', { action: 'type', type: type, page: page }),
            page === 1 ? fetchContent('/api/v1/', { action: 'search', q: '2025', page: 1 }) : null
        ]);

        // Update Sidebar/Hero if first page
        if (page === 1 && trendingData?.data?.length) {
            const trendingList = document.getElementById('trendingList');
            if (trendingList) {
                trendingList.innerHTML = '';
                renderSidebarItems(trendingData.data.slice(0, 8), trendingList);
            }
            renderHeroBanner(trendingData.data[0]);
        }

        if (!typeData.error && typeData.data?.length) {
            if (page === 1) grid.innerHTML = '';
            renderGridItems(typeData.data, 'videoGrid', page > 1);

            const sentinel = document.getElementById('paginationLoading');
            if (sentinel) {
                if (page < typeData.total_pages) {
                    sentinel.innerHTML = '<div class="loader"></div>';
                    sentinel.style.display = 'block';
                } else {
                    sentinel.innerHTML = '<p style="color:#666; font-size:12px;">Sudah mencapai akhir daftar.</p>';
                    currentMode = 'none';
                }
            }
        } else {
            if (page === 1) grid.innerHTML = `<p style="text-align:center; padding:50px;">Tidak ada ${type} ditemukan.</p>`;
        }
    } catch (e) {
        console.error(e);
        if (page === 1) {
            updateGridTitle('Error');
            grid.innerHTML = '<p style="text-align:center;">Gagal memuat data.</p>';
        }
    }
}

// Browse by Country
let currentCountry = '';
let currentCountryPage = 1;

async function browseCountry(country, page = 1) {
    showSearchResultsView(); // Toggle View
    currentMode = 'country';
    currentCountry = country;
    currentCountryPage = page;

    // Deactivate other chips/nav items
    document.querySelectorAll('.chip, .nav-right .chip').forEach(c => c.classList.remove('active'));

    const grid = document.getElementById('videoGrid');
    if (page === 1) {
        const title = `Negara: ${country.charAt(0).toUpperCase() + country.slice(1)}`;
        document.title = title;
        updateGridTitle(title);
        grid.innerHTML = '<div class="loader"></div>';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    try {
        // Parallel fetch for country data and trending
        const [countryData, trendingData] = await Promise.all([
            fetchContent('/api/v1/', { action: 'country', country: country, page: page }),
            page === 1 ? fetchContent('/api/v1/', { action: 'search', q: '2025', page: 1 }) : null
        ]);

        // Update Sidebar/Hero if first page
        if (page === 1 && trendingData?.data?.length) {
            const trendingList = document.getElementById('trendingList');
            if (trendingList) {
                trendingList.innerHTML = '';
                renderSidebarItems(trendingData.data.slice(0, 8), trendingList);
            }
            renderHeroBanner(trendingData.data[0]);
        }

        if (!countryData.error && countryData.data?.length) {
            if (page === 1) grid.innerHTML = '';
            renderGridItems(countryData.data, 'videoGrid', page > 1);

            const sentinel = document.getElementById('paginationLoading');
            if (sentinel) {
                if (page < countryData.total_pages) {
                    sentinel.innerHTML = '<div class="loader"></div>';
                    sentinel.style.display = 'block';
                } else {
                    sentinel.innerHTML = '<p style="color:#666; font-size:12px;">Sudah mencapai akhir daftar.</p>';
                    currentMode = 'none';
                }
            }
        } else {
            if (page === 1) grid.innerHTML = `<p style="text-align:center; padding:50px;">Tidak ada film dari ${country} ditemukan.</p>`;
        }
    } catch (e) {
        console.error(e);
        if (page === 1) grid.innerHTML = '<p style="text-align:center;">Gagal memuat data.</p>';
    }
}


async function loadMoreContent() {
    if (isFetching) return;

    // Handle Country pagination
    if (currentMode === 'country' && currentCountry) {
        isFetching = true;
        currentCountryPage++;

        const sentinel = document.getElementById('paginationLoading');
        if (sentinel) sentinel.style.display = 'block';

        try {
            const data = await fetchContent('/api/v1/', { action: 'country', country: currentCountry, page: currentCountryPage });
            if (!data.error && data.data?.length) {
                renderGridItems(data.data, 'videoGrid', true);
                if (currentCountryPage >= data.total_pages) {
                    if (sentinel) sentinel.innerHTML = '<p style="color:#666; font-size:12px;">Sudah mencapai akhir daftar.</p>';
                    currentMode = 'none';
                }
            } else {
                if (sentinel) sentinel.innerHTML = '<p style="color:#666; font-size:12px;">Sudah mencapai akhir daftar.</p>';
                currentMode = 'none';
            }
        } catch (e) {
            console.error("Country scroll error:", e);
        } finally {
            isFetching = false;
        }
        return;
    }

    // Handle Type pagination
    if (currentMode === 'type' && currentType) {
        isFetching = true;
        currentTypePage++;

        const sentinel = document.getElementById('paginationLoading');
        if (sentinel) sentinel.style.display = 'block';

        try {
            const data = await fetchContent('/api/v1/', { action: 'type', type: currentType, page: currentTypePage });
            if (!data.error && data.data?.length) {
                renderGridItems(data.data, 'videoGrid', true);
                if (currentTypePage >= data.total_pages) {
                    if (sentinel) sentinel.innerHTML = '<p style="color:#666; font-size:12px;">Sudah mencapai akhir daftar.</p>';
                    currentMode = 'none';
                }
            } else {
                if (sentinel) sentinel.innerHTML = '<p style="color:#666; font-size:12px;">Sudah mencapai akhir daftar.</p>';
                currentMode = 'none';
            }
        } catch (e) {
            console.error("Type scroll error:", e);
        } finally {
            isFetching = false;
        }
        return;
    }

    // Handle Genre pagination
    if (currentMode === 'genre' && currentGenre) {
        isFetching = true;
        currentGenrePage++;

        const sentinel = document.getElementById('paginationLoading');
        if (sentinel) sentinel.style.display = 'block';

        try {
            const data = await fetchContent('/api/v1/', { action: 'genre', genre: currentGenre, page: currentGenrePage });
            if (!data.error && data.data?.length) {
                renderGridItems(data.data, 'videoGrid', true);
                if (currentGenrePage >= data.total_pages) {
                    if (sentinel) sentinel.innerHTML = '<p style="color:#666; font-size:12px;">Sudah mencapai akhir daftar.</p>';
                    currentMode = 'none';
                }
            } else {
                if (sentinel) sentinel.innerHTML = '<p style="color:#666; font-size:12px;">Sudah mencapai akhir daftar.</p>';
                currentMode = 'none';
            }
        } catch (e) {
            console.error("Genre scroll error:", e);
        } finally {
            isFetching = false;
        }
        return;
    }

    // Handle Search pagination
    if (!currentQuery) return;
    isFetching = true;
    currentPage++;

    const sentinel = document.getElementById('paginationLoading');
    if (sentinel) sentinel.style.display = 'block';

    try {
        const data = await fetchContent('/api/v1/', { action: 'search', q: currentQuery, page: currentPage });
        if (!data.error && data.data?.length) {
            renderGridItems(data.data, 'videoGrid', true);
        } else {
            if (sentinel) sentinel.innerHTML = '<p style="color:#666; font-size:12px;">Sudah mencapai akhir daftar.</p>';
            currentMode = 'none'; // Stop observing
        }
    } catch (e) {
        console.error("Infinite scroll error:", e);
    } finally {
        isFetching = false;
        // Optionally hide sentinel if data was empty after a timeout
        if (currentMode === 'none') setTimeout(() => { if (sentinel) sentinel.style.display = 'none'; }, 3000);
    }
}

function handleSearch() {
    const val = document.getElementById('searchInput').value;
    if (val) navigateTo('/search?q=' + encodeURIComponent(val));
    hideSearchSuggestions();
}

function hideSearchSuggestions() {
    const sug = document.getElementById('searchSuggestions');
    if (sug) sug.style.display = 'none';
}

// Global click handler to close suggestions
document.addEventListener('click', (e) => {
    if (!e.target.closest('.search-box')) hideSearchSuggestions();
});

// Real-time search suggestions
// searchTimeout is already declared above
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const suggestions = document.getElementById('searchSuggestions');

    if (searchInput && suggestions) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            clearTimeout(searchTimeout);

            if (query.length < 2) {
                suggestions.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(async () => {
                try {
                    const res = await fetchContent('/api/v1/', { action: 'search_suggestion', q: query });
                    if (res.data && res.data.length > 0) {
                        suggestions.innerHTML = res.data.map(item => `
                            <a href="/nonton/${item.slug}?type=${item.type}" class="suggestion-item" onclick="navigateTo('/nonton/${item.slug}?type=${item.type}'); hideSearchSuggestions(); return false;">
                                <img src="${item.poster}" alt="">
                                <div class="info">
                                    <div class="title">${item.title}</div>
                                    <div class="meta">${item.year} • ${item.type.toUpperCase()}</div>
                                </div>
                            </a>
                        `).join('');
                        suggestions.style.display = 'block';
                        applyImageLoading(suggestions);
                    } else {
                        suggestions.style.display = 'none';
                    }
                } catch (err) {
                    console.error('Suggestion error:', err);
                }
            }, 300);
        });
    }
});

async function fetchContent(endpoint, params = {}) {
    try {
        const url = new URL(endpoint, window.location.origin);
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));

        const res = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });

        if (!res.ok) {
            console.error(`API Error: ${res.status} ${res.statusText}`, url.toString());
            // Try fallback with demo data if HTTP error
            if (params.section && params.action === 'home_section' && !params.demo) {
                console.log('HTTP error, attempting demo fallback...');
                return await fetchContent(endpoint, { ...params, demo: '1' });
            }
            return { error: `HTTP ${res.status}`, data: [] };
        }

        const data = await res.json();

        if (data.error) {
            console.warn('API warning:', data.error, 'URL:', url.toString());
            // Return data even with error flag
            return { ...data, data: data.data || [] };
        }

        return data || { error: 'No data', data: [] };
    } catch (error) {
        console.error('Fetch error:', error, 'Endpoint:', endpoint);
        // Network error - try demo fallback
        if (params.section && params.action === 'home_section' && !params.demo) {
            console.log('Network error, trying demo fallback...');
            return await fetchContent(endpoint, { ...params, demo: '1' });
        }
        return { error: error.message, data: [] };
    }
}

// --- WATCH LOGIC ---
async function initWatchPage() {
    const urlParams = new URLSearchParams(window.location.search);
    const slug = window.location.pathname.split('/nonton/')[1]?.split('?')[0];
    const type = urlParams.get('type') || 'movie';
    if (!slug) return;

    // Use Hydrated Data from Server (SSR) if available
    // This avoids double fetching and improves SEO/UX
    if (window.initialData && typeof window.initialData === 'object' && window.initialData.title) {
        console.log('Using SSR Data');
        renderWatchDetails(window.initialData);
        storage.saveToHistory({ ...window.initialData, slug, type });
        currentPlayingSlug = slug;
        // Clear SSR data after first use to prevent stale data during PJAX navigation
        window.initialData = null;
        return;
    }

    // Fallback to CSR (Client Side Rendering)
    try {
        const response = await fetch(`/api/v1/?action=detail&page=${encodeURIComponent(slug)}&type=${type}`);
        const result = await response.json();
        if (result.data) {
            renderWatchDetails(result.data);
            storage.saveToHistory({ ...result.data, slug, type });
            currentPlayingSlug = slug;
        }
    } catch (e) { console.error(e); }
}

async function renderWatchDetails(data) {
    document.title = "Nonton " + data.title;
    const titleEl = document.querySelector('.video-title');
    if (titleEl) titleEl.textContent = data.title;

    if (data.poster) extractAmbientFromPoster(data.poster);

    // Watchlist Button
    const infoSec = document.querySelector('.watch-info');
    if (infoSec) {
        let btn = document.getElementById('watchlistBtn');
        if (!btn) {
            btn = document.createElement('button');
            btn.id = 'watchlistBtn';
            btn.className = 'btn-outline';
            titleEl.after(btn);
        }
        const updateBtn = () => {
            const inList = storage.isInWatchlist(data.url.split('/').filter(p => p).pop());
            btn.innerHTML = inList ? '<i class="fas fa-check"></i> In Watchlist' : '<i class="fas fa-plus"></i> Add to List';
            btn.className = inList ? 'btn-outline active' : 'btn-outline';
        };
        updateBtn();
        btn.onclick = () => { storage.toggleWatchlist(data); updateBtn(); };
    }
    const slug = data.url.split('/').filter(p => p).pop();

    // Synopsis & Tags logic
    const synEl = document.getElementById('fullSynopsis');
    if (synEl) synEl.textContent = data.synopsis;

    const tagEl = document.getElementById('videoTags');
    if (tagEl) {
        let html = '';
        // Add Year as a clickable tag if available
        if (data.year) {
            html += `<a href="/year/${data.year}" class="tag year-tag"><i class="fas fa-calendar-alt"></i> ${data.year}</a>`;
        }
        // Add Genres
        if (data.tags && data.tags.length) {
            html += data.tags.map(t => `<a href="/genre/${encodeURIComponent(t)}" class="tag">${t}</a>`).join('');
        }
        tagEl.innerHTML = html;
    }

    const player = document.getElementById('mainPlayer');
    const wrapper = document.getElementById('videoWrapper');
    const loader = wrapper ? wrapper.querySelector('.loader') : null;
    const placeholderText = wrapper ? wrapper.querySelector('p') : null;

    if (data.type === 'series' || data.type === 'tv') {
        // Handle Trailer / Player State
        if (data.trailer && player) {
            player.src = data.trailer;
            player.style.display = 'block';
            if (loader) loader.style.display = 'none';
            if (placeholderText) placeholderText.style.display = 'none';
        } else {
            // No Trailer: Show prompt
            if (player) player.style.display = 'none';
            if (loader) loader.style.display = 'none';
            if (placeholderText) {
                placeholderText.innerHTML = '<i class="fas fa-arrow-down"></i> Silakan pilih episode di bawah';
                placeholderText.style.display = 'block';
                placeholderText.style.color = '#fff';
            }
        }

        // Add User Guidance Hint
        const existingHint = document.getElementById('seriesHint');
        if (!existingHint && wrapper) {
            const hint = document.createElement('div');
            hint.id = 'seriesHint';
            hint.style.cssText = 'background: rgba(229, 9, 20, 0.1); border-left: 3px solid var(--accent); padding: 12px; margin-top: 10px; border-radius: 4px; font-size: 14px; color: #ddd; display: flex; align-items: center; gap: 10px;';
            hint.innerHTML = '<i class="fas fa-info-circle"></i> <span><strong>Trailer Mode:</strong> Silakan pilih episode di bawah untuk mulai menonton Series ini.</span>';
            wrapper.after(hint);
        }

        // Ep Grid
        const epSec = document.getElementById('episodeSection');
        const grid = document.getElementById('episodeGrid');

        console.log("Episodes data:", data.episodes); // Audit Log

        if (epSec && grid) {
            epSec.style.display = 'block';
            if (data.episodes && data.episodes.length > 0) {
                // Group by Season
                const seasons = {};
                data.episodes.forEach(ep => {
                    const s = ep.season || 'Unknown';
                    if (!seasons[s]) seasons[s] = [];
                    seasons[s].push(ep);
                });

                let html = '';
                // Sort seasons if possible (assuming numeric or comparable)
                Object.keys(seasons).sort().forEach(sKey => {
                    const eps = seasons[sKey];
                    // Only show header if we have valid season names other than 'Unknown' or if multiple groups
                    if (Object.keys(seasons).length > 1 || sKey !== 'Unknown') {
                        html += `<h4 style="width:100%; margin:15px 0 10px; color:#aaa; font-size:14px; border-bottom:1px solid #333; padding-bottom:5px;">Season ${sKey}</h4>`;
                    }
                    html += eps.map(ep =>
                        `<div class="ep-btn" data-slug="${ep.url}" onclick="playEpisode('${ep.url}', 'series', '${ep.title || 'Episode ' + ep.number}')">${ep.number}</div>`
                    ).join('');
                });

                grid.innerHTML = html;

                // Highlight current if slug matches
                const currentSlug = window.location.pathname.split('/nonton/')[1]?.split('?')[0];
                const activeBtn = grid.querySelector(`[data-slug="${currentSlug}"]`);
                if (activeBtn) activeBtn.classList.add('active');
            } else {
                grid.innerHTML = '<p class="text-muted">Belum ada episode tersedia.</p>';
            }
        }
    } else {
        const h = document.getElementById('seriesHint');
        if (h) h.remove();

        const epSec = document.getElementById('episodeSection');
        if (epSec) epSec.style.display = 'none';

        const res = await fetch(`/api/v1/?action=get_token&id=${encodeURIComponent(data.url)}&type=movie`);
        const tokenData = await res.json();
        if (tokenData.stream_url && player) {
            player.src = tokenData.stream_url;
            player.style.display = 'block';
            if (loader) loader.style.display = 'none';
            // Track progress
            currentPlayingSlug = slug;
        }
    }

    // --- UP NEXT LOGIC (Optimized) ---
    const container = document.getElementById('relatedVideos');
    if (container) {
        if (data.related && data.related.length > 0) {
            console.log('Using Scraped Related Content');
            renderRelatedItems(data.related, container);
        } else {
            console.log('Falling back to Tag Search for Related Content');
            loadRelated(data.tags);
        }
    }
}

async function playEpisode(url, type, title) {
    const player = document.getElementById('mainPlayer');
    const titleEl = document.querySelector('.video-title');

    if (player) player.style.opacity = '0.5';
    if (titleEl && title) titleEl.textContent = title;

    // Highlight active button
    document.querySelectorAll('.ep-btn').forEach(btn => {
        if (btn.getAttribute('data-slug') === url) btn.classList.add('active');
        else btn.classList.remove('active');
    });

    try {
        const res = await fetch(`/api/v1/?action=get_token&id=${encodeURIComponent(url)}&type=${type}`);
        const data = await res.json();
        if (data.stream_url && player) {
            player.src = data.stream_url;
            player.style.opacity = '1';
            player.style.display = 'block';
            const wrapper = document.getElementById('videoWrapper');
            if (wrapper) wrapper.scrollIntoView({ behavior: 'smooth' });
            const loader = wrapper ? wrapper.querySelector('.loader') : null;
            if (loader) loader.style.display = 'none';

            // Track Episode Progress
            currentPlayingSlug = url;
            // Resume prompt is now handled internally by player.php
        }
    } catch (e) {
        console.error("Play episode error:", e);
        if (player) player.style.opacity = '1';
    }
}

async function loadRelated(tags) {
    const query = (tags && tags.length) ? tags[0] : '2025';
    const res = await fetch(`/api/v1/?action=search&q=${encodeURIComponent(query)}&page=1`);
    const result = await res.json();
    const container = document.getElementById('relatedVideos');
    if (container && result.data) renderRelatedItems(result.data, container);
}

function renderRelatedItems(items, container) {
    container.innerHTML = '';
    container.className = 'related-list';

    const fragment = document.createDocumentFragment();

    items.slice(0, 8).forEach(item => {
        const card = document.createElement('a');
        card.className = 'related-card';
        const type = item.type || 'movie';
        const slug = item.slug || item.url.split('/').filter(p => p).pop();
        card.href = `/nonton/${slug}?type=${type}`;

        let poster = item.poster;
        if (poster && !poster.startsWith('http') && !poster.startsWith('/')) {
            poster = '/' + poster;
        }
        if (!poster) poster = 'https://via.placeholder.com/300x450?text=No+Poster';

        card.innerHTML = `
            <div class="thumbnail"><img src="${poster}" alt="${item.title}" loading="lazy" onerror="this.src='https://via.placeholder.com/300x450?text=Error'"></div>
            <div class="meta"><h3 class="video-title">${item.title}</h3><p class="video-stats">${item.year || ''}</p></div>
        `;
        fragment.appendChild(card);
    });

    container.appendChild(fragment);
    applyImageLoading(container);
}
// Fullscreen Logic
function toggleFullscreen() {
    const wrapper = document.getElementById('videoWrapper');
    if (!wrapper) return;

    if (!document.fullscreenElement) {
        wrapper.requestFullscreen().catch(err => {
            console.error(`Error: ${err.message}`);
        });
    } else {
        document.exitFullscreen();
    }
}

// Update icon on fullscreen change
document.addEventListener('fullscreenchange', () => {
    const btn = document.getElementById('fullscreenBtn');
    const icon = btn?.querySelector('i');
    if (icon) {
        icon.className = document.fullscreenElement ? 'fas fa-compress' : 'fas fa-expand';
    }
});

// Ensure available globally for onclick
window.toggleFullscreen = toggleFullscreen;
window.toggleWrapperFullscreen = toggleFullscreen; // Alias for player.php

