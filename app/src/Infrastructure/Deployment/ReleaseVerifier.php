<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Deployment;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ReleaseVerifier
{
    public function verify(string $release): ReleaseVerificationReport
    {
        $root = realpath($release);
        if (!is_string($root) || !is_dir($root)) {
            return new ReleaseVerificationReport(false, 0, ['release_directory_missing']);
        }
        $manifestPath = $root . DIRECTORY_SEPARATOR . 'release-manifest.json';
        $manifest = is_file($manifestPath) ? json_decode((string)file_get_contents($manifestPath), true) : null;
        if (!is_array($manifest) || ($manifest['format'] ?? null) !== 'architex-release-manifest-v1' || !is_array($manifest['files'] ?? null)) {
            return new ReleaseVerificationReport(false, 0, ['manifest_invalid']);
        }
        $errors = [];
        $actual = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                $errors[] = 'symlink_forbidden';
                continue;
            }
            if (!$item->isFile()) continue;
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            if ($relative === 'release-manifest.json') continue;
            $actual[$relative] = ['sha256'=>hash_file('sha256', $item->getPathname()), 'bytes'=>$item->getSize()];
            if ($this->forbidden($relative)) $errors[] = 'forbidden_path:' . $relative;
            if ($item->getSize() <= 2_000_000 && $this->containsSecret((string)file_get_contents($item->getPathname()))) {
                $errors[] = 'possible_secret:' . $relative;
            }
        }
        ksort($actual, SORT_STRING);
        $expected = $manifest['files'];
        ksort($expected, SORT_STRING);
        if ($actual !== $expected) $errors[] = 'manifest_mismatch';
        if (!is_file($root . '/public/index.php') || !is_file($root . '/vendor/autoload.php') || !is_file($root . '/bootstrap.php')) {
            $errors[] = 'runtime_incomplete';
        }
        return new ReleaseVerificationReport($errors === [], count($actual), array_values(array_unique($errors)));
    }

    private function forbidden(string $relative): bool
    {
        return preg_match('#(^|/)(?:\.env(?:\..*)?|tests?|node_modules|packs|\.git|storage/(?:files|logs|tmp|evidence))(?:/|$)#i', $relative) === 1
            || preg_match('#^vendor/(?:phpunit|sebastian|mockery|phar-io|theseer)/#i', $relative) === 1;
    }

    private function containsSecret(string $content): bool
    {
        return preg_match('/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/', $content) === 1
            || preg_match('/\b(?:DB_PASSWORD|SMTP_PASSWORD|BACKUP_ARCHIVE_PASSWORD)\s*=\s*(?!replace-with|\s*$)[^\s#]{12,}/i', $content) === 1;
    }
}
