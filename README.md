# dynimage

A script that returns a file from another domain with optional resizing and compression for images. Assets are then stored on the server, speeding up subsequent requests. This is used to dynamically resize images without needing to save multiple sizes in the application logic.

## Requirements
- Server configured to route all requests to index.php
- Environment variable for ORIGIN_URL with the value of the origin URL.

# Options

All options are set via URL parameter.

- s = An integer representing the desired size of the longest edge of the image.
- q = An integer from 0 to 100, defaults to 75 for JPG.

## Usage

Given an image file on the origin:
```
https://origin.cdn.com/image.jpg
```

A request to the following URL on your dynimage server will return a version resized to 1200px on its longest edge and 60 percent quality. 
```
https://dyn.example.com/image.jpg?s=1200&q=60
```
