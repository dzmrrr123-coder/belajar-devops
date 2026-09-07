<?php
namespace App\Http;
class Router {
    private array $routes = [];
    public function get(string $path, callable $h): void { $this->routes['GET '.$path] = $h; }
    public function post(string $path, callable $h): void { $this->routes['POST '.$path] = $h; }
    public function dispatch(string $method, string $path): void {
        $k = strtoupper($method).' '.$path;
        if (isset($this->routes[$k])) { ($this->routes[$k])(); return; }
        foreach ($this->routes as $rk=>$h) {
            [$rm,$rp] = explode(' ', $rk, 2);
            if ($rm !== strtoupper($method)) continue;
            $rx = '#^'.preg_replace('#\{(\w+)\}#','(?P<$1>[^/]+)', $rp).'$#';
            if (preg_match($rx, $path, $m)) { $h(array_filter($m,'is_string',ARRAY_FILTER_USE_KEY)); return; }
        }
        http_response_code(404); require dirname(__DIR__,2).'/404.php';
    }
}
