<?php
declare(strict_types=1);
namespace Architex\Platform\Inbox;
use PDO;

/** Notification audiences mirror producing services' authority rules; never grant authority. */
final readonly class WorkflowRecipients
{
    public function __construct(private PDO $pdo) {}

    /** @return list<array{id:string,role:string}> */
    public function forEvent(string $organisation, string $project, string $event): array
    {
        $rule = match ($event) {
            'closeout.handover_pack_issued' => "m.role IN ('owner','admin') OR t.user_id IS NOT NULL",
            'finance_claim_review_required' => "t.discipline='quantity_surveyor'",
            'finance_variation_requires_approval', 'finance_payment_certificate_issued' => "m.role IN ('owner','admin') OR t.discipline='quantity_surveyor' OR t.discipline=p.lead_professional_role",
            'finance_retention_release_due' => "m.role IN ('owner','admin') OR t.discipline=p.lead_professional_role",
            'procurement_award_approval_required' => "m.role IN ('owner','admin') OR t.discipline IN ('client','developer') OR
                (t.discipline IN ('architect','quantity_surveyor','project_manager','construction_project_manager','principal_agent','engineer') AND
                 EXISTS (SELECT 1 FROM verification_records v WHERE v.user_id=m.user_id AND v.kind='professional_registration'
                   AND v.grade IN ('manually_reviewed','externally_verified') AND v.reviewed_by IS NOT NULL AND v.reviewed_by<>v.user_id
                   AND (v.expires_on IS NULL OR v.expires_on>=UTC_DATE())))",
            default => null,
        };
        if ($rule === null) return [];
        $roles = $event === 'closeout.handover_pack_issued'
            ? "m.role IN ('owner','admin','member','viewer')"
            : "m.role IN ('owner','admin','member')";
        $statement = $this->pdo->prepare("SELECT DISTINCT LOWER(HEX(m.user_id)) id,m.role
            FROM projects p JOIN organisation_memberships m ON m.organisation_id=p.organisation_id AND m.status='active'
            LEFT JOIN project_team_members t ON t.organisation_id=p.organisation_id AND t.project_id=p.id AND t.user_id=m.user_id AND t.status='active'
            WHERE p.organisation_id=UNHEX(:organisation) AND p.id=UNHEX(:project) AND {$roles} AND ({$rule}) ORDER BY id");
        $statement->execute(['organisation'=>$organisation,'project'=>$project]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
