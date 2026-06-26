<?php

// Check if we are running in Vercel to create writable storage directories in /tmp
if (getenv('RUNNING_IN_VERCEL') === 'true') {
    $storageDirs = [
        '/tmp/storage/bootstrap/cache',
        '/tmp/storage/framework/sessions',
        '/tmp/storage/framework/views',
        '/tmp/storage/framework/cache/data',
        '/tmp/storage/logs',
    ];

    foreach ($storageDirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

// Forward to the actual Laravel public entry point
require __DIR__ . '/../public/index.php';
