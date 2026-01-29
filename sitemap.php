<?php
// sitemap.php - Dynamic XML Sitemap Generator

header('Content-Type: application/xml; charset=utf-8');

$base_url = 'https://' . $_SERVER['HTTP_HOST'];
$current_date = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
        xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">

    <!-- Homepage -->
    <url>
        <loc><?= $base_url ?>/</loc>
        <lastmod><?= $current_date ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Static Pages -->
    <url>
        <loc><?= $base_url ?>/series</loc>
        <lastmod><?= $current_date ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>

    <url>
        <loc><?= $base_url ?>/movie</loc>
        <lastmod><?= $current_date ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>

    <!-- Genres -->
    <?php
    $genres = [
        'action', 'adventure', 'animation', 'comedy', 'crime',
        'documentary', 'drama', 'family', 'fantasy', 'history',
        'horror', 'music', 'mystery', 'romance', 'sci-fi',
        'thriller', 'war', 'western'
    ];

    foreach ($genres as $genre) {
        echo '<url>';
        echo '<loc>' . $base_url . '/genre/' . $genre . '</loc>';
        echo '<lastmod>' . $current_date . '</lastmod>';
        echo '<changefreq>weekly</changefreq>';
        echo '<priority>0.7</priority>';
        echo '</url>' . "\n";
    }
    ?>

    <!-- Countries -->
    <?php
    $countries = [
        'usa', 'uk', 'korea', 'japan', 'china', 'thailand',
        'indonesia', 'india', 'france', 'spain'
    ];

    foreach ($countries as $country) {
        echo '<url>';
        echo '<loc>' . $base_url . '/country/' . $country . '</loc>';
        echo '<lastmod>' . $current_date . '</lastmod>';
        echo '<changefreq>weekly</changefreq>';
        echo '<priority>0.6</priority>';
        echo '</url>' . "\n";
    }
    ?>

    <!-- Years -->
    <?php
    $current_year = date('Y');
    for ($year = $current_year; $year >= 2020; $year--) {
        echo '<url>';
        echo '<loc>' . $base_url . '/year/' . $year . '</loc>';
        echo '<lastmod>' . $current_date . '</lastmod>';
        echo '<changefreq>monthly</changefreq>';
        echo '<priority>0.5</priority>';
        echo '</url>' . "\n";
    }
    ?>

</urlset>
