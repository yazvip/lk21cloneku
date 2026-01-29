<?php
// index.php - YouTube Style Home
require_once 'config.php';
require_once 'seo_helper.php';

$action = $_GET['action'] ?? 'home';
$query = $_GET['q'] ?? '';

// Default SEO
$pageTitle = "MovieTube - Streaming Film & Series Gratis";
$pageDesc = "Nonton streaming film sub indo gratis dengan tampilan nyaman bebas iklan.";

if ($action === 'genre' && !empty($query)) {
    $pageTitle = "Nonton Film " . ucfirst($query) . " Sub Indo Terupdate - MovieTube";
    $pageDesc = "Kumpulan film genre " . $query . " terbaik dan terbaru sub indo kualitas HD.";
} elseif ($action === 'year' && !empty($query)) {
    $pageTitle = "Rekomendasi Film Tahun " . $query . " - MovieTube";
    $pageDesc = "Daftar film rilisan tahun " . $query . " terlengkap dengan kualitas super jernih.";
} elseif ($action === 'search' && !empty($query)) {
    $pageTitle = "Hasil Pencarian: " . $query . " - MovieTube";
}

$pageImage = "https://via.placeholder.com/1200x630.png?text=MovieTube";
$pageUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$canonicalUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . strtok($_SERVER["REQUEST_URI"], '?');

// Initial Data for JS
$initialData = [
    'mode' => $action,
    'query' => $query
];

// Schema - Enhanced with SearchAction
$jsonLD = generateSchema('WebSite', [
    'url' => $canonicalUrl,
    'name' => 'MovieTube',
    'description' => $pageDesc,
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => $canonicalUrl . '/search/{search_term_string}',
        'query-input' => 'required name=search_term_string'
    ]
]);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($pageImage) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="MovieTube">
    <meta property="og:locale" content="id_ID">
    
    <?= $jsonLD ?>

    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/enhancements.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>

    <script>
        window.initialData = <?= json_encode($initialData) ?>;
    </script>
    <style>
        /* Generic Dropdown Styles */
        .nav-dropdown {
            position: relative;
            display: inline-block;
        }

        .nav-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: linear-gradient(135deg, #1a1f3a 0%, #0a0e27 100%);
            min-width: 280px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.6), 0 0 20px rgba(255, 193, 7, 0.1);
            z-index: 1000;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            grid-template-columns: 1fr 1fr;
            gap: 5px;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .nav-dropdown:hover .nav-menu {
            display: grid;
        }

        .nav-menu a {
            color: #bbb;
            padding: 8px 12px;
            text-decoration: none;
            display: block;
            font-size: 13px;
            border-radius: 6px;
            transition: all 0.2s;
            white-space: nowrap;
            font-weight: 500;
        }

        .nav-menu a:hover {
            background: linear-gradient(135deg, var(--accent), #ffb300);
            color: #0a0e27;
        }

        .nav-dropdown-btn {
            background: transparent;
            border: 2px solid rgba(255, 193, 7, 0.2);
            color: #fff;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-weight: 600;
        }

        .nav-dropdown-btn:hover {
            border-color: var(--accent);
            background: rgba(255, 193, 7, 0.1);
        }

        .page-title {
            font-size: 28px;
            font-weight: 900;
            margin: 30px 0 20px;
            color: #fff;
            border-left: 4px solid var(--accent);
            padding-left: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

    <!-- Ambient Light Background -->
    <div id="ambient-glow"></div>

    <!-- Glass Navbar -->
    <nav class="navbar">
        <a href="/" class="logo">
            <i class="fas fa-play-circle"></i>
            <span>Movie<span>Tube</span></span>
        </a>
        
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search movies..." autocomplete="off">
            <button onclick="handleSearch()"><i class="fas fa-search"></i></button>
            <div id="searchSuggestions" class="search-suggestions"></div>
        </div>

        <div class="nav-right" style="display:flex; gap:15px; align-items:center;">
            <!-- Genre Dropdown -->
            <div class="nav-dropdown">
                <button class="nav-dropdown-btn">Genre <i class="fas fa-chevron-down"></i></button>
                <div class="nav-menu">
                    <!-- Col 1 -->
                    <a href="/genre/action">Action</a>
                    <a href="/genre/adventure">Adventure</a>
                    <a href="/genre/animation">Animation</a>
                    <a href="/genre/biography">Biography</a>
                    <a href="/genre/comedy">Comedy</a>
                    <a href="/genre/crime">Crime</a>
                    <a href="/genre/documentary">Documentary</a>
                    <a href="/genre/drama">Drama</a>
                    <a href="/genre/family">Family</a>
                    <a href="/genre/fantasy">Fantasy</a>

                    <!-- Col 2 -->
                    <a href="/genre/history">History</a>
                    <a href="/genre/horror">Horror</a>
                    <a href="/genre/musical">Musical</a>
                    <a href="/genre/mystery">Mystery</a>
                    <a href="/genre/romance">Romance</a>
                    <a href="/genre/sci-fi">Sci-Fi</a>
                    <a href="/genre/sport">Sport</a>
                    <a href="/genre/thriller">Thriller</a>
                    <a href="/genre/war">War</a>
                    <a href="/genre/western">Western</a>
                </div>
            </div>

            <button id="navPersistenceBtn" class="btn-outline" style="padding: 8px; border-radius: 50%;"><i class="fas fa-history"></i></button>
        </div>

        <!-- Persistence Drawer (History & Watchlist) -->
        <div id="persistenceDrawer" class="nav-drawer">
            <div class="drawer-header">
                <h3><i class="fas fa-layer-group"></i> Library</h3>
                <button onclick="toggleDrawer()"><i class="fas fa-times"></i></button>
            </div>
            <div id="drawerContent" class="drawer-body">
                <!-- Content injected via JS -->
            </div>
        </div>
    </nav>
    
    <!-- Toast Notification Container -->
    <div id="toastContainer" class="toast-container"></div>
    
    <!-- Main Content -->
    <div id="main-content" class="main-container">
        
        <!-- Hero Banner -->
        <div id="heroBanner" class="hero-banner">
            <div class="hero-bg" id="heroBg"></div>
            <div class="hero-overlay"></div>
            <div class="hero-content">
                <span class="hero-badge">🔥 TRENDING</span>
                <h1 id="heroTitle">Loading...</h1>
                <p id="heroDesc">Memuat film unggulan...</p>
                <div class="hero-actions">
                    <a id="heroPlayBtn" href="#" class="btn-primary"><i class="fas fa-play"></i> Nonton Sekarang</a>
                    <button id="heroAddBtn" class="btn-outline"><i class="fas fa-plus"></i> Watchlist</button>
                </div>
            </div>
        </div>
        


        <!-- Main Layout with Sidebar -->
        <div class="home-layout">
            <!-- Main Grid -->
            <div class="home-main">
                <div id="gridHeader" style="display:none;"></div>
                
                <!-- Dynamic Sections -->
                <div id="homeSections">
                <!-- Continue Watching -->
                <div id="section-continue" class="section-block" style="display:none;">
                    <div class="section-title"><i class="fas fa-play-circle"></i> Lanjutkan Menonton</div>
                    <div id="grid-continue" class="continue-watching-grid horizontal-scroll">
                        <!-- Injected via JS -->
                    </div>
                </div>

                <!-- Terbaru -->
                    <div class="section-block">
                        <div class="section-title"><i class="fas fa-certificate"></i> Terbaru</div>
                        <div id="grid-latest" class="video-grid">
                            <div class="loader"></div>
                        </div>
                    </div>

                    <!-- Top Series -->
                    <div class="section-block">
                        <div class="section-title"><i class="fas fa-crown"></i> Top Series</div>
                        <div id="grid-top-series" class="horizontal-scroll">
                            <div class="loader"></div>
                        </div>
                    </div>

                    <!-- Series Update -->
                    <div class="section-block">
                        <div class="section-title"><i class="fas fa-sync-alt"></i> Series Update</div>
                        <div id="grid-new-series" class="horizontal-scroll">
                            <div class="loader"></div>
                        </div>
                    </div>

                    <!-- Populer -->
                    <div class="section-block">
                        <div class="section-title"><i class="fas fa-fire"></i> Populer</div>
                        <div id="grid-popular" class="video-grid">
                            <div class="loader"></div>
                        </div>
                    </div>
                </div>

                <!-- Fallback Grid for Search/Genre results -->
                <div id="videoGrid" class="video-grid" style="display:none;"></div>
                
                <!-- Pagination -->
                <div id="paginationLoading" style="text-align:center; padding: 40px; display:none;">
                    <div class="loader"></div>
                </div>
            </div>

            <!-- Trending Sidebar -->
            <aside id="trendingSidebar" class="home-sidebar">
                <div class="sidebar-title"><i class="fas fa-fire"></i> Trending</div>
                <div id="trendingList" class="sidebar-list">
                    <div class="loader"></div>
                </div>
            </aside>
        </div>

    </div>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-content">
            <div class="footer-brand">
                <span class="logo"><i class="fas fa-film"></i> MovieTube</span>
                <p>Nonton streaming film dan series sub indo gratis.</p>
            </div>
            <div class="footer-links">
                <a href="#">DMCA</a>
                <a href="#">Disclaimer</a>
                <a href="#">Contact</a>
                <a href="#">Privacy Policy</a>
            </div>
            <div class="footer-social">
                <a href="#"><i class="fab fa-telegram"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2026 ANdRIAS. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bottom Navigation (Mobile) -->
    <nav class="bottom-nav">
        <a href="/" class="bottom-nav-item active" onclick="loadHome(); return false;">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="#" class="bottom-nav-item" onclick="toggleMobileSearch(); return false;">
            <i class="fas fa-search"></i>
            <span>Search</span>
        </a>
        <a href="#" class="bottom-nav-item" onclick="toggleDrawer(); return false;">
            <i class="fas fa-bookmark"></i>
            <span>Library</span>
        </a>
    </nav>

    <script src="/app.js"></script>
    <script src="/animations.js"></script>
    <script>
        // Handle URL routing for genre pages
        (function() {
            const urlParams = new URLSearchParams(window.location.search);
            const action = urlParams.get('action');
            const query = urlParams.get('q');
            
            // Also detect URL path like /genre/Horror
            const pathMatch = window.location.pathname.match(/^\/genre\/([^\/]+)/i);
            
            // Detect URLs like /series or /movie
            const typeMatch = window.location.pathname.match(/^\/(series|movie)\/?/i);
            
            // Detect URLs like /country/Japan
            const countryMatch = window.location.pathname.match(/^\/country\/([^\/]+)/i);
            
            if (action === 'genre' && query) {
                // Loaded via ?action=genre&q=Horror
                setTimeout(() => browseGenre(query), 100);
            } else if (pathMatch) {
                // Loaded via /genre/Horror path
                setTimeout(() => browseGenre(decodeURIComponent(pathMatch[1])), 100);
            } else if (countryMatch) {
                // Loaded via /country/Japan
                setTimeout(() => browseCountry(decodeURIComponent(countryMatch[1])), 100);
            } else if (typeMatch) {
                // Loaded via /series or /movie path
                const type = typeMatch[1].toLowerCase();
                setTimeout(() => browseType(type), 100);
            } else if (action === 'search' && query) {
                setTimeout(() => searchContent(query), 100);
            }
        })();
    </script>
</body>
</html>
