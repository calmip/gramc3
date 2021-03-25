<?php

use App\Kernel;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/config/bootstrap.php';

if ($_SERVER['APP_DEBUG']) {
    umask(0000);

    Debug::enable();
}

if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? false) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
}

if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? false) {
    Request::setTrustedHosts([$trustedHosts]);
}

// Le fichier adresses.txt contient la liste des adresses autorisées
// cf. adresses.txt.dist pour un modèle de fichier
$adresses_ip = file(__DIR__.'/../config/adresses.txt');
$adresses_ip = array_map('trim',$adresses_ip);
if ($adresses_ip === false)
{
	$adresses_ip = [];
}

// Si on est derrière un proxy il convient de commenter la ligne HTTP_X_FORWARDED_FOR !
if (isset($_SERVER['HTTP_CLIENT_IP'])
//    || isset($_SERVER['HTTP_X_FORWARDED_FOR'])
    || !(in_array(@$_SERVER['HTTP_X_REAL_IP'], $adresses_ip) || php_sapi_name() === 'cli-server')
) {
	header('HTTP/1.0 403 Forbidden');
    exit('You are not allowed to access this file. Check '.basename(__FILE__).' for more information. Your address is '.@$_SERVER['HTTP_X_REAL_IP']);
  }

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$request = Request::createFromGlobals();

// Nous sommes derrière un reverseproxy, commentez la ligne si ce n'est pas le cas
// cf. https://symfony.com/doc/4.4/deployment/proxies.html
Request::setTrustedProxies(['10.0.0.0/8'],Request::HEADER_X_FORWARDED_ALL);

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
