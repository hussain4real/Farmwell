<?php

declare(strict_types=1);

[$script, $archive, $destination, $source] = $argv;
$zip = new ZipArchive;
if ($zip->open($archive) !== true) {
    throw new RuntimeException('Cannot open the downloaded backup archive.');
}
$zip->setPassword((string) getenv('BACKUP_ARCHIVE_PASSWORD'));
$verifiedFiles = 0;
for ($index = 0; $index < $zip->numFiles; $index++) {
    $entry = $zip->statIndex($index);
    $name = $entry['name'];
    if (str_ends_with($name, '/')) {
        continue;
    }
    if (str_starts_with($name, '/') || str_contains($name, '\\') || in_array('..', explode('/', $name), true)
        || ($entry['encryption_method'] ?? null) !== ZipArchive::EM_AES_256
        || ! is_file($source.'/'.$name) || is_link($source.'/'.$name)) {
        throw new RuntimeException('Unexpected or unencrypted backup entry.');
    }
    if (! $zip->extractTo($destination, [$name])
        || ! hash_equals(hash_file('sha256', $source.'/'.$name), hash_file('sha256', $destination.'/'.$name))) {
        throw new RuntimeException('Recovered bytes differ from the backup source.');
    }
    $verifiedFiles++;
}
$sourceFiles = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile()) {
        $sourceFiles++;
        $relative = substr($file->getPathname(), strlen($source) + 1);
        if (! is_file($destination.'/'.$relative)) {
            throw new RuntimeException('A source file is absent from the downloaded archive.');
        }
    }
}
if ($sourceFiles !== $verifiedFiles || ! is_file($destination.'/database.dump')) {
    throw new RuntimeException('The downloaded archive is incomplete.');
}
$zip->close();
echo "Verified SHA-256 for {$verifiedFiles} restored files.\n";
