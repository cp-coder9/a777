<?php
declare(strict_types=1);

namespace Architex\Platform\Finance;

final class ProviderLifecycle
{
    public static function next(string $current, string $event): ?string
    {
        $target = match ($event) {
            'payment_processing'=>'submitted_to_provider', 'payment_paid'=>'provider_confirmed_paid',
            'payment_failed'=>'failed', 'payment_refunded'=>'refunded', default=>null,
        };
        if ($target === $current) return $current;
        return match ($current) {
            'submitted_to_provider'=>in_array($target,['provider_confirmed_paid','failed'],true) ? $target : null,
            'provider_confirmed_paid'=>$target === 'refunded' ? $target : null,
            default=>null,
        };
    }
}
