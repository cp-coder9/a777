<?php
declare(strict_types=1);
namespace Architex\Platform\Wingman;

final class WingmanTutorial
{
    public const VERSION = WingmanRoleTutorials::VERSION;

    public static function catalogue(?string $profile, string $membership): array
    {
        return WingmanRoleTutorials::catalogue($profile, $membership);
    }

    public static function advance(array $state, string $action, int $count): array
    {
        $step = (int)$state['step'];
        $status = $state['status'];
        if ($action === 'replay') return ['status'=>'in_progress','step'=>0];
        if ($action === 'skip') return ['status'=>'dismissed','step'=>$step];
        if ($action === 'start' && $status !== 'completed') return ['status'=>'in_progress','step'=>$step];
        if ($status !== 'in_progress') throw new WingmanException('invalid_tutorial_action');
        return match ($action) {
            'next' => ['status'=>'in_progress','step'=>min($count-1,$step+1)],
            'back' => ['status'=>'in_progress','step'=>max(0,$step-1)],
            'finish' => $step === $count-1 ? ['status'=>'completed','step'=>$step] : throw new WingmanException('invalid_tutorial_action'),
            default => throw new WingmanException('invalid_tutorial_action'),
        };
    }
}
