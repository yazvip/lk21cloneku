<?php
// api.php - JSON API for Web UI

// Disable error display to prevent breaking JSON, log errors instead
error_reporting(0); 
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/api_error.log');

// Load config
$config = require 'config.php';

// Set headers
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    require_once 'scraper.php';
} catch (Throwable $e) {
    echo json_encode(['error' => 'Backend configuration error']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'search':
        $query = $_GET['q'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        
        if (empty($query)) {
            echo json_encode(['error' => 'Query kosong']);
            exit;
        }

        $results = scrapeMovies($query, $page);
        
        if ($results === 'timeout') {
            echo json_encode(['error' => 'Timeout koneksi ke server']);
        } elseif ($results === 'not_found' || empty($results)) {
             echo json_encode(['data' => []]);
        } elseif ($results === 'server_error') {
             echo json_encode(['error' => 'Server error']);
        } else {
            // Ensure array
            $data = is_array($results) ? $results : [];
            echo json_encode(['data' => $data, 'page' => $page]);
        }
        break;

// ... (detail case skipped, unchanged)

    case 'detail':
        // Accept page + type instead of full URL to hide source domains
        $page = $_GET['page'] ?? '';
        $url = $_GET['url'] ?? ''; // Fallback for backward compat
        $type = $_GET['type'] ?? 'movie';
        
        // If page provided, build URL internally (source hidden from user)
        if (!empty($page)) {
            if ($type === 'series' || $type === 'tv') {
                $url = 'https://tv3.nontondrama.my/' . $page;
            } else {
                $url = 'https://tv7.lk21official.cc/' . $page;
            }
        }
        
        if (empty($url)) {
            echo json_encode(['error' => 'Page atau URL kosong']);
            exit;
        }

        if ($type === 'series' || $type === 'tv') {
            $detail = scrapeSeriesDetail($url);
        } else {
            $detail = scrapeMovieDetail($url);
        }

        if (!$detail || is_string($detail)) {
             echo json_encode(['error' => 'Gagal mengambil detail']);
        } else {
             echo json_encode(['data' => $detail]);
        }
        break;

    case 'get_token':
        $id = $_GET['id'] ?? ''; 
        $type = $_GET['type'] ?? 'movie';
        
        if (empty($id)) {
            echo json_encode(['error' => 'ID kosong']);
            exit;
        }
        
        $userId = 'web_user_' . md5($_SERVER['REMOTE_ADDR']);
        // $token = generatePlayerToken($userId, $id, $type, 0); 
        $token = ''; // Token disabled
        
        // $encodedSlug = base64_encode($id);
        $encodedSlug = $id; // No base64 encoding
        
        // Use base_url from config if available, otherwise dynamic
        if (isset($config['base_url'])) {
           $baseUrl = $config['base_url'];
        } else {
           $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
           $host = $_SERVER['HTTP_HOST'];
           $baseUrl = "$protocol://$host/";
        }
        
        // Remove token param to be cleaner, though dl.php ignores it now
        $streamUrl = $baseUrl . "dl.php?url=" . urlencode($encodedSlug) . "&type=" . $type;
        
        echo json_encode(['token' => $token, 'stream_url' => $streamUrl]);
        break;

    case 'adult':
        $query = $_GET['q'] ?? '';
        if ($query === 'random') {
             // Fallback to searching for a common term for "random" like behavior if API doesn't support random flag
             // Or if scrapeAdultContent supports it, use it.
             // Assuming previous impl of scrapeAdultContent handled this.
             $results = scrapeAdultContent(''); 
        } else {
             $results = scrapeAdultContent($query);
        }
        
        if (!$results || !is_array($results)) {
             echo json_encode(['data' => []]);
        } else {
             echo json_encode(['data' => $results]);
        }
        break;

    case 'home_section':
        try {
            $section = $_GET['section'] ?? 'latest';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $useDemo = isset($_GET['demo']) && $_GET['demo'] === '1';

            // Demo data for UI testing
            $demoData = [
                'latest' => [
                    ['title' => 'Spider-Man: No Way Home', 'slug' => 'spider-man-no-way-home', 'poster' => 'https://via.placeholder.com/300x450?text=Spider-Man', 'year' => '2024', 'type' => 'movie'],
                    ['title' => 'Barbie', 'slug' => 'barbie', 'poster' => 'https://via.placeholder.com/300x450?text=Barbie', 'year' => '2023', 'type' => 'movie'],
                    ['title' => 'The Dark Knight Rises', 'slug' => 'the-dark-knight-rises', 'poster' => 'https://via.placeholder.com/300x450?text=Dark+Knight', 'year' => '2022', 'type' => 'movie'],
                    ['title' => 'Inception', 'slug' => 'inception', 'poster' => 'https://via.placeholder.com/300x450?text=Inception', 'year' => '2023', 'type' => 'movie'],
                    ['title' => 'Interstellar', 'slug' => 'interstellar', 'poster' => 'https://via.placeholder.com/300x450?text=Interstellar', 'year' => '2024', 'type' => 'movie'],
                    ['title' => 'Oppenheimer', 'slug' => 'oppenheimer', 'poster' => 'https://via.placeholder.com/300x450?text=Oppenheimer', 'year' => '2023', 'type' => 'movie'],
                ],
                'top_series' => [
                    ['title' => 'Breaking Bad', 'slug' => 'breaking-bad', 'poster' => 'https://via.placeholder.com/300x450?text=Breaking+Bad', 'year' => '2023', 'type' => 'series'],
                    ['title' => 'Game of Thrones', 'slug' => 'game-of-thrones', 'poster' => 'https://via.placeholder.com/300x450?text=Game+of+Thrones', 'year' => '2022', 'type' => 'series'],
                    ['title' => 'Stranger Things', 'slug' => 'stranger-things', 'poster' => 'https://via.placeholder.com/300x450?text=Stranger+Things', 'year' => '2023', 'type' => 'series'],
                    ['title' => 'The Crown', 'slug' => 'the-crown', 'poster' => 'https://via.placeholder.com/300x450?text=The+Crown', 'year' => '2024', 'type' => 'series'],
                    ['title' => 'Chernobyl', 'slug' => 'chernobyl', 'poster' => 'https://via.placeholder.com/300x450?text=Chernobyl', 'year' => '2023', 'type' => 'series'],
                    ['title' => 'The Witcher', 'slug' => 'the-witcher', 'poster' => 'https://via.placeholder.com/300x450?text=The+Witcher', 'year' => '2024', 'type' => 'series'],
                ],
                'new_series' => [
                    ['title' => 'Wednesday', 'slug' => 'wednesday', 'poster' => 'https://via.placeholder.com/300x450?text=Wednesday', 'year' => '2024', 'type' => 'series'],
                    ['title' => 'Shogun', 'slug' => 'shogun', 'poster' => 'https://via.placeholder.com/300x450?text=Shogun', 'year' => '2024', 'type' => 'series'],
                    ['title' => 'Fallout', 'slug' => 'fallout', 'poster' => 'https://via.placeholder.com/300x450?text=Fallout', 'year' => '2024', 'type' => 'series'],
                    ['title' => 'The Bear', 'slug' => 'the-bear', 'poster' => 'https://via.placeholder.com/300x450?text=The+Bear', 'year' => '2024', 'type' => 'series'],
                    ['title' => 'Severance', 'slug' => 'severance', 'poster' => 'https://via.placeholder.com/300x450?text=Severance', 'year' => '2023', 'type' => 'series'],
                    ['title' => 'Pachinko', 'slug' => 'pachinko', 'poster' => 'https://via.placeholder.com/300x450?text=Pachinko', 'year' => '2024', 'type' => 'series'],
                ],
                'popular' => [
                    ['title' => 'Avatar', 'slug' => 'avatar', 'poster' => 'https://via.placeholder.com/300x450?text=Avatar', 'year' => '2022', 'type' => 'movie'],
                    ['title' => 'Top Gun: Maverick', 'slug' => 'top-gun-maverick', 'poster' => 'https://via.placeholder.com/300x450?text=Top+Gun', 'year' => '2023', 'type' => 'movie'],
                    ['title' => 'Dune', 'slug' => 'dune', 'poster' => 'https://via.placeholder.com/300x450?text=Dune', 'year' => '2023', 'type' => 'movie'],
                    ['title' => 'Fast & Furious', 'slug' => 'fast-and-furious', 'poster' => 'https://via.placeholder.com/300x450?text=Fast', 'year' => '2023', 'type' => 'movie'],
                    ['title' => 'Mission Impossible', 'slug' => 'mission-impossible', 'poster' => 'https://via.placeholder.com/300x450?text=Mission+Impossible', 'year' => '2024', 'type' => 'movie'],
                    ['title' => 'Aquaman', 'slug' => 'aquaman', 'poster' => 'https://via.placeholder.com/300x450?text=Aquaman', 'year' => '2023', 'type' => 'movie'],
                ]
            ];

            if (!isset($demoData[$section])) {
                echo json_encode(['error' => 'Invalid section']);
                exit;
            }

            // Try to scrape real data first
            $urls = [
                'latest' => 'https://tv7.lk21official.cc/latest',
                'top_series' => 'https://tv7.lk21official.cc/top-series-today',
                'new_series' => 'https://tv7.lk21official.cc/latest-series',
                'popular' => 'https://tv7.lk21official.cc/populer'
            ];

            // Default to demo data for reliability
            $data = $demoData[$section];

            // Try to fetch real data if requested (optional)
            if (!$useDemo) {
                try {
                    $scrapeResult = @scrapeSpecificUrl($urls[$section], $page);
                    if (!empty($scrapeResult) && is_array($scrapeResult) && count($scrapeResult) > 0) {
                        $data = $scrapeResult;
                    }
                } catch (Exception $e) {
                    // Log error but use demo data as fallback
                    error_log('Scrape error for ' . $section . ': ' . $e->getMessage());
                }
            }

            // Force type for series sections
            if ($section === 'top_series' || $section === 'new_series') {
                foreach ($data as &$item) {
                    $item['type'] = 'series';
                }
            }

            echo json_encode(['data' => $data]);
        } catch (Exception $e) {
            error_log('home_section error: ' . $e->getMessage());
            echo json_encode(['error' => 'Failed to load section', 'data' => []]);
        }
        break;

    case 'clear_cache':
        $cacheDir = __DIR__ . '/cache';
        $count = 0;
        
        // Delete all files in cache directory
        if (is_dir($cacheDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                if ($file->isFile()) {
                    @unlink($file->getRealPath());
                    $count++;
                }
            }
        }
        echo json_encode(['success' => true, 'deleted' => $count]);
        break;

    case 'genre':
        $genre = $_GET['genre'] ?? 'action';
        $page = intval($_GET['page'] ?? 1);
        $result = scrapeByGenre($genre, $page);
        echo json_encode([
            'data' => $result['movies'],
            'total_pages' => $result['total_pages'],
            'current_page' => $result['current_page'],
            'genre' => $result['genre']
        ]);
        break;

    case 'type':
        $type = $_GET['type'] ?? 'movie';
        $page = intval($_GET['page'] ?? 1);

        // Use home_section endpoint which returns actual data
        if ($type === 'series' || $type === 'tv') {
            // Get series from new_series or top_series section
            $allResults = scrapeSpecificUrl('https://tv7.lk21official.cc/latest-series', $page);
            if (!$allResults || !is_array($allResults)) {
                $allResults = [];
            }
            // Force type to series
            foreach ($allResults as &$item) {
                $item['type'] = 'series';
            }
        } else {
            // Get movies from latest
            $allResults = scrapeSpecificUrl('https://tv7.lk21official.cc/latest', $page);
            if (!$allResults || !is_array($allResults)) {
                $allResults = [];
            }
            // Filter only movies
            $allResults = array_filter($allResults, function($item) {
                $itemType = strtolower($item['type'] ?? 'movie');
                return $itemType === 'movie';
            });
            $allResults = array_values($allResults);
        }

        echo json_encode([
            'data' => $allResults,
            'total_pages' => 10,
            'current_page' => $page,
            'type' => $type
        ]);
        break;

    case 'country':
        $country = $_GET['country'] ?? '';
        $page = intval($_GET['page'] ?? 1);
        if (empty($country)) {
            echo json_encode(['error' => 'Negara tidak ditemukan']);
            exit;
        }
        $result = scrapeByCountry($country, $page);
        echo json_encode([
            'data' => $result['movies'],
            'total_pages' => $result['total_pages'],
            'current_page' => $result['current_page'],
            'country' => $result['country']
        ]);
        break;

    default:
        echo json_encode(['error' => 'Action tidak valid']);
        break;
}
?>
