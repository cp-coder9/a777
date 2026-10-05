<?php
declare(strict_types=1);
namespace Architex\Platform\Project;

use InvalidArgumentException;

final readonly class UpdateProject
{
    private function __construct(
        public string $projectCode, public string $projectName, public string $clientName,
        public string $municipality, public string $propertyReference, public string $propertyUse,
        public string $landUseNotes, public string $leadProfessionalRole, public int $expectedVersion,
    ) {}

    /** @param array<string,mixed> $body */
    public static function fromArray(array $body): self
    {
        $limits = ['projectCode'=>60,'projectName'=>180,'clientName'=>180,'municipality'=>180,'propertyReference'=>180,'propertyUse'=>120,'landUseNotes'=>65535,'leadProfessionalRole'=>60];
        foreach ($body as $field => $_) {
            if (!array_key_exists($field, $limits) && $field !== 'expectedVersion') throw new InvalidArgumentException($field . ' is not editable.');
        }
        foreach ($limits as $field => $limit) {
            if (!is_string($body[$field] ?? null)) throw new InvalidArgumentException($field . ' must be a string.');
            if ($field !== 'landUseNotes' && trim($body[$field]) === '') throw new InvalidArgumentException($field . ' is required.');
            // TEXT is byte-limited; VARCHAR limits are Unicode character counts.
            $length = $field === 'landUseNotes' ? strlen($body[$field]) : mb_strlen($body[$field], 'UTF-8');
            if (!mb_check_encoding($body[$field], 'UTF-8') || $length > $limit) throw new InvalidArgumentException($field . ' is too long or is not valid UTF-8.');
        }
        if (!is_int($body['expectedVersion'] ?? null) || $body['expectedVersion'] < 1 || $body['expectedVersion'] >= 4294967295) throw new InvalidArgumentException('expectedVersion must be a positive integer below 4294967295.');
        return new self($body['projectCode'], $body['projectName'], $body['clientName'], $body['municipality'], $body['propertyReference'], $body['propertyUse'], $body['landUseNotes'], $body['leadProfessionalRole'], $body['expectedVersion']);
    }
}
