<?php

declare(strict_types=1);

/**
 * Recursively iterate every .php file under a directory.
 *
 * @return list<string>
 */
function arch_php_files(string $dir): array
{
    if (!\is_dir($dir)) {
        return [];
    }

    $files = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($rii as $file) {
        /** @var SplFileInfo $file */
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    \sort($files);

    return $files;
}
