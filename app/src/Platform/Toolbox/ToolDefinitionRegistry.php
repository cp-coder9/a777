<?php

declare(strict_types=1);

namespace Architex\Platform\Toolbox;

final class ToolDefinitionRegistry
{
    /** @var array<string,ToolDefinition> */
    private array $definitions = [];

    public function register(ToolDefinition $definition): void
    {
        if (isset($this->definitions[$definition->id])) throw new ToolException('duplicate_tool_definition');
        $this->definitions[$definition->id] = $definition;
    }

    public function required(string $id): ToolDefinition
    {
        return $this->definitions[$id] ?? throw new ToolException('unknown_tool');
    }

    /** @return list<ToolDefinition> */
    public function forRole(string $role): array
    {
        return array_values(array_filter($this->definitions, static fn(ToolDefinition $definition):bool=>in_array($role,$definition->roles,true)));
    }

    /** @return list<ToolDefinition> */
    public function all(): array{return array_values($this->definitions);}
}
