<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Closure;
use Throwable;

final readonly class ReadinessController
{
    /** @param Closure():bool $databaseProbe @param Closure():bool $migrationProbe @param Closure():bool $jobsProbe @param Closure():bool $mailProbe */
    public function __construct(
        private Closure $databaseProbe,
        private Closure $migrationProbe,
        private Closure $jobsProbe,
        private Closure $mailProbe,
    ) {}

    public function __invoke(Request $request): Response
    {
        $components = [
            'database' => ['status' => $this->probe($this->databaseProbe) ? 'ready' : 'unavailable'],
            'migrations' => ['status' => $this->probe($this->migrationProbe) ? 'ready' : 'unavailable'],
            'jobs' => ['status' => $this->probe($this->jobsProbe) ? 'ready' : 'unavailable'],
            'mail' => ['status' => $this->probe($this->mailProbe) ? 'ready' : 'unavailable'],
        ];
        $ready = array_reduce($components, static fn (bool $carry, array $component): bool => $carry && $component['status'] === 'ready', true);
        return Response::success(['status' => $ready ? 'ready' : 'unavailable', 'components' => $components], status: $ready ? 200 : 503);
    }

    /** @param Closure():bool $probe */
    private function probe(Closure $probe): bool
    {
        try {
            return $probe() === true;
        } catch (Throwable) {
            return false;
        }
    }
}
