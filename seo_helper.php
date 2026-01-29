<?php
// seo_helper.php

function generateSchema($type, $data) {
    $schema = [];
    
    if ($type === 'WebSite') {
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "WebSite",
            "name" => "MovieClub",
            "url" => $data['url'],
            "potentialAction" => [
                "@type" => "SearchAction",
                "target" => $data['url'] . "search/{search_term_string}",
                "query-input" => "required name=search_term_string"
            ]
        ];
    } elseif ($type === 'Movie' || $type === 'TVSeries') {
        $schema = [
            "@context" => "https://schema.org",
            "@type" => $type === 'TVSeries' ? "TVSeries" : "Movie",
            "name" => $data['title'],
            "description" => $data['synopsis'] ?? "Nonton " . $data['title'] . " Sub Indo Gratis",
            "image" => $data['poster'],
            "datePublished" => $data['year'] ?? date('Y'),
            "offers" => [
                "@type" => "Offer",
                "availability" => "https://schema.org/InStock",
                "price" => "0",
                "priceCurrency" => "IDR"
            ]
        ];
        
        if (isset($data['rating'])) {
            $schema['aggregateRating'] = [
                "@type" => "AggregateRating",
                "ratingValue" => (string)$data['rating'],
                "bestRating" => "10",
                "worstRating" => "1",
                "ratingCount" => "100" // Placeholder
            ];
        }
    } elseif ($type === 'VideoObject') {
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "VideoObject",
            "name" => $data['title'],
            "description" => $data['synopsis'] ?? "Streaming " . $data['title'],
            "thumbnailUrl" => [
                $data['poster']
            ],
            "uploadDate" => date('c'), // Current time as placeholder
            "duration" => "PT2H", // Placeholder ISO 8601 duration
            "embedUrl" => $data['player_url'] ?? ""
        ];
    }

    return '<script type="application/ld+json">' . json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</script>';
}
?>
