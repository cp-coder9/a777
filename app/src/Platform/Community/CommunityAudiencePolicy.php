<?php

declare(strict_types=1);

namespace Architex\Platform\Community;

final class CommunityAudiencePolicy
{
    private const ROLES = [
        'architect',
        'candidate_architect',
        'quantity_surveyor',
        'engineer',
        'town_planner',
        'land_surveyor',
        'construction_project_manager',
        'landscape_architect',
        'interior_designer',
        'contractor',
        'subcontractor',
        'supplier',
        'client_developer',
        'admin',
        'moderator',
    ];

    private const VERIFICATION_STATUSES = [
        'any',
        'self_declared',
        'document_uploaded',
        'manually_reviewed',
        'externally_verified',
        'verified',
    ];

    /**
     * @param array<string, mixed> $input
     * @return array{scope:string,targetRoles:list<string>,targetRegions:list<string>,verificationStatus:string}
     */
    public function normalise(array $input): array
    {
        $scope = (string) ($input['scope'] ?? 'selected_roles');
        if (!in_array($scope, ['all_community', 'selected_roles'], true)) {
            throw new CommunityException('invalid_audience_scope');
        }

        $roles = $this->uniqueStrings($input['targetRoles'] ?? []);
        foreach ($roles as $role) {
            if (!in_array($role, self::ROLES, true)) {
                throw new CommunityException('invalid_audience_role');
            }
        }
        if ($scope === 'selected_roles' && $roles === []) {
            throw new CommunityException('audience_roles_required');
        }

        $verification = (string) ($input['verificationStatus'] ?? 'any');
        if (!in_array($verification, self::VERIFICATION_STATUSES, true)) {
            throw new CommunityException('invalid_verification_status');
        }

        return [
            'scope' => $scope,
            'targetRoles' => $scope === 'all_community' ? [] : $roles,
            'targetRegions' => $this->uniqueStrings($input['targetRegions'] ?? []),
            'verificationStatus' => $verification,
        ];
    }

    /**
     * @param list<array<string, mixed>> $candidates
     * @param array<string, mixed> $audience
     * @return list<array<string, mixed>>
     */
    public function resolve(array $candidates, array $audience, string $senderId): array
    {
        $audience = $this->normalise($audience);
        $recipients = [];
        foreach ($candidates as $candidate) {
            $role = (string) ($candidate['role'] ?? '');
            $blocked = $this->uniqueStrings($candidate['blockedUserIds'] ?? []);
            $muted = $this->uniqueStrings($candidate['mutedRoles'] ?? []);
            if (($candidate['active'] ?? false) !== true || ($candidate['optedIn'] ?? false) !== true) {
                continue;
            }
            if (in_array($senderId, $blocked, true) || ($audience['targetRoles'] !== [] && array_intersect($muted, $audience['targetRoles']) !== [])) {
                continue;
            }
            if ($audience['scope'] === 'selected_roles' && !in_array($role, $audience['targetRoles'], true)) {
                continue;
            }
            if ($audience['targetRegions'] !== [] && !in_array((string) ($candidate['region'] ?? ''), $audience['targetRegions'], true)) {
                continue;
            }
            if ($audience['verificationStatus'] !== 'any' && (string) ($candidate['verificationStatus'] ?? '') !== $audience['verificationStatus']) {
                continue;
            }
            $recipients[] = $candidate;
        }

        return $recipients;
    }

    /** @param array<string, mixed> $audience */
    public function moderationStatus(string $category, array $audience, bool $isModerator): string
    {
        if ($isModerator) {
            return 'approved';
        }
        $audience = $this->normalise($audience);
        if ($category === 'admin_notice' || $audience['scope'] === 'all_community' || count($audience['targetRoles']) > 3) {
            return 'pending_approval';
        }

        return 'approved';
    }

    /** @return list<string> */
    private function uniqueStrings(mixed $values): array
    {
        if (!is_array($values)) {
            throw new CommunityException('invalid_audience_filter');
        }
        $strings = [];
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new CommunityException('invalid_audience_filter');
            }
            $strings[] = trim($value);
        }

        return array_values(array_unique($strings));
    }
}
