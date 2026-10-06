<?php

declare(strict_types=1);
namespace Architex\Platform\SiteExecution;

/** Pure state policy: internal operational authorisation, never statutory approval. */
final class WorkPermitPolicy
{
    public static function transitions(string $status): array
    {
        return match ($status) {
            'draft' => ['requested','revoked'],
            'requested' => ['reviewed','revoked'],
            'reviewed' => ['issued','revoked'],
            'issued' => ['closed','revoked'],
            'closed','revoked' => [],
            default => throw new WorkPermitException('work_permit_status_invalid'),
        };
    }
    public static function assertTransition(string $from, string $to): void
    {
        if (!in_array($to, self::transitions($from), true)) throw new WorkPermitException('work_permit_transition_invalid',409);
    }
    public static function text(mixed $value, int $max): string
    {
        if (!is_string($value) || trim($value)==='' || mb_strlen(trim($value))>$max) throw new WorkPermitException('work_permit_input_required');
        return trim($value);
    }
    public static function id(mixed $id): string
    {
        if (!is_string($id) || preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1) throw new WorkPermitException('work_permit_id_invalid');
        return strtolower($id);
    }
    public static function date(mixed $date): string
    {
        if (!is_string($date)) throw new WorkPermitException('work_permit_date_invalid');
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$date,new \DateTimeZone('UTC'));
        if (!$parsed || $parsed->format('Y-m-d\TH:i')!==$date) throw new WorkPermitException('work_permit_date_invalid');
        return $date;
    }
}