<?php

declare(strict_types=1);

namespace Architex\Http;

use LogicException;

final class Router
{
    /** @var array<string,array<string,callable(Request):Response>> */
    private array $routes = [];

    /** @param callable(Request):Response $handler */
    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /** @param callable(Request):Response $handler */
    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /** @param callable(Request):Response $handler */
    public function put(string $path, callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    /** @param callable(Request):Response $handler */
    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    /** @param callable(Request):Response $handler */
    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$path][strtoupper($method)] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $routes = $this->routes[$request->path()] ?? null;
        if ($routes === null) {
            foreach ($this->routes as $pattern => $candidateRoutes) {
                if (!str_contains($pattern, '{')) {
                    continue;
                }
                $names = [];
                $quoted = preg_quote($pattern, '#');
                $regex = preg_replace_callback('/\\\\\{([A-Za-z][A-Za-z0-9_]*)\\\\\}/', static function (array $match) use (&$names): string {
                    $names[] = $match[1];
                    return '([^/]+)';
                }, $quoted);
                if (!is_string($regex) || preg_match('#^' . $regex . '$#D', $request->path(), $matches) !== 1) {
                    continue;
                }
                array_shift($matches);
                foreach ($names as $index => $name) {
                    $value = rawurldecode($matches[$index]);
                    if ($value === '' || str_contains($value, '/')) {
                        continue 2;
                    }
                    $request = $request->withAttribute($name, $value);
                }
                $routes = $candidateRoutes;
                break;
            }
        }
        if ($routes === null) {
            return Response::error(404, 'not_found', 'Route not found.');
        }

        $method = $request->method();
        $handler = $routes[$method] ?? ($method === 'HEAD' ? ($routes['GET'] ?? null) : null);
        if ($handler === null) {
            return Response::error(405, 'method_not_allowed', 'Method not allowed.', null, null, ['Allow' => implode(', ', $this->allowedMethods($routes))]);
        }

        $response = $handler($request);
        if (!$response instanceof Response) {
            throw new LogicException('Route handlers must return an HTTP response.');
        }

        return $method === 'HEAD' ? $response->withoutBody() : $response;
    }

    /** @param array<string,callable(Request):Response> $routes @return list<string> */
    private function allowedMethods(array $routes): array
    {
        $methods = array_keys($routes);
        if (in_array('GET', $methods, true) && !in_array('HEAD', $methods, true)) {
            $methods[] = 'HEAD';
        }
        sort($methods);
        return $methods;
    }
}
