<?php
declare(strict_types=1);
namespace Architex\Platform\Project;

final readonly class ProjectPassport
{
    /** @param list<array{recordType:string,priority:string,reason:string}> $missingRecords */
    public function __construct(
        public string $projectId, public string $organisationId, public string $projectCode,
        public string $projectName, public ProjectPhase $currentPhase, public int $version,
        public array $missingRecords, public bool $mayAdvance,
    ) {}
}
