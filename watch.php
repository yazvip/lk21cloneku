<?php
// watch.php - Dedicated Player Page
require_once 'config.php';
require_once 'seo_helper.php';
// Include scraper for Server-Side Rendering (SEO)
require_once 'scraper.php';

$slug = $_GET['slug'] ?? '';
$type = $_GET['type'] ?? 'movie';

// Input Validation
if (empty($slug) || !preg_match('/^[a-z0-9-]+$/', $slug)) {
    header("Location: /");
    exit;
}

// Server-Side Data Fetching
$detailData = null;
$error = null;

try {
    // Construct URL based on logic from api.php
    // Note: These domains should ideally be in config.php
    if ($type === 'series' || $type === 'tv') {
        $targetUrl = 'https://tv3.nontondrama.my/' . $slug;
        $detailData = scrapeSeriesDetail($targetUrl);
    } else {
        $targetUrl = 'https://tv7.lk21official.cc/' . $slug;
        $detailData = scrapeMovieDetail($targetUrl);
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

// 4. Fallback / Mock Data (if scraping fails)
if (!$detailData || empty($detailData['title'])) {
    // Fallback to "Loading..." state but keep valid structure
    $niceTitle = ucwords(str_replace('-', ' ', $slug));
    $pageTitle = "Nonton $niceTitle - MovieTube";
    $pageDesc = "Streaming $niceTitle subtitle Indonesia gratis.";
    $pageImage = "https://via.placeholder.com/1280x720?text=$niceTitle";
    // Mark detail as null so JS tries to fetch again or handle error
    $detailData = null; 
} else {
    // Real Data for SEO
    $pageTitle = "Nonton " . $detailData['title'] . " - MovieTube";
    $pageDesc = $detailData['synopsis'] ?? "Streaming " . $detailData['title'] . " Subtitle Indonesia.";
    // Ensure description isn't too long for meta tag
    if (strlen($pageDesc) > 160) $pageDesc = substr($pageDesc, 0, 157) . "...";
    
    $pageImage = $detailData['poster'] ?? "";
    if (strpos($pageImage, '//') === 0) $pageImage = 'https:' . $pageImage;
    if (strpos($pageImage, 'http') !== 0 && $pageImage) {
        $pageImage = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $pageImage;
    }
}

// 5. Schema Data
$schemaData = [
    'title' => $detailData['title'] ?? $niceTitle,
    'synopsis' => $pageDesc,
    'poster' => $pageImage,
    'year' => $detailData['year'] ?? date('Y'),
    'url' => (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]",
    'player_url' => ""
];
$jsonLD = generateSchema($type === 'series' ? 'TVSeries' : 'Movie', $schemaData);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($pageImage) ?>">
    <meta property="og:type" content="<?= $type === 'series' ? 'video.tv_show' : 'video.movie' ?>">
    <meta property="og:url" content="<?= $schemaData['url'] ?>">
    
    <?= $jsonLD ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/enhancements.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>

    <script>
        // Pass server-fetched data to JS to avoid double fetch
        // json_encode ensures XSS safety
        window.watchSlug = <?= json_encode($slug) ?>;
        window.watchType = <?= json_encode($type) ?>;
        window.initialData = <?= json_encode($detailData) ?>; 
    </script>
</head>
<body class="watch-page">

    <!-- Ambient Light Background -->
    <div id="ambient-glow"></div>

    <!-- Glass Navbar -->
    <nav class="navbar">
        <a href="/" class="logo"><i class="fas fa-play-circle"></i> MovieTube</a>
        
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search movies...">
            <button onclick="handleSearch()"><i class="fas fa-search"></i></button>
        </div>

        <div class="nav-right" style="display:flex; gap:15px; align-items:center;">
            <button id="navPersistenceBtn" class="btn-outline" style="padding: 8px; border-radius: 50%;"><i class="fas fa-history"></i></button>
        </div>

        <!-- Persistence Drawer -->
        <div id="persistenceDrawer" class="nav-drawer">
            <div class="drawer-header">
                <h3><i class="fas fa-layer-group"></i> Library</h3>
                <button onclick="toggleDrawer()"><i class="fas fa-times"></i></button>
            </div>
            <div id="drawerContent" class="drawer-body"></div>
        </div>
    </nav>

    <!-- Main Content -->
    <div id="main-content" class="main-container">
        <div class="watch-layout">
            
            <!-- Main Video Column -->
            <div class="player-column">
                <div class="player-wrapper" id="videoWrapper">
                    <!-- Player Placeholder -->
                    <div class="player-placeholder" style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:#888;">
                        <div class="loader"></div>
                        <p style="margin-top:20px;">Memuat video...</p>
                    </div>
                    <iframe id="mainPlayer" src="" style="display:none;" allowfullscreen></iframe>
                    
                    <!-- Watermark -->
                    <div class="player-watermark">MR.YAZ</div>
                    
                    <!-- Fullscreen Button -->
                    <button id="fullscreenBtn" onclick="toggleFullscreen()" style="position:absolute; top:15px; left:15px; z-index:100; background:rgba(0,0,0,0.7); border:none; color:white; padding:10px 12px; border-radius:8px; cursor:pointer; font-size:16px; display:flex; align-items:center; gap:6px;">
                        <i class="fas fa-expand"></i>
                    </button>
                </div>

                <!-- Episode Section -->
                <div id="episodeSection" class="episodes-container" style="display:none; margin-top: 15px;">
                    <h3 style="margin-top:0; font-size: 18px;"><i class="fas fa-list-ol"></i> Pilih Episode</h3>
                    <div id="episodeGrid" class="ep-grid"></div>
                </div>

                <div class="watch-info">
                    <h1 class="video-title" style="font-size: 32px; white-space: normal; margin: 0 0 15px 0; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px;"><?= htmlspecialchars($detailData['title'] ?? $niceTitle ?? 'Loading...') ?></h1>

                    <div id="videoTags" class="tag-list"></div>

                    <div style="border-top: 2px solid var(--accent); padding-top: 20px; margin-top: 20px;">
                        <p id="fullSynopsis" style="color: #bbb; line-height: 1.8; margin: 0; font-size: 15px;"><?= htmlspecialchars($detailData['synopsis'] ?? 'Memuat sinopsis...') ?></p>
                        <button class="btn-show-more" style="display:none; background:transparent; border:none; color:var(--accent); cursor:pointer; font-weight: 600; margin-top: 10px;">Selengkapnya</button>
                    </div>
                </div>
            </div>

            <!-- Sidebar Recommendations -->
            <div class="sidebar-column">
                <h3 style="margin-top:0; font-size: 20px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--accent); display: flex; align-items: center; gap: 10px;"><i class="fas fa-play" style="color: var(--accent); font-size: 18px;"></i> Rekomendasi</h3>
                <div id="relatedVideos" class="related-list">
                    <!-- Related items -->
                    <div style="text-align: center; padding: 30px 0;">
                        <div class="loader" style="margin: 0 auto;"></div>
                        <p style="color: var(--text-secondary); font-size: 13px; margin-top: 10px;">Memuat...</p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="/app.js"></script>
    <script src="/animations.js"></script>
</body>
</html>
