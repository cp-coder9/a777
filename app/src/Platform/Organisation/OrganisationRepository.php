<?php
declare(strict_types=1);

namespace Architex\Platform\Organisation;

use Architex\Platform\Identity\Identifier;
use PDO;

final readonly class OrganisationRepository
{
    public function __construct(private PDO $pdo) {}

    public function createPending(string $name, string $slug): string
    {
        $id = Identifier::generate();
        $statement = $this->pdo->prepare("INSERT INTO organisations (id,name,slug,status) VALUES (UNHEX(:id),:name,:slug,'pending_email_verification')");
        $statement->execute(['id' => $id, 'name' => $name, 'slug' => $slug]);
        return $id;
    }

    /** @return array{id:string,name:string,slug:string,status:string}|null */
    public function findForUser(string $organisationId, string $userId): ?array
    {
        $statement = $this->pdo->prepare("SELECT HEX(o.id) id,o.name,o.slug,o.status FROM organisations o JOIN organisation_memberships m ON m.organisation_id=o.id WHERE o.id=UNHEX(:organisation_id) AND m.user_id=UNHEX(:user_id) AND m.status='active'");
        $statement->execute(['organisation_id' => $organisationId, 'user_id' => $userId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }
        return ['id' => strtolower((string) $row['id']), 'name' => (string) $row['name'], 'slug' => (string) $row['slug'], 'status' => (string) $row['status']];
    }
}
