<?php
// scraper.php - Extracted Logic

// Load Config
$config = require 'config.php';

// --- HELPER FUNCTIONS ---

function getJakartaTime($format = 'Y-m-d H:i:s') {
    date_default_timezone_set('Asia/Jakarta');
    return date($format);
}


function cleanMovieTitle($title) {
    $title = preg_replace('/^Nonton\s+/i', '', $title);
    $title = preg_replace('/\s+Sub\s+Indo\s+di\s+Lk21\s*$/i', '', $title);
    $title = preg_replace('/\s+di\s+Lk21\s*$/i', '', $title);
    $title = preg_replace('/\s+Sub\s+Indo\s*$/i', '', $title);
    return trim($title);
}

function generatePlayerToken($userId, $contentId, $contentType = 'movie', $expiryMinutes = 0, $validateUser = true) {
    // Path ke cache di folder bioskopkeren.biz.id (shared dengan player.php)
    // Adjusted for independence or local structure
    $cacheDir = __DIR__ . '/bioskopkeren.biz.id/cache/';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $tokenDbFile = $cacheDir . 'player_tokens.json';
    
    // Generate random token
    try {
        $token = bin2hex(random_bytes(32)); 
    } catch (Exception $e) {
        $token = bin2hex(openssl_random_pseudo_bytes(32));
    }
    
    // Load existing tokens
    $tokens = [];
    if (file_exists($tokenDbFile)) {
        $tokens = json_decode(file_get_contents($tokenDbFile), true) ?: [];
    }
    
    // Cleanup expired tokens
    $currentTime = time();
    foreach ($tokens as $key => $tokenData) {
        if (($currentTime - $tokenData['created_at']) > 86400) { 
            unset($tokens[$key]);
        }
    }
    
    // Save new token
    $tokens[$token] = [
        'user_id' => $userId,
        'content_id' => $contentId,
        'content_type' => $contentType,
        'created_at' => $currentTime,
        'expires_at' => $currentTime + ($expiryMinutes * 60),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'validate_user' => $validateUser
    ];
    
    file_put_contents($tokenDbFile, json_encode($tokens, JSON_PRETTY_PRINT));
    
    return $token;
}

// --- SCRAPING FUNCTIONS ---

function scrapeMovies($query, $page = 1) {
    global $config;

    $searchUrl = $config['search_url'] . urlencode($query) . "&page=" . $page;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $searchUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, $config['user_agent']);
    curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Referer: https://tv7.lk21official.cc/',
        'Accept: application/json, text/plain, */*'
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$html) {
        if ($httpCode === 0) return 'timeout';
        if ($httpCode === 404) return 'not_found';
        return 'server_error';
    }
    
    $data = json_decode($html, true);
    if (!$data || !isset($data['data'])) return false;
    
    $movies = [];
    foreach ($data['data'] as $film) {
        $movie = [];
        $movie['title'] = $film['title'] ?? '';
        $movie['year'] = $film['year'] ?? '';
        $movie['rating'] = $film['rating'] ?? '';
        $movie['quality'] = $film['quality'] ?? '';
        $movie['type'] = $film['type'] ?? 'movie';
        $movie['slug'] = $film['slug'] ?? '';
        // Poster - use clean URL (htaccess will proxy)
        $movie['poster'] = isset($film['poster']) ? '/wp-content/uploads/' . $film['poster'] : '';
        
        if (!empty($movie['slug'])) {
            // Don't expose source domain - use slug as page identifier
            $movie['url'] = $movie['slug'];
        }
        
        if (!empty($movie['title'])) $movies[] = $movie;
    }
    
    return $movies;
}

/**
 * Scrape movies by genre
 * @param string $genre Genre name (e.g., 'action', 'horror', 'comedy')
 * @param int $page Page number
 * @return array ['movies' => [...], 'total_pages' => int]
 */
function scrapeByGenre($genre, $page = 1) {
    global $config;
    
    $genre = strtolower(trim($genre));

    // Build URL
    $baseUrl = 'https://tv7.lk21official.cc';
    $url = $baseUrl . '/genre/' . urlencode($genre);
    if ($page > 1) {
        $url .= '/page/' . $page;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, $config['user_agent']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    
    $html = curl_exec($ch);
    curl_close($ch);
    
    if (!$html) {
        return ['movies' => [], 'total_pages' => 0, 'current_page' => $page];
    }
    
    $movies = [];
    $totalPages = 1;
    
    // Parse articles
    preg_match_all('/<article[^>]*itemscope[^>]*>(.*?)<\/article>/is', $html, $articles);
    
    foreach ($articles[1] as $article) {
        $movie = [];
        
        // Title
        if (preg_match('/<h3[^>]*class="poster-title"[^>]*>([^<]+)<\/h3>/i', $article, $m)) {
            $movie['title'] = cleanMovieTitle(trim($m[1]));
        }
        
        // URL/Slug
        if (preg_match('/href="\/([^"]+)"[^>]*itemprop="url"/i', $article, $m)) {
            $movie['slug'] = trim($m[1]);
            $movie['url'] = $movie['slug'];
        }
        
        // Poster
        if (preg_match('/<img[^>]*src="([^"]+)"[^>]*itemprop="image"/i', $article, $m)) {
            $poster = $m[1];
            // Convert to local proxy URL
            if (preg_match('/wp-content\/uploads\/(.+)$/i', $poster, $pm)) {
                $movie['poster'] = '/wp-content/uploads/' . $pm[1];
            } else {
                $movie['poster'] = $poster;
            }
        }
        
        // Year
        if (preg_match('/<span[^>]*class="year"[^>]*>(\d{4})<\/span>/i', $article, $m)) {
            $movie['year'] = $m[1];
        }
        
        // Rating
        if (preg_match('/<span[^>]*itemprop="ratingValue"[^>]*>([^<]+)<\/span>/i', $article, $m)) {
            $movie['rating'] = floatval($m[1]);
        }
        
        // Duration
        if (preg_match('/<span[^>]*class="duration"[^>]*>([^<]+)<\/span>/i', $article, $m)) {
            $movie['duration'] = trim($m[1]);
        }
        
        // Genre
        if (preg_match('/<div[^>]*class="genre"[^>]*>([^<]+)<\/div>/i', $article, $m)) {
            $movie['genre'] = trim($m[1]);
        } elseif (preg_match('/itemprop="genre"[^>]*content="([^"]+)"/i', $article, $m)) {
            $movie['genre'] = trim($m[1]);
        }
        
        // Quality label
        if (preg_match('/<span[^>]*class="label[^"]*"[^>]*>([^<]+)<\/span>/i', $article, $m)) {
            $movie['quality'] = trim($m[1]);
        }
        
        $movie['type'] = 'movie';
        
        if (!empty($movie['title']) && !empty($movie['slug'])) {
            $movies[] = $movie;
        }
    }
    
    // Parse pagination - get total pages
    if (preg_match_all('/<li[^>]*><a[^>]*href="[^"]*\/page\/(\d+)"[^>]*>\d+<\/a><\/li>/i', $html, $pageMatches)) {
        $totalPages = max(array_map('intval', $pageMatches[1]));
    }
    // Also check for last page link with » symbol
    if (preg_match('/href="[^"]*\/page\/(\d+)"[^>]*>»<\/a>/i', $html, $lastPage)) {
        $totalPages = max($totalPages, intval($lastPage[1]));
    }
    
    $result = [
        'movies' => $movies,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'genre' => $genre
    ];

    return $result;
}

/**
 * Scrape movies by country
 * @param string $country Country name (e.g., 'japan', 'south-korea', 'thailand')
 * @param int $page Page number
 * @return array ['movies' => [...], 'total_pages' => int, 'current_page' => int, 'country' => string]
 */
function scrapeByCountry($country, $page = 1) {
    global $config;
    
    $country = strtolower(trim($country));

    // Build URL
    $baseUrl = 'https://tv7.lk21official.cc';
    $url = $baseUrl . '/country/' . urlencode($country);
    if ($page > 1) {
        $url .= '/page/' . $page;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, $config['user_agent']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    
    $html = curl_exec($ch);
    curl_close($ch);
    
    if (!$html) {
        return ['movies' => [], 'total_pages' => 0, 'current_page' => $page, 'country' => $country];
    }
    
    $movies = [];
    $totalPages = 1;
    
    // Parse articles
    preg_match_all('/<article[^>]*itemscope[^>]*>(.*?)<\/article>/is', $html, $articles);
    
    foreach ($articles[1] as $article) {
        $movie = [];
        
        // Title
        if (preg_match('/<h3[^>]*class="poster-title"[^>]*>([^<]+)<\/h3>/i', $article, $m)) {
            $movie['title'] = cleanMovieTitle(trim($m[1]));
        }
        
        // URL/Slug
        if (preg_match('/href="\/([^"]+)"[^>]*itemprop="url"/i', $article, $m)) {
            $movie['slug'] = trim($m[1]);
            $movie['url'] = $movie['slug'];
        }
        
        // Poster
        if (preg_match('/<img[^>]*src="([^"]+)"[^>]*itemprop="image"/i', $article, $m)) {
            $poster = $m[1];
            // Convert to local proxy URL
            if (preg_match('/wp-content\/uploads\/(.+)$/i', $poster, $pm)) {
                $movie['poster'] = '/wp-content/uploads/' . $pm[1];
            } else {
                $movie['poster'] = $poster;
            }
        }
        
        // Year
        if (preg_match('/<span[^>]*class="year"[^>]*>(\d{4})<\/span>/i', $article, $m)) {
            $movie['year'] = $m[1];
        }
        
        // Rating
        if (preg_match('/<span[^>]*itemprop="ratingValue"[^>]*>([^<]+)<\/span>/i', $article, $m)) {
            $movie['rating'] = floatval($m[1]);
        }
        
        // Duration
        if (preg_match('/<span[^>]*class="duration"[^>]*>([^<]+)<\/span>/i', $article, $m)) {
            $movie['duration'] = trim($m[1]);
        }
        
        // Genre
        if (preg_match('/<div[^>]*class="genre"[^>]*>([^<]+)<\/div>/i', $article, $m)) {
            $movie['genre'] = trim($m[1]);
        }
        
        // Quality label
        if (preg_match('/<span[^>]*class="label[^"]*"[^>]*>([^<]+)<\/span>/i', $article, $m)) {
            $movie['quality'] = trim($m[1]);
        }
        
        $movie['type'] = 'movie';
        
        if (!empty($movie['title']) && !empty($movie['slug'])) {
            $movies[] = $movie;
        }
    }
    
    // Parse pagination
    if (preg_match_all('/<li[^>]*><a[^>]*href="[^"]*\/page\/(\d+)"[^>]*>\d+<\/a><\/li>/i', $html, $pageMatches)) {
        $totalPages = max(array_map('intval', $pageMatches[1]));
    }
    if (preg_match('/href="[^"]*\/page\/(\d+)"[^>]*>»<\/a>/i', $html, $lastPage)) {
        $totalPages = max($totalPages, intval($lastPage[1]));
    }
    
    $result = [
        'movies' => $movies,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'country' => $country
    ];

    return $result;
}

function scrapeMovieDetail($url) {
    global $config;

    if (empty($url)) {
        return 'not_found';
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, $config['user_agent']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$html) {
         if ($httpCode === 0) return 'timeout';
        if ($httpCode === 404) return 'not_found';
        return 'server_error';
    }
    
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    
    $detail = [];
    // Extract slug from URL for page reference (hide source domain)
    $detail['url'] = basename(parse_url($url, PHP_URL_PATH));
    
    // Title
    $titleNode = $xpath->query('//h1[@class="entry-title"]')->item(0); // Common WP structure or LK21 structure
    if (!$titleNode) $titleNode = $xpath->query('//h1')->item(0);
    $detail['title'] = $titleNode ? cleanMovieTitle($titleNode->textContent) : 'Unknown Title';
    
    // Synopsis
    $synopsisNode = $xpath->query('//div[contains(@class, "synopsis")]')->item(0);
    if (!$synopsisNode) $synopsisNode = $xpath->query('//div[@id="sinopsis"]//blockquote')->item(0);
    
    if ($synopsisNode) {
         // Try getting full text from data-full attribute if available
        $full = $synopsisNode->getAttribute('data-full');
        if (!empty($full)) {
            $detail['synopsis'] = trim($full);
        } else {
            $detail['synopsis'] = trim($synopsisNode->textContent);
        }
    } else {
        $detail['synopsis'] = "Sinopsis tidak tersedia.";
    }

    // Trailer
    // Try multiple common trailer containers
    $trailerNode = $xpath->query('//div[@class="trailer-series"]//iframe')->item(0); // If shared class
    if (!$trailerNode) $trailerNode = $xpath->query('//div[@class="trailer"]//iframe')->item(0);
    if (!$trailerNode) $trailerNode = $xpath->query('//iframe[contains(@src, "youtube")]')->item(0);
    
    if ($trailerNode) {
        $detail['trailer'] = $trailerNode->getAttribute('src');
    }
    
    // Tags (Country, Genre, etc)
    $tagNodes = $xpath->query('//div[@class="tag-list"]//a');
    if (!$tagNodes->length) $tagNodes = $xpath->query('//div[@class="content-info"]//a'); // Fallback

    $tags = [];
    foreach ($tagNodes as $tagNode) {
        $tagName = trim($tagNode->textContent);
        if (!empty($tagName) && strlen($tagName) > 2) {
            $tags[] = $tagName;
        }
    }
    $detail['tags'] = $tags;

    // Poster
    $posterNode = $xpath->query('//img[@class="img-thumbnail"]')->item(0);
    if (!$posterNode) $posterNode = $xpath->query('//div[@class="poster"]//img')->item(0);
    
    if ($posterNode) {
        $posterUrl = $posterNode->getAttribute('src');
        if (strpos($posterUrl, '//') === 0) $posterUrl = 'https:' . $posterUrl;
        
        // Extract path from full URL
        $posterPath = parse_url($posterUrl, PHP_URL_PATH);
        if (strpos($posterPath, '/wp-content/uploads/') !== false) {
            $detail['poster'] = $posterPath;
        } else {
            $detail['poster'] = $config['base_url'] . 'img.php?l=' . urlencode($posterUrl);
        }
    }
    
    // Info Tags (Year, Quality, etc)
    // ... (Existing code)

    // Related Movies (Up Next)
    $related = [];
    $relatedNodes = $xpath->query('//div[@class="related-content"]//ul[@class="video-list"]/li/a');
    
    foreach ($relatedNodes as $node) {
        $item = [];
        $href = $node->getAttribute('href');
        $item['slug'] = basename(parse_url($href, PHP_URL_PATH));
        $item['url'] = $item['slug'];
        $item['type'] = 'movie'; // Default assumption for this section
        
        $titleNode = $xpath->query('.//span[@class="video-title"]', $node)->item(0);
        $item['title'] = $titleNode ? trim($titleNode->textContent) : '';
        
        $yearNode = $xpath->query('.//span[@class="video-year"]', $node)->item(0);
        $item['year'] = $yearNode ? trim($yearNode->textContent) : '';
        
        $item['poster'] = '';
        $imgNode = $xpath->query('.//img', $node)->item(0);
        if ($imgNode) {
            $posterUrl = $imgNode->getAttribute('src');
            if ($posterUrl) {
                $posterPath = parse_url($posterUrl, PHP_URL_PATH);
                if (strpos($posterPath, '/wp-content/uploads/') !== false) {
                     $item['poster'] = $posterPath;
                } else {
                     $item['poster'] = $config['base_url'] . 'img.php?l=' . urlencode($posterUrl);
                }
            }
        }
        
        if (!empty($item['title'])) {
            $related[] = $item;
        }
    }
    $detail['related'] = $related;

    return $detail;
}

function scrapeSeriesDetail($url) {
    global $config;

    if (empty($url)) {
        return 'not_found';
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, $config['user_agent']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$html) return false;

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    
    $detail = [];
    // Extract slug from URL for page reference (hide source domain)
    $detail['url'] = basename(parse_url($url, PHP_URL_PATH));
    $detail['type'] = 'series';
    
    $titleNode = $xpath->query('//div[@class="movie-info"]//h1')->item(0);
    if ($titleNode) {
        $rawTitle = trim($titleNode->textContent);
        $detail['title'] = preg_replace('/^Nonton\s+Serial\s+/i', '', $rawTitle);
    } else {
        $detail['title'] = 'Unknown Series';
    }
    
    // Synopsis
    $synopsisNode = $xpath->query('//div[contains(@class, "synopsis")]')->item(0);
    if ($synopsisNode) {
        // Try getting full text from data-full attribute if available
        $full = $synopsisNode->getAttribute('data-full');
        if (!empty($full)) {
            $detail['synopsis'] = trim($full);
        } else {
            $detail['synopsis'] = trim($synopsisNode->textContent);
        }
    } else {
        $detail['synopsis'] = "Sinopsis tidak tersedia.";
    }

    // Trailer
    // Try multiple common trailer containers (Robust)
    $trailerNode = $xpath->query('//div[contains(@class, "trailer-series")]//iframe')->item(0);
    if (!$trailerNode) $trailerNode = $xpath->query('//div[contains(@class, "trailer")]//iframe')->item(0);
    if (!$trailerNode) $trailerNode = $xpath->query('//iframe[contains(@src, "youtube")]')->item(0);
    
    if ($trailerNode) {
        $detail['trailer'] = $trailerNode->getAttribute('src');
    }

    // Tags (Country, Genre, etc)
    $tagNodes = $xpath->query('//div[@class="tag-list"]//a');
    $tags = [];
    foreach ($tagNodes as $tagNode) {
        $tagName = trim($tagNode->textContent);
        if (!empty($tagName)) {
            $tags[] = $tagName;
        }
    }
    $detail['tags'] = $tags;

    // Episodes
    $episodesMap = [];
    
    // Strategy: Extract from JSON data script (most reliable)
    $jsonPattern = '/<script id="season-data" type="application\/json">\s*(.*?)\s*<\/script>/is';
    if (preg_match($jsonPattern, $html, $matches)) {
        $jsonStr = $matches[1];
        $seasonData = json_decode($jsonStr, true);
        
        if ($seasonData && is_array($seasonData)) {
            foreach ($seasonData as $season => $epsList) {
                if (is_array($epsList)) {
                    foreach ($epsList as $ep) {
                        $epNum = $ep['episode_no'] ?? '';
                        $epSlug = $ep['slug'] ?? '';
                        
                        if ($epSlug) {
                             $slug = ltrim($epSlug, '/');
                             $episodesMap[$slug] = [
                                 'number' => $epNum,
                                 'url' => $slug,
                                 'title' => $ep['title'] ?? '',
                                 'season' => $season // Capture Season ID/Number
                             ];
                        }
                    }
                }
            }
        }
    }
    
    // Fallback: Check older logic if still empty
    if (empty($episodesMap)) {
        $episodeNodes = $xpath->query('//ul[contains(@class, "episode-list")]//li//a');
        foreach ($episodeNodes as $episodeNode) {
            $episodeUrl = $episodeNode->getAttribute('href');
            $episodeNum = trim($episodeNode->textContent);
            if (!empty($episodeUrl)) {
                 if (strpos($episodeUrl, 'http') === 0) {
                     $slug = basename(parse_url($episodeUrl, PHP_URL_PATH));
                 } else {
                     $slug = ltrim($episodeUrl, '/');
                 }
                $episodesMap[$slug] = [
                    'number' => $episodeNum,
                    'url' => $slug
                ];
            }
        }
    }

    $detail['episodes'] = array_values($episodesMap);
    
    // Poster
    $posterNode = $xpath->query('//source[@type="image/jpeg"]')->item(0);
    if ($posterNode) {
        $posterUrl = $posterNode->getAttribute('srcset');
        if ($posterUrl) {
            // Poster - use clean URL (htaccess will proxy)
            $posterPath = parse_url($posterUrl, PHP_URL_PATH);
            if (strpos($posterPath, '/wp-content/uploads/') !== false) {
                $detail['poster'] = $posterPath;
            } else {
                $detail['poster'] = $config['base_url'] . 'img.php?l=' . urlencode($posterUrl);
            }
        }
    }

    // Related Series
    $related = [];
    // XPath for "Series Terkait" slider
    $relatedNodes = $xpath->query('//div[contains(@class, "slider-wrapper")][@aria-label="Series Terkait"]//ul[contains(@class, "sliders")]//li//article//figure//a');
    
    foreach ($relatedNodes as $node) {
        $item = [];
        $href = $node->getAttribute('href');
        $item['slug'] = basename(parse_url($href, PHP_URL_PATH));
        $item['url'] = $item['slug'];
        $item['type'] = 'series';
        
        // Title often in h3 inside figcaption or similar
        // Based on user snippet: <h3 class="poster-title" ...>
        $titleNode = $xpath->query('.//h3[contains(@class, "poster-title")]', $node)->item(0);
        $item['title'] = $titleNode ? trim($titleNode->textContent) : '';
        
        // Year
        $yearNode = $xpath->query('.//span[contains(@class, "year")]', $node)->item(0);
        $item['year'] = $yearNode ? trim($yearNode->textContent) : '';
        
        // Poster
        $imgNode = $xpath->query('.//img', $node)->item(0);
        if ($imgNode) {
             $posterUrl = $imgNode->getAttribute('src');
             $posterPath = parse_url($posterUrl, PHP_URL_PATH);
             if (strpos($posterPath, '/wp-content/uploads/') !== false) {
                  $item['poster'] = $posterPath;
             } else {
                  $item['poster'] = $config['base_url'] . 'img.php?l=' . urlencode($posterUrl);
             }
        }
        
        if (!empty($item['title'])) {
            $related[] = $item;
        }
    }
    $detail['related'] = $related;
    
    return $detail;
}



/**
 * Scrape movies/series from a specific raw URL (HTML)
 * Reuses the HTML parsing logic from scrapeByGenre
 */
function scrapeSpecificUrl($url, $page = 1) {
    global $config;

    if (empty($url)) {
        return [];
    }

    if ($page > 1) {
        $url .= '/page/' . $page;
    }

    // Setup cache file path
    $cacheDir = __DIR__ . '/cache/';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $cacheFile = $cacheDir . md5($url) . '.json';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, $config['user_agent']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    
    $html = curl_exec($ch);
    curl_close($ch);
    
    if (!$html) return [];
    
    $movies = [];
    
    // Parse articles (Same regex as scrapeByGenre/Country)
    preg_match_all('/<article[^>]*itemscope[^>]*>(.*?)<\/article>/is', $html, $articles);
    
    foreach ($articles[1] as $article) {
        $movie = [];
        
        // Title
        if (preg_match('/<h3[^>]*class="poster-title"[^>]*>([^<]+)<\/h3>/i', $article, $m)) {
            $movie['title'] = cleanMovieTitle(trim($m[1]));
        }
        
        // URL/Slug
        if (preg_match('/href="\/([^"]+)"[^>]*itemprop="url"/i', $article, $m)) {
            $movie['slug'] = trim($m[1]);
            $movie['url'] = $movie['slug'];
        }
        
        // Poster
        if (preg_match('/<img[^>]*src="([^"]+)"[^>]*itemprop="image"/i', $article, $m)) {
            $poster = $m[1];
            if (preg_match('/wp-content\/uploads\/(.+)$/i', $poster, $pm)) {
                $movie['poster'] = '/wp-content/uploads/' . $pm[1];
            } else {
                $movie['poster'] = $poster;
            }
        }
        
        // Year
        if (preg_match('/<span[^>]*class="year"[^>]*>(\d{4})<\/span>/i', $article, $m)) {
            $movie['year'] = $m[1];
        } else if (preg_match('/itemprop="datePublished">(\d{4})<\/span>/i', $article, $m)) {
            $movie['year'] = $m[1];
        }
        
        // Rating
        if (preg_match('/<span[^>]*itemprop="ratingValue"[^>]*>([^<]+)<\/span>/i', $article, $m)) {
            $movie['rating'] = floatval($m[1]);
        }

        // Episode (New)
        if (preg_match('/<span[^>]*class="episode[^"]*"[^>]*>EPS\s*<strong>(\d+)<\/strong><\/span>/i', $article, $m)) {
            $movie['episode'] = $m[1];
        }

        // Duration / Season (New)
        if (preg_match('/<span[^>]*class="duration"[^>]*>([^<]+)<\/span>/i', $article, $m)) {
            $movie['duration'] = trim($m[1]);
        }
        
        // Genre (New)
        if (preg_match('/<meta[^>]*itemprop="genre"[^>]*content="([^"]+)"/i', $article, $m)) {
             $movie['genres'] = $m[1];
        } else if (preg_match('/<div[^>]*class="genre"[^>]*>\s*(.*?)\s*<\/div>/i', $article, $m)) {
             $movie['genres'] = trim($m[1]);
        }
        
        // Type Detection
        $movie['type'] = 'movie'; // Default
        
        // strong indicators
        if (isset($movie['episode']) || stripos($movie['slug'], 'season') !== false || isset($movie['duration']) && stripos($movie['duration'], 'S.') !== false) {
             $movie['type'] = 'series';
        }
        // explicit url context overrides
        else if (stripos($url, 'series') !== false || stripos($url, 'tv') !== false) {
             // Only if NOT scraping a mixed page like 'populer' or 'latest'
             if (stripos($url, 'latest') === false && stripos($url, 'populer') === false) {
                 $movie['type'] = 'series';
             }
        }

        if (!empty($movie['title']) && !empty($movie['slug'])) {
            $movies[] = $movie;
        }
    }
    
    // Save to cache
    if (!empty($movies)) {
        file_put_contents($cacheFile, json_encode($movies));
    }
    
    return $movies;
}
?>
