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

    /** @return list<array{organisationId:string,role:string,profileRole?:string}> */
    public function activeForUser(string $userId): array
    {
        $statement = $this->pdo->prepare("SELECT HEX(m.organisation_id) organisation_id,m.role,p.role_key FROM organisation_memberships m LEFT JOIN registration_profiles p ON p.organisation_id=m.organisation_id AND p.user_id=m.user_id WHERE m.user_id=UNHEX(:user_id) AND m.status='active' ORDER BY m.organisation_id");
        $statement->execute(['user_id' => $userId]);
        $memberships = [];
        foreach ($statement as $row) {
            $membership = ['organisationId' => strtolower((string) $row['organisation_id']), 'role' => (string) $row['role']];
            if (is_string($row['role_key']) && $row['role_key'] !== '') {
                $membership['profileRole'] = $row['role_key'];
            }
            $memberships[] = $membership;
        }
        return $memberships;
    }
}
