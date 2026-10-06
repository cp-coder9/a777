<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Deployment;

/** Persistent fail-closed lease; flock drains only callers of enter(). */
final class MaintenanceFence
{
    private static mixed $requestLock = null;
    private static ?string $requestDirectory = null;

    public function __construct(private readonly string $directory)
    {
        if (!is_dir($directory) || is_link($directory)) {
            throw new \RuntimeException('Maintenance fence directory unavailable.');
        }
    }

    private function lock(string $name = 'writers.lock'): mixed
    {
        $path = $this->directory . '/' . $name;
        if (is_link($path)) throw new \RuntimeException('Unsafe fence lock.');
        $handle = fopen($path, 'c+b');
        if ($handle === false) throw new \RuntimeException('Maintenance lock unavailable.');
        chmod($path, 0600);
        return $handle;
    }

    private function read(): ?array
    {
        $path = $this->directory . '/lease.json';
        if (!file_exists($path) && !is_link($path)) return null;
        if (is_link($path)) throw new \RuntimeException('Unsafe maintenance lease.');
        $state = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($state) || !isset($state['owner'], $state['fingerprint'], $state['phase'])) {
            throw new \RuntimeException('Maintenance lease corrupt.');
        }
        return $state;
    }

    /** Retained SH lock is released only when the PHP request/process terminates. */
    public function enter(): void
    {
        if (self::$requestLock !== null) {
            if (self::$requestDirectory !== realpath($this->directory)) throw new \RuntimeException('Conflicting request fence.');
            return;
        }
        $lock = $this->lock();
        if (!flock($lock, LOCK_SH | LOCK_NB)) {
            fclose($lock);
            throw new \RuntimeException('Application maintenance in progress.');
        }
        try {
            if ($this->read() !== null) throw new \RuntimeException('Application maintenance in progress.');
            self::$requestLock = $lock;
            self::$requestDirectory = realpath($this->directory);
            register_shutdown_function(static function (): void {
                if (is_resource(self::$requestLock)) {
                    flock(self::$requestLock, LOCK_UN);
                    fclose(self::$requestLock);
                }
                self::$requestLock = null;
            });
        } catch (\Throwable $error) {
            flock($lock, LOCK_UN);
            fclose($lock);
            throw $error;
        }
    }

    private function write(array $state): void
    {
        $temporary = $this->directory . '/lease-' . bin2hex(random_bytes(16)) . '.part';
        $handle = fopen($temporary, 'x+b');
        if ($handle === false) throw new \RuntimeException('Cannot persist maintenance lease.');
        try {
            chmod($temporary, 0600);
            $data = json_encode($state, JSON_THROW_ON_ERROR);
            if (fwrite($handle, $data) !== strlen($data) || !fflush($handle)) {
                throw new \RuntimeException('Cannot flush maintenance lease.');
            }
            if (function_exists('fsync') && !fsync($handle)) throw new \RuntimeException('Cannot sync lease.');
        } finally { fclose($handle); }
        if (!rename($temporary, $this->directory . '/lease.json')) throw new \RuntimeException('Cannot publish lease.');
    }

    private function validate(string $token, string $fingerprint): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token) || !preg_match('/^[a-f0-9]{64}$/D', $fingerprint)) {
            throw new \RuntimeException('Invalid lease ownership.');
        }
    }

    /** No expiry resumes writes. Timed-out drain deliberately retains the lease. */
    public function stop(string $token, string $fingerprint, float $timeout = 30): array
    {
        $this->validate($token, $fingerprint);
        $control = $this->lock('control.lock');
        if (!flock($control, LOCK_EX | LOCK_NB)) {
            fclose($control);
            throw new \RuntimeException('Maintenance controller busy.');
        }
        $lock = null;
        try {
            $state = $this->read();
            $owner = hash('sha256', $token);
            if ($state !== null && (!hash_equals($state['owner'], $owner)
                || !hash_equals($state['fingerprint'], $fingerprint))) {
                throw new \RuntimeException('Maintenance lease owned by another operator.');
            }
            $state = ['owner'=>$owner, 'fingerprint'=>$fingerprint, 'phase'=>'draining', 'startedAt'=>time()];
            // Publish before waiting so newly arriving readers cannot starve drain.
            $this->write($state);
            $lock = $this->lock();
            $deadline = microtime(true) + max(0, $timeout);
            while (!flock($lock, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) throw new \RuntimeException('Fence-aware writer drain timed out; lease retained.');
                usleep(10000);
            }
            $state['phase'] = 'drained';
            $state['drainedAt'] = time();
            $this->write($state);
            return $state;
        } finally {
            if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
            flock($control, LOCK_UN);
            fclose($control);
        }
    }

    /** Caller must separately verify release readiness and old unhooked writers. */
    public function resume(string $token, string $fingerprint): void
    {
        $this->validate($token, $fingerprint);
        $control = $this->lock('control.lock');
        if (!flock($control, LOCK_EX | LOCK_NB)) {
            fclose($control);
            throw new \RuntimeException('Maintenance controller busy.');
        }
        try {
            $state = $this->read();
            if ($state === null || $state['phase'] !== 'drained'
                || !hash_equals($state['owner'], hash('sha256', $token))
                || !hash_equals($state['fingerprint'], $fingerprint)) {
                throw new \RuntimeException('Maintenance release ownership mismatch.');
            }
            $lock = $this->lock();
            try {
                if (!flock($lock, LOCK_EX | LOCK_NB)) throw new \RuntimeException('Writers still active.');
                if (!unlink($this->directory . '/lease.json')) throw new \RuntimeException('Lease removal failed.');
            } finally { flock($lock, LOCK_UN); fclose($lock); }
        } finally { flock($control, LOCK_UN); fclose($control); }
    }

    public function status(): ?array { return $this->read(); }
}
