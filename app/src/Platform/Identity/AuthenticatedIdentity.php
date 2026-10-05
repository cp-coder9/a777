<?php
declare(strict_types=1);

namespace Architex\Platform\Identity;

final readonly class AuthenticatedIdentity
{
    /** @param list<array{organisationId:string,role:string,profileRole?:string}> $memberships */
    public function __construct(public string $userId, public string $email, public array $memberships) {}
}
