<?php
declare(strict_types=1);
namespace Architex\Platform\Project;

use InvalidArgumentException;

final readonly class CreateProject
{
    public function __construct(
        public string $organisationId, public string $userId, public string $projectCode,
        public string $projectName, public string $clientName, public string $municipality,
        public string $propertyReference, public string $propertyUse, public string $landUseNotes,
        public string $leadProfessionalRole,
    ) {
        foreach (['organisationId','userId','projectCode','projectName','clientName','municipality','propertyReference','propertyUse','leadProfessionalRole'] as $field) {
            if (trim($this->{$field}) === '') throw new InvalidArgumentException($field . ' is required.');
        }
        if (strlen($projectCode) > 60 || strlen($projectName) > 180) throw new InvalidArgumentException('Project identity is too long.');
    }
}
