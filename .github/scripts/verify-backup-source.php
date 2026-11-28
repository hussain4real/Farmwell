<?php

declare(strict_types=1);

[$script, $root, $inventory] = $argv;

// Spatie uses a line-delimited manifest and trims each path before adding it to the zip.
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    $path = $file->getPathname();
    if (! $file->isFile() || $file->isLink() || strpbrk($path, "\r\n") !== false || rtrim($path) !== $path) {
        throw new RuntimeException('A private media path cannot be archived safely.');
    }
}

$prefix = trim((string) getenv('MEDIA_PREFIX'), '/');
foreach (new SplFileObject($inventory) as $row) {
    if ($row === false || trim($row) === '') {
        continue;
    }

    [$id, $name, $size] = json_decode($row, true, flags: JSON_THROW_ON_ERROR);
    if (basename($name) !== $name || in_array($name, ['', '.', '..'], true)) {
        throw new RuntimeException("Invalid filename on media record {$id}.");
    }
    $path = $root.'/'.($prefix === '' ? '' : $prefix.'/').$id.'/'.$name;
    if (! is_file($path) || filesize($path) !== $size) {
        throw new RuntimeException("Missing or incomplete file for media record {$id}.");
    }
}
