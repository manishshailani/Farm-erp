<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal, dependency-free HTTP router.
 *
 * Supports static segments and "{param}" placeholders, GET/POST/PUT/PATCH/
 * DELETE (PUT/PATCH/DELETE arrive via the _method form-spoofing handled in
 * Request::method()), and an ordered list of middleware callables that run
 * before the route handler and can short-circuit the request (used for
 * authentication and RBAC permission checks).
 */
final class Router
{
    /** @var array<string, array<int, array{pattern:string, regex:string, params:list<string>, handler:callable, middleware: list<callable>}>> */
    private array $routes = [];

    /** @var list<callable> */
    private array $globalMiddleware = [];

    public function middleware(callable $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('PUT', $pattern, $handler, $middleware);
    }

    public function patch(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('PATCH', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middleware);
    }

    private function add(string $method, string $pattern, callable $handler, array $middleware): void
    {
        $params = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, $pattern);
        $regex = '#^' . $regex . '$#';

        $this->routes[$method][] = [
            'pattern' => $pattern,
            'regex' => $regex,
            'params' => $params,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = rtrim($request->path(), '/');
        if ($path === '') {
            $path = '/';
        }

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                array_shift($matches);
                $args = array_combine($route['params'], $matches) ?: [];

                $chain = array_merge($this->globalMiddleware, $route['middleware']);
                $this->runChain($chain, $route['handler'], $request, $args);
                return;
            }
        }

        http_response_code(404);
        View::render('errors/404', [], '');
    }

    /** @param list<callable> $middleware */
    private function runChain(array $middleware, callable $handler, Request $request, array $args): void
    {
        $next = static function () use ($handler, $request, $args): void {
            $handler($request, $args);
        };

        // Build the chain from the inside out so middleware runs in the
        // order it was registered.
        foreach (array_reverse($middleware) as $mw) {
            $next = static function () use ($mw, $request, $args, $next): void {
                $mw($request, $args, $next);
            };
        }

        $next();
    }
}
