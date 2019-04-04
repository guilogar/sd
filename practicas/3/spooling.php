<?php

require_once "vendor/autoload.php";

use Milo\Github\Api;
use Milo\Github\OAuth\Configuration;
use Milo\Github\OAuth\Token;
use Milo\Github\OAuth\Login;
use Milo\Github\Storages\SessionStorage;

session_start();

$config = new Configuration(
    "69bc143d157aee49455c",
    "4926f2f7519ba1da909b4388dd5062258bcc8ce1",
    ['user', 'repo']
);
$storage = new SessionStorage;
$login = new Login($config, $storage);
$token = NULL;

$appUrl = "https://guilogar.github.io";
//$appUrl = "http://localhost/universidad/sd/practicas/3/spooling.php";

if ($login->hasToken())
{
    $token = $login->getToken();
} else
{
    if (isset($_GET['back']))
    {
        $token = $login->obtainToken(
            $_GET['code'],
            $_GET['state']
        );
        # drop the 'code' and 'state' from URL
        header("Location: $appUrl");
        die();
    } else
    {
        # Performs redirect to Github page
        $login->askPermissions("$appUrl?back=1");
    }
}

/*
 *$api = new Api;
 *$en = $api->get('/users/guilogar');
 *$res = $api->decode($en);
 */

print_r($token);
