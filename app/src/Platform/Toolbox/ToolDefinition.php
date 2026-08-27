<?php

declare(strict_types=1);

namespace Architex\Platform\Toolbox;

use Closure;

final readonly class ToolDefinition
{
    /** @param list<string> $roles @param list<string> $tags @param array<string,string> $inputSchema @param array<string,string> $outputSchema @param list<string> $downstreamWriteBack @param Closure(array<string,mixed>,array<string,string>):array<string,mixed> $execute */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public array $roles,
        public string $category,
        public string $route,
        public array $tags,
        public array $inputSchema,
        public array $outputSchema,
        public bool $requiresProfessionalConfirmation,
        public bool $allowsAiDraft,
        public bool $locksOnIssue,
        public array $downstreamWriteBack,
        public Closure $execute,
    ) {
        if (preg_match('/\A[a-z][a-z0-9_]{1,79}\z/', $id) !== 1 || preg_match('/\A\d+\.\d+\.\d+\z/', $version) !== 1) throw new ToolException('invalid_tool_definition');
        if (!str_starts_with($route, '/toolbox/standalone/') || count($tags) < 3 || $roles === [] || $inputSchema === [] || $outputSchema === []) throw new ToolException('invalid_tool_definition');
    }
}
