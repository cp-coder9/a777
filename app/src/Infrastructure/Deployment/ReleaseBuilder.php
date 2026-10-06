<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Deployment;

use Closure;
use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final readonly class ReleaseBuilder
{
    /** @var Closure(string):void */
    private Closure $dependencyInstaller;

    /** @param Closure(string):void|null $dependencyInstaller */
    public function __construct(private string $projectRoot, private string $outputRoot, ?Closure $dependencyInstaller = null)
    {
        $this->dependencyInstaller = $dependencyInstaller ?? $this->installDependencies(...);
    }

    public function build(string $version, string $environment = 'production', bool $allowDirtyStaging = false): string
    {
        if (!in_array($environment, ['production', 'staging'], true) || ($allowDirtyStaging && $environment !== 'staging')) {
            throw new InvalidArgumentException('Dirty source overrides are permitted only for explicit staging builds.');
        }
        if (preg_match('/\A[a-z0-9][a-z0-9._-]{0,63}\z/i', $version) !== 1) {
            throw new InvalidArgumentException('The release version is invalid.');
        }
        $source = realpath($this->projectRoot);
        if (!is_string($source) || !is_file($source . '/composer.lock') || !is_dir($source . '/app/public')) {
            throw new RuntimeException('The project source is incomplete.');
        }
        $provenance = $this->provenance($source);
        if (($provenance['dirty'] || $provenance['commit'] === null) && !$allowDirtyStaging) {
            throw new RuntimeException('A clean, identifiable Git revision is required for this release.');
        }
        if (!is_dir($this->outputRoot) && !mkdir($this->outputRoot, 0700, true) && !is_dir($this->outputRoot)) {
            throw new RuntimeException('The release output directory is unavailable.');
        }
        $output = realpath($this->outputRoot);
        if (!is_string($output)) throw new RuntimeException('The release output directory is unavailable.');
        $destination = $output . DIRECTORY_SEPARATOR . 'architex-release-' . $version;
        if (file_exists($destination)) throw new RuntimeException('The immutable release already exists.');
        $staging = $output . DIRECTORY_SEPARATOR . '.build-' . $version . '-' . bin2hex(random_bytes(6));
        if (!mkdir($staging, 0700, true)) throw new RuntimeException('The release staging directory is unavailable.');
        try {
            foreach (['public','src','views','database','cron','config'] as $directory) {
                $this->copyTree($source . '/app/' . $directory, $staging . '/' . $directory);
            }
            mkdir($staging . '/bin', 0700, true);
            foreach (['migrate.php','database-status.php','backup.php','restore.php','verify-release.php','deliver-community-notifications.php','deliver-dedicated-app-notifications.php'] as $script) {
                if (is_file($source . '/app/bin/' . $script)) copy($source . '/app/bin/' . $script, $staging . '/bin/' . $script);
            }
            copy($source . '/app/bootstrap.php', $staging . '/bootstrap.php');
            $composer = json_decode((string)file_get_contents($source . '/composer.json'), true, 64, JSON_THROW_ON_ERROR);
            unset($composer['require-dev'], $composer['autoload-dev'], $composer['scripts']);
            $composer['autoload']['psr-4']['Architex\\'] = 'src/';
            file_put_contents($staging . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            copy($source . '/composer.lock', $staging . '/composer.lock');
            $sourceFiles = $this->manifestFiles($staging);
            unset($sourceFiles['public/build-info.json']);
            $snapshot = hash('sha256', json_encode($sourceFiles, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            ($this->dependencyInstaller)($staging);
            if (!is_file($staging . '/vendor/autoload.php')) throw new RuntimeException('Production dependencies were not installed.');
            $after = $this->provenance($source);
            $dirty = $provenance['dirty'] || $after['dirty'] || $provenance['commit'] !== $after['commit'];
            if (($dirty || $provenance['commit'] === null) && !$allowDirtyStaging) throw new RuntimeException('Source must remain clean and unchanged while building.');
            $metadata = ['version'=>$version,'php'=>'8.3','sourceCommit'=>$dirty ? null : $provenance['commit'],'sourceBaseCommit'=>$provenance['commit'],'sourceDirty'=>$dirty,'sourceSnapshotSha256'=>$snapshot,'environment'=>$environment,'branch'=>$provenance['branch'],'builtAt'=>(new \DateTimeImmutable())->format(DATE_ATOM),'apiMode'=>'php-shared-hosting'];
            file_put_contents($staging . '/release.json', json_encode(['format'=>'architex-release-v1']+$metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            file_put_contents($staging . '/public/build-info.json', json_encode(['format'=>'architex-build-info-v1']+$metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            $files = $this->manifestFiles($staging);
            file_put_contents($staging . '/release-manifest.json', json_encode(['format'=>'architex-release-manifest-v1','version'=>$version,'files'=>$files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            $report = (new ReleaseVerifier())->verify($staging);
            if (!$report->valid) throw new RuntimeException('Release verification failed: ' . implode(',', $report->errors));
            if (!rename($staging, $destination)) throw new RuntimeException('The release could not be published atomically.');
            return $destination;
        } catch (Throwable $exception) {
            $this->removeTree($staging);
            throw $exception;
        }
    }

    private function installDependencies(string $release): void
    {
        $composer = $this->composerCommand();
        $command = [...$composer,'install','--working-dir=' . $release,'--no-dev','--no-interaction','--no-scripts','--prefer-dist','--optimize-autoloader','--classmap-authoritative'];
        $environment = getenv();
        $environment = is_array($environment) ? $environment : [];
        $environment['COMPOSER_ALLOW_SUPERUSER'] = '1';
        $stdoutPath = $release . '/.composer-install.out';
        $stderrPath = $release . '/.composer-install.err';
        $process = proc_open($command, [['pipe','r'],['file',$stdoutPath,'wb'],['file',$stderrPath,'wb']], $pipes, $this->projectRoot, $environment);
        if (!is_resource($process)) throw new RuntimeException('Composer could not be started.');
        fclose($pipes[0]);
        $exit = proc_close($process);
        @unlink($stdoutPath); @unlink($stderrPath);
        if ($exit !== 0) throw new RuntimeException('Production dependency installation failed.');
    }

    /** @return list<string> */
    private function composerCommand(): array
    {
        $configured = getenv('COMPOSER_BINARY');
        if (is_string($configured) && is_file($configured)) {
            return str_ends_with(strtolower($configured), '.phar') ? [PHP_BINARY, $configured] : [$configured];
        }
        $path = getenv('PATH');
        if (is_string($path)) {
            foreach (explode(PATH_SEPARATOR, $path) as $directory) {
                foreach (PHP_OS_FAMILY === 'Windows' ? ['composer.phar','composer.exe'] : ['composer','composer.phar'] as $name) {
                    $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
                    if (!is_file($candidate)) continue;
                    return str_ends_with(strtolower($candidate), '.phar') ? [PHP_BINARY, $candidate] : [$candidate];
                }
            }
        }
        throw new RuntimeException('Composer could not be located. Set COMPOSER_BINARY to its executable or PHAR path.');
    }

    private function copyTree(string $source, string $destination): void
    {
        if (!is_dir($source) || !mkdir($destination, 0700, true)) throw new RuntimeException('A release source directory is unavailable.');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            if ($item->isLink()) throw new RuntimeException('Release sources may not contain symbolic links.');
            $relative = substr($item->getPathname(), strlen($source) + 1);
            if (basename($source) === 'config' && str_replace('\\', '/', $relative) === 'runtime.php') continue;
            $target = $destination . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0700, true)) throw new RuntimeException('A release directory could not be created.');
            } elseif (!copy($item->getPathname(), $target)) {
                throw new RuntimeException('A release file could not be copied.');
            }
        }
    }

    /** @return array<string,array{sha256:string,bytes:int}> */
    private function manifestFiles(string $root): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if (!$item->isFile() || $item->getFilename() === 'release-manifest.json') continue;
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            $files[$relative] = ['sha256'=>hash_file('sha256', $item->getPathname()), 'bytes'=>$item->getSize()];
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    /** @return array{commit:?string,branch:string,dirty:bool} */
    private function provenance(string $source): array
    {
        $commit = $this->git($source, ['rev-parse', '--verify', 'HEAD']);
        $status = $this->git($source, ['status', '--porcelain', '--untracked-files=all']);
        return ['commit'=>is_string($commit) && preg_match('/\A[a-f0-9]{40}\z/', $commit) === 1 ? $commit : null,'branch'=>$this->git($source, ['rev-parse', '--abbrev-ref', 'HEAD']) ?? 'unknown','dirty'=>$status === null || $status !== ''];
    }

    /** @param list<string> $arguments */
    private function git(string $source, array $arguments): ?string
    {
        $process = proc_open(['git', '-C', $source, ...$arguments], [['pipe','r'],['pipe','w'],['pipe','w']], $pipes);
        if (!is_resource($process)) return null;
        fclose($pipes[0]);$output = stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);
        return proc_close($process) === 0 && is_string($output) ? trim($output) : null;
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) return;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        @rmdir($path);
    }
}
