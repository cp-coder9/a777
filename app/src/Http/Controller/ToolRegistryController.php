<?php
declare(strict_types=1);
namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;

final readonly class ToolRegistryController
{
    public function __construct(private string $registryPath) {}
    public function index(Request $request): Response
    {
        $json = file_get_contents($this->registryPath);
        if (!is_string($json)) return Response::error(503, 'tool_registry_unavailable', 'Tool registry is unavailable.');
        try {
            $registry = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            return Response::success($registry['tools'] ?? []);
        } catch (\JsonException) {
            return Response::error(503, 'tool_registry_unavailable', 'Tool registry is unavailable.');
        }
    }
}
