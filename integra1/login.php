<?php

require_once('system/LightOAuth2.php');

define('CLIENT_ID', '57099574867-fbc4d445qu04k6uko91mdghfuffastod.apps.googleusercontent.com');
define('CLIENT_SECRET', 'hiQirW8nDay5k7o8FxmYx47h');
define('CALLBACK', 'http://integra.eocenterprise.com/login.php');

$oauth = new LightOAuth2(CLIENT_ID, CLIENT_SECRET);
session_start();

if (!isset($_SESSION['user']))
{
    if (!isset($_SESSION['access_token']))
    {
        if (!isset($_GET['code']))
        {
            header("Location: " . $oauth->getAuthUrl('https://accounts.google.com/o/oauth2/auth', CALLBACK, ['scope' => 'email']));
            exit();
        }

        $obj = $oauth->getToken('https://accounts.google.com/o/oauth2/token', CALLBACK, $_GET['code']);
        $_SESSION['access_token'] = $obj->access_token;
    }

    $oauth->setToken($_SESSION['access_token']);
    $url = "https://www.googleapis.com/plus/v1/people/me";
    $response = $oauth->fetch($url);
    $obj = json_decode($response, true);
    $_SESSION['user'] = $obj['emails'][0]['value'];
}

header("Location: " . (isset($_SESSION['redirect']) ? $_SESSION['redirect'] : '/'));
?>
