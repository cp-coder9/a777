<?php

declare(strict_types=1);

namespace Architex\Platform\NuancedQc;

final class NuancedQcRegistry
{
    /** @return list<array{key:string,title:string,sortOrder:int}> */
    public function categories(): array
    {
        $definitions = [
            ['contract_legal_basis', 'Contract and legal basis'],
            ['authority_matrix', 'Authority matrix'],
            ['delegation_supervision', 'Delegation and supervision'],
            ['version_conflicts', 'Version conflict and stale-source handling'],
            ['source_of_truth', 'Source-of-truth hierarchy'],
            ['expiry_validity', 'Expiry and validity windows'],
            ['compliance_packs', 'Compliance packs'],
            ['programme_risk', 'Long-lead and programme risk'],
            ['samples_shop_drawings', 'Samples, mockups and shop drawings'],
            ['substitution_impact', 'Substitution impact chain'],
            ['delivery_logistics', 'Delivery and logistics'],
            ['inventory_materials_on_site', 'Inventory and materials on site'],
            ['payment_commercial_distinctions', 'Payment and commercial distinctions'],
            ['disputes_objections', 'Disputes and objections'],
            ['change_impact', 'Change impact engine'],
            ['notices_deadlines', 'Notices and deadlines'],
            ['tender_fairness', 'Tender fairness'],
            ['pricing_normalisation', 'Pricing normalisation'],
            ['unit_conversion_pack_size', 'Unit conversion and pack-size logic'],
            ['warranty_closeout', 'Warranty and closeout'],
            ['statutory_professional_boundaries', 'Statutory and professional responsibility boundaries'],
            ['risk_register', 'Risk register integration'],
            ['notification_priority', 'Notification priority'],
            ['offline_mobile_site', 'Offline, mobile and site operation'],
            ['evidence_quality', 'Evidence quality'],
            ['human_readable_audit', 'Human-readable audit trail'],
            ['tenant_project_isolation', 'Tenant and project data isolation'],
            ['security_remote_desktop', 'Security and remote desktop'],
            ['support_incidents', 'Support and incident handling'],
            ['legacy_imports', 'Legacy import handling'],
            ['project_type_configuration', 'Project type configuration'],
            ['admin_configuration', 'Admin configuration'],
            ['not_applicable_handling', 'Not-applicable handling'],
            ['responsibility_disclaimers', 'Responsibility disclaimers'],
            ['analytics_learning_loop', 'Analytics and learning loop'],
        ];

        return array_map(
            static fn (array $definition, int $index): array => [
                'key' => $definition[0],
                'title' => $definition[1],
                'sortOrder' => $index + 1,
            ],
            $definitions,
            array_keys($definitions),
        );
    }
}
