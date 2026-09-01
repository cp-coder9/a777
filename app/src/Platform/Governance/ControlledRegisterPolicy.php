<?php

declare(strict_types=1);

namespace Architex\Platform\Governance;

final readonly class ControlledRegisterPolicy
{
    /** @return array<string,array{label:string,initial:string,transitions:array<string,list<string>>,terminal:list<string>}> */
    public function definitions(): array
    {
        return [
            'dispute_resolution' => [
                'label' => 'Dispute Resolution', 'initial' => 'notice_draft', 'terminal' => ['resolved', 'closed'],
                'transitions' => [
                    'notice_draft' => ['issued'], 'issued' => ['negotiating', 'referred'],
                    'negotiating' => ['referred', 'resolved'], 'referred' => ['resolved'], 'resolved' => ['closed'], 'closed' => [],
                ],
            ],
            'insurance_register' => [
                'label' => 'Insurance Register', 'initial' => 'pending_evidence', 'terminal' => ['active', 'expired', 'rejected'],
                'transitions' => [
                    'pending_evidence' => ['active', 'rejected'], 'active' => ['expiring', 'expired'],
                    'expiring' => ['active', 'expired'], 'expired' => ['active'], 'rejected' => ['pending_evidence'],
                ],
            ],
            'nhbrc_enrolment' => [
                'label' => 'NHBRC Enrolment', 'initial' => 'draft', 'terminal' => ['enrolled', 'closed'],
                'transitions' => [
                    'draft' => ['submitted'], 'submitted' => ['inspections_due', 'enrolled', 'exception'],
                    'inspections_due' => ['enrolled', 'exception'], 'exception' => ['submitted', 'closed'],
                    'enrolled' => ['inspections_due', 'closed'], 'closed' => [],
                ],
            ],
            'contractor_compliance' => [
                'label' => 'Contractor Compliance', 'initial' => 'pending', 'terminal' => ['compliant', 'conditional', 'expired', 'rejected'],
                'transitions' => [
                    'pending' => ['under_review'], 'under_review' => ['compliant', 'conditional', 'rejected'],
                    'compliant' => ['conditional', 'expired'], 'conditional' => ['under_review', 'compliant', 'expired'],
                    'expired' => ['under_review'], 'rejected' => ['under_review'],
                ],
            ],
        ];
    }

    /** @return array{label:string,initial:string,transitions:array<string,list<string>>,terminal:list<string>} */
    public function definition(string $key): array
    {
        return $this->definitions()[$key] ?? throw new ControlledRegisterException('controlled_register_not_found');
    }

    public function initialStatus(string $key): string
    {
        return $this->definition($key)['initial'];
    }

    /** @return list<string> */
    public function allowedTransitions(string $key, string $status): array
    {
        $definition = $this->definition($key);
        return $definition['transitions'][$status] ?? throw new ControlledRegisterException('controlled_register_status_invalid');
    }

    public function assertTransition(string $key, string $from, string $to): void
    {
        if (!in_array($to, $this->allowedTransitions($key, $from), true)) {
            throw new ControlledRegisterException('controlled_register_transition_invalid');
        }
    }
}
