<?php
declare(strict_types=1);

namespace Architex\Platform\Organisation;

use Architex\Platform\Identity\Identifier;
use PDO;

final readonly class MembershipRepository
{
    public function __construct(private PDO $pdo) {}

    public function addPendingOwner(string $organisationId, string $userId): string
    {
        $id = Identifier::generate();
        $statement = $this->pdo->prepare("INSERT INTO organisation_memberships (id,organisation_id,user_id,role,status) VALUES (UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),'owner','pending')");
        $statement->execute(['id' => $id, 'organisation_id' => $organisationId, 'user_id' => $userId]);
        return $id;
    }

    /** @return list<array{organisationId:string,role:string}> */
    public function activeForUser(string $userId): array
    {
        $statement = $this->pdo->prepare("SELECT HEX(organisation_id) organisation_id,role FROM organisation_memberships WHERE user_id=UNHEX(:user_id) AND status='active' ORDER BY organisation_id");
        $statement->execute(['user_id' => $userId]);
        $memberships = [];
        foreach ($statement as $row) {
            $memberships[] = ['organisationId' => strtolower((string) $row['organisation_id']), 'role' => (string) $row['role']];
        }
        return $memberships;
    }
}
