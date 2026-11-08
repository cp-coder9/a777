<?php

declare(strict_types=1);

namespace Architex\Platform\Wingman;

/** Bounds user input and labels generated output before it is persisted or returned. */
final class WingmanContentPolicy
{
    public const DISCLAIMER = 'AI-generated content. Review before professional use.';
    public const TRUNCATED = '[Response truncated.]';

    public static function prompt(mixed $value): string
    {
        if (!is_string($value)) throw new WingmanException('invalid_prompt');
        $prompt = trim($value);
        $length = mb_strlen($prompt);
        if ($length < 3 || $length > 4000) throw new WingmanException('invalid_prompt');
        return $prompt;
    }

    public static function response(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') throw new WingmanException('provider_response_invalid');
        $content = trim($value);
        if (mb_strlen($content) > 8000) {
            $content = mb_substr($content, 0, 8000) . "\n\n" . self::TRUNCATED;
        }
        return $content . "\n\n" . self::DISCLAIMER;
    }
}
