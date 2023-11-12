<?php

// Configuration
$origin_url = getenv('ORIGIN_URL'); // Base URL for origin server
$local_path = getenv('LOCAL_PATH'); // Base path to save files locally
$max_size = 8192; // Max width in pixels

// Get the dynamic request parameters, intval used as basic sanitization
$dyn_size = isset($_GET["s"]) ? intval($_GET["s"]) : null;
$dyn_quality = isset($_GET["q"]) ? intval($_GET["q"]) : 75;
if ($dyn_size > $max_size) $dyn_size = $max_size;

// Parse the request
$uri = $_SERVER['REQUEST_URI'];
$uri = reset(explode('?', $uri)); // Remove query string
$file_extension = strtolower(pathinfo($uri, PATHINFO_EXTENSION));

// Processed files are saved in different location
$prepend_path = "";
if (in_array($file_extension, ['jpg', 'jpeg', 'png'])) {
    if (isset($dyn_size)) $prepend_path .= "/s/" . $dyn_size;
    if (isset($dyn_quality)) $prepend_path .= "/q/" . $dyn_quality;
}

// Define the local and remote file paths
$local_file_path = $local_path . $prepend_path . $uri;
$remote_file_path = $origin_url . $uri;

// Set the local directory and create it if it doesn't exist
$directory = dirname($local_file_path);
if (!is_dir($directory)) {
    // Attempt to create the directory
    if (!mkdir($directory, 0755, true)) {
        die('Failed to create directories...');
    }
}

// Function resizes an image proportionally based on the max size in any direction
function resize_image ($image, $size) {
    // Get pixel dims of original image
    $width = imagesx($image); 
    $height = imagesy($image);

    // Determine aspect ratio of image
    $aspect_ratio = $width/$height;

    // Determine if image is landscape or portrait
    $new_width = ($width > $height) ? $size : floor($size*$aspect_ratio);
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
        // Clear file status cache, without this the image won't display on first attempt
        clearstatcache(true, $local_file_path);
    } else {
        // For non-image files or SVG, just save the file as is
        file_put_contents($local_file_path, $file_content);
    }
}

// Set CORS headers
/*
    Add CORS logic here
*/

// Serve the file with the correct content type
switch ($file_extension) {
    case 'jpg':
        case 'jpeg':
        header('Content-Type: image/jpeg');
        break;
    case 'png':
        header('Content-Type: image/png');
        break;
    case 'js':
        header('Content-Type: application/javascript');
        break;
    case 'html':
        header('Content-Type: text/html');
        break;
    case 'css':
        header('Content-Type: text/css');
        break;
    case 'mp4':
        header('Content-Type: video/mp4');
        break;
    case 'gif':
        header('Content-Type: image/gif');
        break;
    case 'svg':
        header('Content-Type: image/svg+xml');
        break;
    case 'json':
        header('Content-Type: application/json');
        break;
    case 'xml':
        header('Content-Type: application/xml');
        break;
    case 'txt':
        header('Content-Type: text/plain');
        break;
    case 'pdf':
        header('Content-Type: application/pdf');
        break;
    default:
        // Default or add more types as needed
        header('Content-Type: application/octet-stream');
        break;
}

readfile($local_file_path);

?>