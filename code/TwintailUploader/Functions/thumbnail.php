<?php
namespace TwintailUploader\Functions;

//generate thumbnail
function thumbnailImage(string $imagePath, string $thumbPath, int $w, int $h): void {
    // Validate source file exists
    if (!file_exists($imagePath)) {
        error_log("Thumbnail source file not found: $imagePath");
        return;
    }

    // Quality and dimensions settings
    $maxWidth = 200;
    $maxHeight = 95;
             
    $imageData = file_get_contents($imagePath);
    if ($imageData === false) {
        error_log("Failed to read image file: $imagePath");
        return;
    }

    $image = imagecreatefromstring($imageData);
    if ($image === false) {
        error_log("Failed to create image from string: $imagePath (invalid image format)");
        return;
    }
      
    $width = imagesx($image);
    $height = imagesy($image);
      
    $newWidth = $w;
    $newHeight = $h;
      
    if ($width > $maxWidth || $height > $maxHeight) {
        $aspectRatio = $width / $height;
      
        if ($width > $height) {
            $newWidth = $maxWidth;
            $newHeight = $maxWidth / $aspectRatio;
        } else {
            $newHeight = $maxHeight;
            $newWidth = $maxHeight * $aspectRatio;
        }
    }
      
    // Create a new image
    $thumbnail = imagecreatetruecolor((int)$newWidth, (int)$newHeight);
    
    // Resize the image to the new dimensions
    imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, (int)$newWidth, (int)$newHeight, $width, $height);
      
    // create thumb
    if (!imagejpeg($thumbnail, $thumbPath, 80)) {
        error_log("Failed to save thumbnail: $thumbPath");
    }
}

function thumbnailVideo(string $videoPath, string $thumbPath, int $width = 0, int $height = 0): string {
    // Validate source file exists
    if (!file_exists($videoPath)) {
        error_log("Thumbnail source video file not found: $videoPath");
        return '';
    }

    // Generate a temp thumbnail path if not provided
    if (empty($thumbPath)) {
        $thumbPath = tempnam(sys_get_temp_dir(), 'thumbnail') . ".jpg";
    }

    // Prepare scale filter if width or height is provided
    $scaleFilter = '';
    if ($width > 0 || $height > 0) {
        $w = $width > 0 ? $width : -1;
        $h = $height > 0 ? $height : -1;
        $scaleFilter = " -vf " . escapeshellarg("scale={$w}:{$h}:force_original_aspect_ratio=decrease") . " ";
    }

    // Ensure the environment variable is included in the command
    $ffmpegCommand = "LD_LIBRARY_PATH=/usr/local/lib:/usr/X11R6/lib ffmpeg -i " . escapeshellarg($videoPath) .
                     " -vframes 1" . $scaleFilter . " " . escapeshellarg($thumbPath) . " 2>&1";

    $output = [];
    $returnCode = 0;
    exec($ffmpegCommand, $output, $returnCode);

    if ($returnCode !== 0) {
        error_log("FFmpeg failed to generate video thumbnail: " . implode("\n", $output));
        return '';
    }

    return $thumbPath;
}