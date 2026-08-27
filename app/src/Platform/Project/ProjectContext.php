<?php
declare(strict_types=1);
namespace Architex\Platform\Project;

final readonly class ProjectContext
{
    public function __construct(public string $organisationId, public string $userId, public string $projectId, public string $role) {}
}
