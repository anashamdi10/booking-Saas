<?php

/**
 * Verifies that every namespaced PHP file declares the namespace matching its
 * directory, and that the class name matches the file name.
 *
 * Usage: php tools/verify-namespaces.php
 */
$root = dirname(__DIR__);
$errors = [];
$checked = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app'));
$files = [];

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = $file->getRealPath();
    }
}

foreach (['database/factories', 'database/seeders'] as $extra) {
    foreach (glob($root.'/'.$extra.'/*.php') ?: [] as $path) {
        $files[] = realpath($path);
    }
}

foreach ($files as $real) {
    $checked++;
    $contents = file_get_contents($real);

    if (! preg_match('/^\s*namespace\s+([^;]+);/m', $contents, $matches)) {
        $errors[] = "{$real}: missing namespace declaration";

        continue;
    }

    $declared = trim($matches[1]);

    if (str_starts_with($real, $root.'/app/')) {
        $relative = substr($real, strlen($root.'/app/'));
        $expected = 'App\\'.str_replace('/', '\\', substr($relative, 0, -4));
    } elseif (str_contains($real, '/database/factories/')) {
        $expected = 'Database\\Factories';
    } elseif (str_contains($real, '/database/seeders/')) {
        $expected = 'Database\\Seeders';
    } else {
        continue;
    }

    if ($declared !== $expected) {
        $errors[] = "{$real}: declares \"{$declared}\" but should declare \"{$expected}\"";
    }

    if (preg_match('/^\s*(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+(\w+)/m', $contents, $classMatch)) {
        $className = $classMatch[1];

        if ($className !== basename($real, '.php')) {
            $errors[] = "{$real}: declares class \"{$className}\" but the file is named ".basename($real);
        }
    }
}

echo "Checked {$checked} files.\n";

if ($errors === []) {
    echo "All namespaces and class names are consistent.\n";
    exit(0);
}

foreach ($errors as $error) {
    echo "PROBLEM: {$error}\n";
}

exit(1);
