<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * Minimal clean-URL router.
 * Patterns use {name} (segment) and {name:.+} (greedy) placeholders.
 */
final class Router
{
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->routes[] = [$pattern, $handler, 'GET'];
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->routes[] = [$pattern, $handler, 'POST'];
    }

    public function any(string $pattern, callable $handler): void
    {
        $this->routes[] = [$pattern, $handler, null];
    }

    public function dispatch(string $path, string $method): void
    {
        $path = '/' . trim($path, '/');
        foreach ($this->routes as [$pattern, $handler, $m]) {
            if ($m !== null && $m !== $method) continue;
            $regex = preg_replace_callback('/\{(\w+)(?::([^}]+))?\}/', function ($mm) {
                $sub = $mm[2] ?? '[^/]+';
                return '(?P<' . $mm[1] . '>' . $sub . ')';
            }, str_replace(')', '\)', str_replace('(', '\(', $pattern)));
            if (preg_match('#^' . $regex . '$#u', $path, $params)) {
                $args = array_filter($params, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler($args);
                return;
            }
        }
        View::notFound();
    }
}
