<?php
/**
 * Download Links Extractor
 * File untuk menampilkan link download berdasarkan parameter URL film
 */

// Production mode: Log errors but don't display
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide errors from users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Set timezone ke Jakarta
date_default_timezone_set('Asia/Jakarta');

// Load token validator (DISABLED)
// require_once __DIR__ . '/token_validator.php';

// Fungsi helper untuk mendapatkan waktu Jakarta
function getJakartaTime($format = 'Y-m-d H:i:s') {
    return date($format);
}

// Log semua akses ke dl.php
// Path ke bot_log.txt di folder bioskopkeren.biz.id (satu server dengan direktori berbeda)
$logFile = __DIR__ . '/logs/bot_log.txt';
$accessLog = getJakartaTime() . " - dl.php accessed with params: " . json_encode($_GET) . "\n";
file_put_contents($logFile, $accessLog, FILE_APPEND | LOCK_EX);

// ===================================
// ====== TOKEN VALIDATION (DISABLED) ======
// ===================================

// Token validation removed as requested
$token = ''; 

// ===================================
// ====== END TOKEN VALIDATION ======
// ===================================

// Cek parameter URL
if (!isset($_GET['url']) || empty($_GET['url'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Parameter URL diperlukan',
        'usage' => 'dl.php?url=film-slug&token=xxx&user=xxx'
    ]);
    exit;
}

$encodedSlug = $_GET['url'];

// Log parameter yang diterima
$paramLog = getJakartaTime() . " - Parameters: url={$encodedSlug}\n";
file_put_contents($logFile, $paramLog, FILE_APPEND | LOCK_EX);

// Log untuk debug
file_put_contents($logFile, getJakartaTime() . " - dl.php processing request\n", FILE_APPEND | LOCK_EX);

// Decode slug dari base64 REMOVED
$movieSlug = $encodedSlug; // No decoding
// $movieSlug = base64_decode($encodedSlug);

// Cek apakah ini adult content URL, series URL, atau movie slug
$isAdultContent = false;
$isSeriesEpisode = false;
$movieUrl = '';
$contentType = $_GET['type'] ?? 'movie';

if ($contentType === 'series' || $contentType === 'tv') {
    $isSeriesEpisode = true;
}

if (filter_var($movieSlug, FILTER_VALIDATE_URL)) {
    // Ini adalah URL lengkap (adult content atau series episode)
    $movieUrl = $movieSlug;
    
    // Deteksi apakah ini series dari nontondrama.my
    if (strpos($movieUrl, 'nontondrama.my') !== false || strpos($movieUrl, 'tv1.nontondrama') !== false || strpos($movieUrl, 'tv3.nontondrama') !== false) {
        $isSeriesEpisode = true;
        file_put_contents($logFile, getJakartaTime() . " - Series episode URL detected: {$movieUrl}\n", FILE_APPEND | LOCK_EX);
    } elseif (strpos($movieUrl, 'lk21official') !== false || strpos($movieUrl, 'dunia21') !== false || strpos($movieUrl, 'layarkaca21') !== false) {
         // Ini adalah URL Film LK21 valid, bukan adult content
         $isAdultContent = false; 
         file_put_contents($logFile, getJakartaTime() . " - Full Movie URL detected: {$movieUrl}\n", FILE_APPEND | LOCK_EX);
    } else {
        // Adult content atau URL lainnya
        $isAdultContent = true;
        file_put_contents($logFile, getJakartaTime() . " - Adult content URL detected: {$movieUrl}\n", FILE_APPEND | LOCK_EX);
    }
} else {
    // Ini adalah movie slug biasa
    if (empty($movieSlug) || !preg_match('/^[a-zA-Z0-9\-]+$/', $movieSlug)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid encoded URL',
            'encoded' => $encodedSlug
        ]);
        exit;
    }
    
    // Buat URL lengkap dari slug
    if ($isSeriesEpisode) {
        // Series domain (nontondrama)
        $movieUrl = 'https://tv3.nontondrama.my/' . $movieSlug;
    } else {
        // Movie domain (lk21)
        $movieUrl = 'https://tv7.lk21official.cc/' . $movieSlug;
    }
    file_put_contents($logFile, getJakartaTime() . " - Movie URL ({$contentType}): {$movieUrl}\n", FILE_APPEND | LOCK_EX);
}

// Scrape halaman film
function scrapeMoviePage($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$html) {
        return false;
    }
    
    return $html;
}

// Extract series episode player URL (sama seperti movie)
function extractSeriesPlayerUrl($html) {
    global $logFile;
    
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML($html);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    // 1. Ambil dari ul#player-list (Standard LK21/Nontondrama)
    $playerLinks = $xpath->query('//ul[@id="player-list"]//a');
    file_put_contents($logFile, getJakartaTime() . " - Found " . $playerLinks->length . " player links\n", FILE_APPEND | LOCK_EX);
    
    if ($playerLinks->length > 0) {
        // Ambil link pertama (biasanya P2P)
        $firstLink = $playerLinks->item(0);
        $dataUrl = $firstLink->getAttribute('data-url');
        $href = $firstLink->getAttribute('href');
        $dataServer = $firstLink->getAttribute('data-server');
        
        $playerUrl = !empty($dataUrl) ? $dataUrl : $href;
        
        file_put_contents($logFile, getJakartaTime() . " - Player URL: {$playerUrl} (server: {$dataServer})\n", FILE_APPEND | LOCK_EX);
        if (!empty($playerUrl)) return $playerUrl;
    }
    
    // 2. Ambil dari select#player-select (Sering ada di series)
    $selectOptions = $xpath->query('//select[@id="player-select"]//option');
    if ($selectOptions->length > 0) {
        foreach ($selectOptions as $option) {
            $val = $option->getAttribute('value');
            if (!empty($val) && strpos($val, 'http') === 0) {
                file_put_contents($logFile, getJakartaTime() . " - Found player from select: {$val}\n", FILE_APPEND | LOCK_EX);
                return $val;
            }
        }
    }
    
    // 3. Ambil dari div.player-series iframe
    $seriesIframe = $xpath->query('//div[contains(@class, "player-series")]//iframe');
    if ($seriesIframe->length > 0) {
        $src = $seriesIframe->item(0)->getAttribute('src');
        if (!empty($src)) {
            file_put_contents($logFile, getJakartaTime() . " - Found player-series iframe: {$src}\n", FILE_APPEND | LOCK_EX);
            return $src;
        }
    }
    
    // 4. Fallback: Cari iframe player-embed
    $iframeNodes = $xpath->query('//iframe[@id="player-embed"]');
    if ($iframeNodes->length > 0) {
        $iframeSrc = $iframeNodes->item(0)->getAttribute('src');
        if (!empty($iframeSrc)) {
            file_put_contents($logFile, getJakartaTime() . " - Found iframe player: {$iframeSrc}\n", FILE_APPEND | LOCK_EX);
            return $iframeSrc;
        }
    }
    
    file_put_contents($logFile, getJakartaTime() . " - No player URL found\n", FILE_APPEND | LOCK_EX);
    return null;
}

// Extract download links
function extractDownloadLinks($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML($html);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    $downloadLinks = [];
    
    // Cari div player-options
    $playerOptions = $xpath->query('//div[@class="player-options"]');
    
    if ($playerOptions->length > 0) {
        // Cari semua link dalam player-list
        $playerLinks = $xpath->query('.//ul[@id="player-list"]//a');
        
        foreach ($playerLinks as $link) {
            $href = $link->getAttribute('href');
            $dataUrl = $link->getAttribute('data-url');
            $dataServer = $link->getAttribute('data-server');
            $text = trim($link->textContent);
            
            if (!empty($dataUrl) && !empty($dataServer)) {
                $downloadLinks[] = [
                    'server' => strtoupper($dataServer),
                    'name' => $text,
                    'url' => $dataUrl,
                    'iframe_url' => $href
                ];
            }
        }
        
        // Cari juga dari select option
        $selectOptions = $xpath->query('//select[@id="player-select"]//option');
        
        foreach ($selectOptions as $option) {
            $value = $option->getAttribute('value');
            $dataServer = $option->getAttribute('data-server');
            $text = trim($option->textContent);
            
            if (!empty($value) && !empty($dataServer)) {
                // Cek apakah sudah ada di array
                $exists = false;
                foreach ($downloadLinks as $existing) {
                    if ($existing['url'] === $value) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    $downloadLinks[] = [
                        'server' => strtoupper($dataServer),
                        'name' => $text,
                        'url' => $value,
                        'iframe_url' => ''
                    ];
                }
            }
        }
    }
    
    // Urutkan berdasarkan prioritas: P2P → TURBOVIP → CAST → HYDRAX
    $priority = ['P2P', 'TURBOVIP', 'CAST', 'HYDRAX'];
    $sortedLinks = [];
    
    foreach ($priority as $server) {
        foreach ($downloadLinks as $link) {
            if ($link['server'] === $server) {
                $sortedLinks[] = $link;
                break;
            }
        }
    }
    
    // Tambahkan link lain yang tidak ada di priority
    foreach ($downloadLinks as $link) {
        if (!in_array($link['server'], $priority)) {
            $sortedLinks[] = $link;
        }
    }
    
    // Kembalikan hanya 1 link (yang pertama/terprioritas)
    return !empty($sortedLinks) ? [$sortedLinks[0]] : [];
}

// Handle adult content, series episode, or movie content
if ($isAdultContent) {
    // Untuk adult content, langsung redirect ke embed URL
    file_put_contents($logFile, getJakartaTime() . " - Redirecting to adult content embed: {$movieUrl}\n", FILE_APPEND | LOCK_EX);
    header('Location: ' . $movieUrl);
    exit;
} elseif ($isSeriesEpisode) {
    // Untuk series episode, scrape halaman episode (sama seperti movie)
    file_put_contents($logFile, getJakartaTime() . " - Processing series episode: {$movieUrl}\n", FILE_APPEND | LOCK_EX);
    
    $html = scrapeMoviePage($movieUrl);

    if ($html === false) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Gagal mengambil halaman episode',
            'url' => $movieUrl
        ]);
        exit;
    }

    // Extract player URL dari halaman episode (gunakan fungsi yang sama dengan movie)
    $playerUrl = extractSeriesPlayerUrl($html);

    if (empty($playerUrl)) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Tidak ada player URL ditemukan',
            'url' => $movieUrl,
            'message' => 'Episode mungkin belum tersedia atau struktur halaman berubah'
        ]);
        exit;
    }

    // Log player URL
    file_put_contents($logFile, getJakartaTime() . " - Series player URL found: {$playerUrl}\n", FILE_APPEND | LOCK_EX);

    // Khusus untuk P2P, redirect ke external proxy server
    if (strpos($playerUrl, 'https://playeriframe.sbs/iframe/p2p/') === 0) {
        // Extract ID dari URL P2P
        $id = substr($playerUrl, strlen('https://playeriframe.sbs/iframe/p2p/'));
        
        // Redirect ke external proxy server
        $finalUrl = "https://lk21.apivalidasi.my.id/pemutar-video?id=" . $id;
        
        file_put_contents($logFile, getJakartaTime() . " - Redirecting to external proxy with ID: {$id}\n", FILE_APPEND | LOCK_EX);
        
        header('Location: ' . $finalUrl);
    } else {
        // Untuk server lain (CAST, TURBOVIP, HYDRAX), redirect ke external proxy server
        $playerRedirect = "https://lk21.apivalidasi.my.id/pemutar-video?u=" . urlencode($playerUrl);
        
        file_put_contents($logFile, getJakartaTime() . " - Redirecting to external proxy (series): {$playerRedirect}\n", FILE_APPEND | LOCK_EX);
        header('Location: ' . $playerRedirect);
    }
    exit;
} else {
    // Untuk movie content, scrape halaman film
    $html = scrapeMoviePage($movieUrl);

    if ($html === false) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Gagal mengambil halaman film',
            'url' => $movieUrl
        ]);
        exit;
    }

    // Extract download links
    $downloadLinks = extractDownloadLinks($html);

    if (empty($downloadLinks)) {
        echo json_encode([
            'error' => 'Tidak ada link download ditemukan',
            'url' => $movieUrl,
            'message' => 'Film mungkin belum tersedia atau struktur halaman berubah'
        ]);
        exit;
    }

    // Set header untuk JSON
    header('Content-Type: application/json');

    // Tampilkan hasil - redirect ke download link
    if (!empty($downloadLinks)) {
        $link = $downloadLinks[0];
        
        // Khusus untuk P2P, redirect ke external proxy server
        if ($link['server'] === 'P2P') {
            // Extract ID dari URL asli
            $originalUrl = $link['url'];
            if (strpos($originalUrl, 'https://playeriframe.sbs/iframe/p2p/') === 0) {
                $id = substr($originalUrl, strlen('https://playeriframe.sbs/iframe/p2p/'));
                
                // Redirect ke external proxy server
                $finalUrl = "https://lk21.apivalidasi.my.id/pemutar-video?id=" . $id;
                header('Location: ' . $finalUrl);
                exit;
            } else {
                 // Non-standard P2P, use generic player
                 $downloadUrl = $originalUrl;
            }
        } else {
            $downloadUrl = $link['url'];
        }
        
        // Redirect semua stream ke external proxy server
        $playerRedirect = "https://lk21.apivalidasi.my.id/pemutar-video?u=" . urlencode($downloadUrl);
        
        // Redirect ke external proxy
        header('Location: ' . $playerRedirect);
        exit;
    } else {
        // Jika tidak ada download link, tampilkan error
        http_response_code(404);
        echo json_encode([
            'error' => 'Tidak ada link download tersedia',
            'url' => $movieUrl,
            'slug' => $movieSlug
        ]);
    }
}
?>