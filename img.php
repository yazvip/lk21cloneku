<?php
// Tetapkan batas waktu untuk mencegah skrip berjalan terlalu lama
set_time_limit(30);

// Set CORS dan security headers terlebih dahulu
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Cross-Origin-Embedder-Policy: unsafe-none');
header('Cross-Origin-Opener-Policy: unsafe-none');
header('Cross-Origin-Resource-Policy: cross-origin');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Ambil URL gambar dari parameter 'l'
$imageUrl = isset($_GET['l']) ? $_GET['l'] : null;

// Validasi dasar: pastikan URL tidak kosong dan formatnya valid
if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
    // Jika tidak valid, kirim header 400 Bad Request dan keluar
    header("HTTP/1.1 400 Bad Request");
    echo "URL gambar tidak valid.";
    exit;
}

// Inisialisasi sesi cURL
$ch = curl_init();

// Siapkan header kustom untuk meniru permintaan browser
// Ini sangat penting untuk melewati beberapa jenis perlindungan hotlink.
$headers = [
    'Origin: https://poster.lk21.party',
    'Referer: https://poster.lk21.party/', // Menggunakan domain utama sebagai referer adalah praktik umum.
    'Priority: u=4, i',
    'Accept: image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.9',
    'Accept-Encoding: gzip, deflate, br',
    'DNT: 1',
    'Connection: keep-alive',
    'Upgrade-Insecure-Requests: 1'
];

// Atur opsi cURL
// Variables to track state
$isValid = false;
$headersSent = false;

// Atur opsi cURL
curl_setopt($ch, CURLOPT_URL, $imageUrl); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Matikan return transfer karena kita stream manual
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Sedikit lebih lama untuk streaming
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

// HEADER FUNCTION: Tangkap status dan header penting
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$isValid) {
    $len = strlen($header);
    $trimHeaders = trim($header);
    
    if (empty($trimHeaders)) return $len;

    // Cek Status Code
    if (stripos($trimHeaders, 'HTTP/') === 0) {
        if (strpos($trimHeaders, '200') !== false) {
            $isValid = true;
        } else {
            $isValid = false;
        }
    }
    
    // Jika valid, teruskan header Content-Type dan Content-Length
    if ($isValid) {
        if (stripos($trimHeaders, 'Content-Type:') === 0 || stripos($trimHeaders, 'Content-Length:') === 0) {
            header($trimHeaders);
        }
    }
    
    return $len;
});

// WRITE FUNCTION: Stream data ke user
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$isValid, &$headersSent) {
    if (!$isValid) return strlen($chunk); // Buang data jika tidak valid (misal 404 page body)
    
    echo $chunk;
    $headersSent = true;
    return strlen($chunk);
});

// Jalankan permintaan cURL
curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

// Jika gagal atau tidak valid, tampilkan placeholder
if (!$isValid || $err) {
    // Hapus header yang mungkin sudah di-queue (jika belum terkirim outputnya)
    if (!$headersSent) {
        header_remove(); 
        header('Location: https://placehold.co/500x750/141414/FFFFFF?text=Image+Failed');
        exit;
    }
}
?>
