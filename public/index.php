<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/config.php';
use App\Http\Router;
$router = new Router();
$router->get('/', fn() => require dirname(__DIR__).'/index.php');
$router->get('/u/{name}', function($p) { $_GET['u']=$p['name']; require dirname(__DIR__).'/u.php'; });
$router->get('/api/v1/dashboard', fn() => require dirname(__DIR__).'/public/api/v1/dashboard.php');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (is_file(dirname(__DIR__).$path) && !str_ends_with($path,'.php')) return false;
if (preg_match('#^/assets/#',$path) || $path==='/sw.js' || $path==='/manifest.webmanifest') return false;
$router->dispatch($m, $path);
