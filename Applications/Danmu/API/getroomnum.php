<?php

use \Workerman\Protocols\Http;
use \GatewayWorker\Lib\Gateway;

$message = array();
Http::header("Content-Type: application/json");
$message['total'] = Gateway::getAllClientCount();

if(!isset($_GET['roomid'])) {
    $message["state"] = false;
    Http::end(json_encode($message));
}

$message['state'] = true;
$message['num'] = Gateway::getClientCountByGroup($_GET['roomid']);
Http::end(json_encode($message));