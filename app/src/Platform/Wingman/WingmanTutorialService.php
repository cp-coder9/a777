<?php
declare(strict_types=1);
namespace Architex\Platform\Wingman;

use Architex\Platform\Audit\AuditService;
use PDO;
use Throwable;

final readonly class WingmanTutorialService
{
    public function __construct(private PDO $pdo) {}

    public function read(string $organisation, string $user, string $role): array
    {
        $query = $this->pdo->prepare("SELECT m.role,p.role_key FROM organisation_memberships m LEFT JOIN registration_profiles p ON p.organisation_id=m.organisation_id AND p.user_id=m.user_id WHERE m.organisation_id=UNHEX(:org) AND m.user_id=UNHEX(:user) AND m.status='active'");
        $query->execute(['org'=>$organisation,'user'=>$user]);
        $membership = $query->fetch(PDO::FETCH_ASSOC);
        if (!is_array($membership) || $membership['role'] !== $role) throw new WingmanException('context_denied');
        $tour = WingmanTutorial::catalogue($membership['role_key'], $role);
        $query = $this->pdo->prepare('SELECT step_index,status,revision FROM wingman_tutorial_progress WHERE organisation_id=UNHEX(:org) AND user_id=UNHEX(:user) AND role_profile=:profile AND tutorial_version=:version');
        $query->execute(['org'=>$organisation,'user'=>$user,'profile'=>$tour['profile'],'version'=>$tour['version']]);
        $saved = $query->fetch(PDO::FETCH_ASSOC);
        return $tour + ['progress'=>is_array($saved) ? ['step'=>(int)$saved['step_index'],'status'=>$saved['status'],'revision'=>(int)$saved['revision']] : ['step'=>0,'status'=>'new','revision'=>0]];
    }

    public function update(string $organisation, string $user, string $role, array $input): array
    {
        if (!is_string($input['action'] ?? null) || !is_int($input['revision'] ?? null) || $input['revision'] < 0 || !is_string($input['profile'] ?? null) || !is_int($input['version'] ?? null)) throw new WingmanException('invalid_tutorial_action');
        $this->pdo->beginTransaction();
        try {
            // Serialize updates, including the first insert, within this membership.
            $lock = $this->pdo->prepare('SELECT id FROM organisation_memberships WHERE organisation_id=UNHEX(:org) AND user_id=UNHEX(:user) FOR UPDATE');
            $lock->execute(['org'=>$organisation,'user'=>$user]);
            $tour = $this->read($organisation,$user,$role);
            // Validate the exact authenticated catalogue, not a duplicated version constant.
            if ($tour['version'] !== $input['version']) throw new WingmanException('invalid_tutorial_action');
            if ($tour['profile'] !== $input['profile'] || $tour['progress']['revision'] !== $input['revision']) throw new WingmanException('tutorial_revision_conflict');
            $next = WingmanTutorial::advance($tour['progress'],$input['action'],count($tour['steps']));
            $query = $this->pdo->prepare('INSERT INTO wingman_tutorial_progress(organisation_id,user_id,role_profile,tutorial_version,step_index,status,revision) VALUES(UNHEX(:org),UNHEX(:user),:profile,:version,:step,:status,:revision) ON DUPLICATE KEY UPDATE step_index=VALUES(step_index),status=VALUES(status),revision=VALUES(revision),updated_at=UTC_TIMESTAMP(6)');
            $query->execute(['org'=>$organisation,'user'=>$user,'profile'=>$tour['profile'],'version'=>$tour['version'],'step'=>$next['step'],'status'=>$next['status'],'revision'=>$input['revision']+1]);
            (new AuditService($this->pdo))->append($organisation,$user,'wingman.tutorial.'.$input['action'],'user',$user,['roleProfile'=>$tour['profile'],'version'=>$tour['version'],'step'=>$next['step'],'status'=>$next['status']]);
            $result = $this->read($organisation,$user,$role);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }
}
