<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Deployment;

final readonly class ReleaseVerificationReport
{
    /** @param list<string> $errors */
    public function __construct(public bool $valid, public int $fileCount, public array $errors) {}
}
