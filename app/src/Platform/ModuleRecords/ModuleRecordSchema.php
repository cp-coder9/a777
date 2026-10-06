<?php

declare(strict_types=1);

namespace Architex\Platform\ModuleRecords;

/**
 * The per-module vocabulary behind module_records.
 *
 * Each entry describes one module whose v1.html original had no domain form: the record
 * type it writes, its status ladder, the fields its form must collect, and which status
 * changes are decisions that require a human confirmation plus a reason.
 *
 * The status ladder lives here rather than in a MySQL ENUM so a new module is a code
 * change, not a schema migration. Nothing here invents behaviour: every field list is
 * taken from that module's contract gap in js/adapters/registry.js.
 */
final class ModuleRecordSchema
{
    /** Fields every module record may carry, validated by the service. */
    public const BASE_FIELDS = [
        'title', 'detail', 'reference', 'ownerRole', 'dueOn', 'severity',
        'evidenceFileIds', 'linkedRecordIds', 'humanConfirmed', 'confirmationNote',
    ];

    /**
     * @return array<string,array{
     *   recordType:string, statuses:list<string>, initial:string,
     *   required:list<string>, decisionStatuses:list<string>, payloadFields:list<string>
     * }>
     *
     * Scope rule: this store exists ONLY for workflows that have no backend at all.
     *
     * Checked against the running code before adding any module here, because most of the
     * "read-only scaffold" list turned out to be real already:
     *   issues_rfis / ncr_manager / snag_manager / site_instructions -> site_rfis, site_ncrs,
     *     site_snags, site_instructions (adapters/site-tools.js writes all four);
     *   fm_bridge -> closeout_handover_packs (adapters/closeout.js);
     *   dispute_resolution / contractor_compliance / insurance_register / nhbrc_enrolment ->
     *     the controlled-register registers (ControlledRegisterPolicy keys);
     *   council_navigator / municipal_tracker -> municipal submission readiness;
     *   project_explorer -> stored_files uploads.
     * Adding any of those here would create a second source of truth for a workflow that
     * already persists. What is left — and what this store is for — is refuse_calculator,
     * whose module has no write path at all, plus the two shell controls that could never
     * save (A006 implementation notes, A007 project attachment).
     */
    private const MODULES = [
        // Refuse: the inputs are stored with the rule version that produced the figure, so a
        // stored calculation can always be explained later. Nothing here certifies the result.
        'refuse_calculator' => [
            'recordType' => 'refuse_calculation', 'statuses' => ['draft', 'calculated', 'declared'], 'initial' => 'draft',
            'required' => ['title', 'ruleVersionId', 'occupancyUse', 'quantity', 'collectionInterval'],
            'decisionStatuses' => ['declared'],
            'payloadFields' => ['ruleVersionId', 'occupancyUse', 'quantity', 'collectionInterval', 'binCount', 'storageArea', 'facilityRoute', 'declarationNote'],
        ],
        // The two controls the original scaffold shipped but could never save (A006 / A007).
        'scaffold_notes' => [
            'recordType' => 'implementation_note', 'statuses' => ['noted'], 'initial' => 'noted',
            'required' => ['detail'],
            'decisionStatuses' => [],
            'payloadFields' => ['scaffoldId', 'note'],
        ],
        'project_attachments' => [
            'recordType' => 'project_attachment', 'statuses' => ['attached'], 'initial' => 'attached',
            'required' => ['title'],
            'decisionStatuses' => [],
            'payloadFields' => ['scaffoldId', 'attachmentRef'],
        ],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::MODULES);
    }

    public static function supports(string $moduleKey): bool
    {
        return isset(self::MODULES[$moduleKey]);
    }

    /** @return array{recordType:string,statuses:list<string>,initial:string,required:list<string>,decisionStatuses:list<string>,payloadFields:list<string>} */
    public static function for(string $moduleKey): array
    {
        if (!isset(self::MODULES[$moduleKey])) {
            throw new ModuleRecordException('unknown_module', 404);
        }
        return self::MODULES[$moduleKey];
    }

    /** The vocabulary the UI renders its status control from. @return array<string,mixed> */
    public static function catalogue(): array
    {
        $out = [];
        foreach (self::MODULES as $key => $spec) {
            $out[$key] = [
                'recordType' => $spec['recordType'],
                'statuses' => $spec['statuses'],
                'initialStatus' => $spec['initial'],
                'requiredFields' => $spec['required'],
                'decisionStatuses' => $spec['decisionStatuses'],
                'payloadFields' => $spec['payloadFields'],
            ];
        }
        return $out;
    }

    public static function assertStatus(string $moduleKey, string $status): void
    {
        if (!in_array($status, self::for($moduleKey)['statuses'], true)) {
            throw new ModuleRecordException('invalid_status');
        }
    }

    public static function isDecision(string $moduleKey, string $status): bool
    {
        return in_array($status, self::for($moduleKey)['decisionStatuses'], true);
    }

    public static function assertPayloadField(string $moduleKey, string $field): void
    {
        if (!in_array($field, self::for($moduleKey)['payloadFields'], true)) {
            throw new ModuleRecordException('unknown_field');
        }
    }

    public static function isBaseField(string $field): bool
    {
        return in_array($field, self::BASE_FIELDS, true);
    }
}
