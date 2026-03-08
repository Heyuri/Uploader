<?php

// Autoloader function
function up_autoloader($class) {
    $prefix = 'HeyuriUploader\\'; 
    $base_dir = __DIR__ . '/code/HeyuriUploader/';
    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // If class doesn't use the prefix, return false
        return;
    }

    // Get the relative class name (without the namespace prefix)
    $relative_class = substr($class, $len);

    // Convert the namespace separator to directory separator
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Include the class file if it exists
    if (file_exists($file)) {
        require $file;
    }
}

// Register the autoloader
spl_autoload_register('up_autoloader');