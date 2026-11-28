<?php

declare(strict_types=1);

[$script, $archive, $destination] = $argv;
$source = $argv[3] ?? '';
$zip = new ZipArchive;
if ($zip->open($archive) !== true) {
    throw new RuntimeException('Cannot open the downloaded backup archive.');
}
$zip->setPassword((string) getenv('BACKUP_ARCHIVE_PASSWORD'));
$verifiedFiles = [];
for ($index = 0; $index < $zip->numFiles; $index++) {
    $entry = $zip->statIndex($index);
    $name = $entry['name'];
    if (str_ends_with($name, '/')) {
        continue;
    }
    if (($name !== 'database.dump' && ! str_starts_with($name, 'media/'))
        || strpbrk($name, "\\\r\n") !== false || array_intersect(['', '.', '..'], explode('/', $name)) !== []
        || ($entry['encryption_method'] ?? null) !== ZipArchive::EM_AES_256 || isset($verifiedFiles[$name])) {
        throw new RuntimeException('Unexpected or unencrypted backup entry.');
    }
    if ($source !== '' && (! is_file($source.'/'.$name) || is_link($source.'/'.$name))) {
        throw new RuntimeException('A backup entry was absent from the source.');
    }
    $input = $zip->getStreamIndex($index);
    $target = $destination.'/'.$name;
    if ($input === false || (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0700, true))) {
        throw new RuntimeException('Cannot decrypt or create the backup entry.');
    }
    $output = fopen($target, 'xb');
    if ($output === false) {
        throw new RuntimeException('Cannot create the backup entry.');
    }
    $bytes = stream_copy_to_stream($input, $output);
    fclose($input);
    fclose($output);
    if ($bytes !== $entry['size'] || ($source !== ''
        && ! hash_equals(hash_file('sha256', $source.'/'.$name), hash_file('sha256', $target)))) {
        throw new RuntimeException('Recovered bytes differ from the backup source.');
    }
    $verifiedFiles[$name] = true;
}
if ($source !== '') {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = substr($file->getPathname(), strlen($source) + 1);
        if ($file->isFile() && ! isset($verifiedFiles[$relative])) {
            throw new RuntimeException('A source file is absent from the downloaded archive.');
        }
    }
}
if (! isset($verifiedFiles['database.dump'])) {
    throw new RuntimeException('The downloaded archive is incomplete.');
}
$zip->close();
echo count($verifiedFiles).($source === '' ? " AES-256 files decrypted.\n" : " restored files matched the source SHA-256.\n");
