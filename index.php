<?php

// Configuration
$remote_domain = getenv('ORIGIN_URL'); // Potentially change to environment variable
$local_path = '/path/to/local/storage/';
$max_size = 8192; // Max width in pixels

// Parse the request
$uri = $_SERVER['REQUEST_URI'];
$file_extension = strtolower(pathinfo($uri, PATHINFO_EXTENSION));

// Get the dynamic request parameters, intval used as basic sanitization
$dyn_size = isset($_GET["s"]) ? intval($_GET["s"]) : null;
$dyn_quality = isset($_GET["q"]) ? intval($_GET["q"]) : 75;
if ($dyn_size > $max_size) $dyn_size = $max_size;

// Processed files are saved in different location
$prepend_path = "";
if (isset($dyn_size)) $prepend_path.= "/s/" . $dyn_size;
if (isset($dyn_quality)) $prepend_path.= "/q/" . $dyn_quality;

// Define the local and remote file paths
$local_file_path = $local_path . $prepend_path . $uri;
$remote_file_path = 'http://' . $remote_domain . $uri;

// Function resizes an image proportionally based to the max size in any direction
function resize_image ($image, $size) {
    // Determine pixel size of original image
    $width = imagesx($image); 
    $height = imagesy($image);

    // Determine aspect ratio of image
    $aspect_ratio = $width/$height;

    // Determine if image is landscape or portrait
    $new_width = ($width > $height) ? $size : floor($new_height*$aspect_ratio);
    $new_height = ($width > $height) ? floor($new_width/$aspect_ratio) : $size;

    // Create a new image
    $new_image = imagecreatetruecolor($new_width, $new_height);
    imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // Return the new image
    return $new_image;
}

// Check if the file exists locally and process if necessary
if (!file_exists($local_file_path)) {
    $file_content = file_get_contents($remote_file_path);
    if ($file_content === false) {
        header("HTTP/1.0 404 Not Found");
        exit('File not found');
    }

    if ($file_extension == 'jpg' || $file_extension == 'jpeg' || $file_extension == 'png') {
        $image = imagecreatefromstring($file_content);
        if (!$image) {
            die('Invalid image file');
        }

        // Apply resizing
        if (isset($dyn_size)) {
            $image = resize_image($image, $dyn_size);
        }

        // Save the processed image
        switch ($file_extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($image, $local_file_path, $dyn_quality); // Adjust quality as needed
                break;
            case 'png':
                imagepng($image, $local_file_path); // Adjust compression as needed
                break;
        }
        imagedestroy($image);
    } else {
        // For non-image files or SVG, just save the file as is
        file_put_contents($local_file_path, $file_content);
    }
}

// Serve the file with the correct content type
switch ($file_extension) {
    case 'jpg':
    case 'jpeg':
        header('Content-Type: image/jpeg');
        break;
    case 'png':
        header('Content-Type: image/png');
        break;
    case 'svg':
        header('Content-Type: image/svg+xml');
        break;
    default:
        // Default or add more types as needed
        header('Content-Type: application/octet-stream');
        break;
}

readfile($local_file_path);
?>
