<?php

declare(strict_types=1);

namespace App\Support;

final class Router
{
    private array $routes = [];

    public function get(string $path, array|callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array|callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function add(string $method, string $path, array|callable $handler): void
    {
        $this->routes[strtoupper($method)][$this->normalize($path)] = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $path = $this->normalize($path);
        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            Response::html(View::render('errors/404', ['path' => $path]), 404);
            return;
        }

        $result = $this->call($handler);
        if (is_string($result)) {
            Response::html($result);
        }
    }

    private function call(array|callable $handler): mixed
    {
        if (is_callable($handler)) {
            return $handler();
        }

        [$class, $method] = $handler;
        $controller = new $class();
        return $controller->$method();
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
