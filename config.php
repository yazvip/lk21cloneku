<?php
/**
 * Application Configuration
 * Optimized settings for MovieTube streaming platform
 */

return [
    // === SITE SETTINGS ===
    'base_url'    => 'https://ngopi.web.id/',
    'webhook_url' => 'https://ngopi.web.id',
    
    // === SCRAPER SETTINGS ===
    'search_url'  => 'https://gudangvape.com/search.php?s=',
    'max_results' => 10,
    'timeout'     => 30,  // Reduced for faster response
    'user_agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    
    // === CORS/ALLOWED ORIGINS ===
    'allowed_domains' => [
        'ngopi.web.id',
        'localhost',
        '127.0.0.1'
    ],
];
