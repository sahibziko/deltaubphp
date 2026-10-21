<?php
$bgUrl = 'https://images.unsplash.com/photo-1511919884226-fd3cad34687c?auto=format&fit=crop&w=600&h=600&q=80';
$imgData = @file_get_contents($bgUrl);
$srcImg = @imagecreatefromstring($imgData);
$canvas = imagecreatetruecolor(600, 600);
imagecopyresampled($canvas, $srcImg, 0, 0, 0, 0, 600, 600, imagesx($srcImg), imagesy($srcImg));
imagedestroy($srcImg);

$fontPath = __DIR__ . '/BebasNeue.ttf';
$currentTime = '04:36';
$fontSize = 120;

$bbox = imagettfbbox($fontSize, 0, $fontPath, $currentTime);
$textWidth = abs($bbox[4] - $bbox[0]);
$textHeight = abs($bbox[5] - $bbox[1]);
$textX = (int) ((600 - $textWidth) / 2);
$textY = (int) ((600 + $textHeight) / 2);

for ($dx = -3; $dx <= 3; $dx++) {
    for ($dy = -3; $dy <= 3; $dy++) {
        if ($dx !== 0 || $dy !== 0) {
            $outline = imagecolorallocatealpha($canvas, 0, 0, 0, 90);
            imagettftext($canvas, $fontSize, 0, $textX + $dx, $textY + $dy, $outline, $fontPath, $currentTime);
        }
    }
}
$shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 55);
imagettftext($canvas, $fontSize, 0, $textX + 5, $textY + 6, $shadow, $fontPath, $currentTime);

$white = imagecolorallocate($canvas, 255, 255, 255);
imagettftext($canvas, $fontSize, 0, $textX, $textY, $white, $fontPath, $currentTime);

imagejpeg($canvas, __DIR__ . '/sample_preview.jpg', 95);
echo "Preview generated successfully at " . __DIR__ . "/sample_preview.jpg\n";
