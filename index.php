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
$file_extension = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
$parts = explode('?', $file_extension);
$file_extension = reset($parts);

// Parse CloudFlare's image resizing format
function parse_cf_options($uri) {
    // Pattern to extract the part of the URI that contains the options
    $pattern = '/\/cdn-cgi\/image\/([^\/]+)/';
    $matches = [];

    if (preg_match($pattern, $uri, $matches)) {
        // Extract the options part
        $optionsPart = $matches[1];

        // Split the options by comma
        $optionsList = explode(',', $optionsPart);

        $options = [];
        foreach ($optionsList as $option) {
            // Split each option by '=' to get key and value
            list($key, $value) = explode('=', $option);

            // Add the option to the result array
            $options[$key] = $value;
        }

        return $options;
    }

    // Return empty array if no options are found
    return [];
}

// Removes the CloudFlare options string from a URL
function remove_cf_portion($url) {
    $pattern = '/\/cdn-cgi\/image\/[^\/]+\//';
    $cleaned_url = preg_replace($pattern, '/', $url);
    return $cleaned_url;
}

$cf_options = parse_cf_options($uri);
if (count($cf_options) > 0) {
    $uri_query_string = "";
    $query_parts = [];
    // Set the size and quality settings with the CloudFlare options
    if (isset($cf_options['width'])) {
        $dyn_size = intval($cf_options['width']);
        $_GET["s"] = $dyn_size;
    }
    if (isset($cf_options['quality'])) {
        $dyn_quality = intval($cf_options['quality']);
        $_GET["q"] = $dyn_quality;
    }
    // Remove the CloudFlare options from the URI so that the original file can be found
    $uri = remove_cf_portion($uri);
}

// Processed files are saved in different location
$prepend_path = "";
if (in_array($file_extension, ['jpg', 'jpeg', 'png'])) {
    if (isset($_GET["s"])) $prepend_path .= "/s/" . $dyn_size;
    if (isset($_GET["q"])) $prepend_path .= "/q/" . $dyn_quality;
}

// Define the local and remote file paths
$local_file_path = $local_path . $prepend_path . $uri;
$remote_file_path = $origin_url . $uri;

// Check if the file exists locally
if (!file_exists($local_file_path)) {
    // Download the file from the origin server
    $file_content = file_get_contents($remote_file_path);
    if ($file_content === false) {
        header("HTTP/1.0 404 Not Found");
        exit('File not found');
    }

    // Set the local directory and create it if it doesn't exist
    $directory = dirname($local_file_path);
    if (!is_dir($directory)) {
        // Attempt to create the directory
        if (!mkdir($directory, 0755, true)) {
            die('Failed to create directories...');
        }
    }

    // If the file is an image and it's to be resized or compressed
    if (in_array($file_extension, ['jpg', 'jpeg', 'png']) && (isset($_GET["s"]) || isset($_GET["q"]))) {
        $image = imagecreatefromstring($file_content);
        $file_content = null;
        if (!$image) {
            die('Invalid image file');
        }

        // Apply resizing
        if (isset($dyn_size)) {
            // Function resizes an image proportionally based on the max size in any direction
            function resize_image ($image, $size, $transparency = false) {
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
                if ($transparency) {
                    // Preserve PNG transparency
                    imagealphablending($new_image, false);
                    imagesavealpha($new_image, true);
                    $transparent = imagecolorallocatealpha($new_image, 0, 0, 0, 127);
                    imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
                }
                imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                
                // Return the new image
                return $new_image;
            }
            $image = resize_image($image, $dyn_size, $file_extension == 'png' ? true : false);
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
        // Clear file status cache, without this the image won't display on first load
        clearstatcache(true, $local_file_path);
    } else {
        // For non-image files or SVG, just save the file as is
        file_put_contents($local_file_path, $file_content);
        $file_content = null;
        clearstatcache(true, $local_file_path);
    }
}

// Set CORS headers
header("Access-Control-Allow-Origin: *");

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