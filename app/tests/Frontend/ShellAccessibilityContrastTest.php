<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class ShellAccessibilityContrastTest extends TestCase
{
    public function testSharedShellUsesReadableNavigationContrastTokens(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/architex-v1.css');

        foreach ([
            '.kicker{font-size:9px;text-transform:uppercase;letter-spacing:.11em;color:#126965',
            '.nav-heading{padding:0 14px 6px;text-transform:uppercase;letter-spacing:.1em;color:#526074',
            '.badge{margin-left:auto;font-size:8px;padding:2px 6px;border-radius:99px;background:#edf3f2;color:#526074',
            '.scope-pill{font-size:8px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;padding:5px 8px;border-radius:99px;background:rgba(25,183,176,.08);color:#14756f',
            '.project-chip{font-size:9px;padding:6px 9px;border:1px solid rgba(25,183,176,.16);background:var(--aqua);border-radius:9px;color:#14756f',
        ] as $token) {
            self::assertStringContainsString($token, $css);
        }

        foreach ([
            ['126965', 'feffff'],
            ['526074', 'feffff'],
            ['526074', 'edf3f2'],
            ['14756f', 'ebf8f8'],
            ['14756f', 'dff5f2'],
        ] as [$foreground, $background]) {
            self::assertGreaterThanOrEqual(4.5, $this->contrastRatio($foreground, $background));
        }
    }

    private function contrastRatio(string $foreground, string $background): float
    {
        $luminance = static function (string $hex): float {
            $channels = array_map(
                static function (string $channel): float {
                    $value = hexdec($channel) / 255;

                    return $value <= 0.04045
                        ? $value / 12.92
                        : (($value + 0.055) / 1.055) ** 2.4;
                },
                str_split($hex, 2),
            );

            return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
        };

        $first = $luminance($foreground);
        $second = $luminance($background);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }
}
