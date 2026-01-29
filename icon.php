<?php
// Simple Icon Generator
// Generates PNG icons on-the-fly

$size = isset($_GET['size']) ? (int)$_GET['size'] : 192;
$size = min(max($size, 16), 1024); // Limit between 16-1024

// Create image
$image = imagecreatetruecolor($size, $size);

// Colors - MovieTube theme (Netflix-like red)
$bgColor = imagecolorallocate($image, 229, 9, 20); // #E50914
$textColor = imagecolorallocate($image, 255, 255, 255);

// Fill background
imagefilledrectangle($image, 0, 0, $size, $size, $bgColor);

// Add "MT" text (MovieTube)
$fontSize = $size * 0.4;
$fontFile = null;

// Try to use a system font, fallback to built-in
if (function_exists('imagettftext')) {
    $possibleFonts = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/System/Library/Fonts/Helvetica.ttc',
        'C:\Windows\Fonts\arial.ttf'
    ];

    foreach ($possibleFonts as $font) {
        if (file_exists($font)) {
            $fontFile = $font;
            break;
        }
    }
}

if ($fontFile) {
    // Use TrueType font
    $bbox = imagettfbbox($fontSize, 0, $fontFile, 'MT');
    $textWidth = abs($bbox[4] - $bbox[0]);
    $textHeight = abs($bbox[5] - $bbox[1]);

    $x = ($size - $textWidth) / 2;
    $y = ($size + $textHeight) / 2;

    imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontFile, 'MT');
} else {
    // Fallback: Draw a play button icon (triangle)
    $centerX = $size / 2;
    $centerY = $size / 2;
    $triangleSize = $size * 0.4;

    $points = [
        $centerX - $triangleSize * 0.3, $centerY - $triangleSize * 0.5,
        $centerX - $triangleSize * 0.3, $centerY + $triangleSize * 0.5,
        $centerX + $triangleSize * 0.5, $centerY
    ];

    imagefilledpolygon($image, $points, 3, $textColor);
}

// Output as PNG
header('Content-Type: image/png');
imagepng($image, null, 9);
imagedestroy($image);
