<?php
require_once 'Class.LoadConfig.php';
use DanMu\Server\LoadConfig;
use Workerman\Worker;
require_once './Workerman/Autoloader.php';

$L = new LoadConfig();

$Auth = array();

// 创建一个Worker监听2346端口，使用websocket协议通讯
$ws_worker = new Worker("websocket://0.0.0.0:2346");

// 启动4个进程对外提供服务
$ws_worker->count = $L["Server"]["WorkerNumber"];

// 当收到客户端发来的数据后返回hello $data给客户端
$ws_worker->onMessage = function ($connection, $data) use ($ws_worker, &$Auth) {
	$obj = json_decode($data);
	$returnObj = new stdClass();
	$key = $obj->key;
	if (empty($key)) {
		$returnObj->status = false;
		$returnObj->reason = "NO KEY NO WORK";
		$returnObj->errorCode = "9901";
		$connection->send(json_encode($returnObj));
	}
	switch ($obj->action) {
		case 'auth':
			$Auth[$key] = true;
			var_dump($Auth);
			break;
		case 'speak':
			var_dump($Auth);
			if (!empty($Auth[$key]) && $Auth[$key] === true) {
				foreach ($ws_worker->connections as $con) {
					$con->send($obj->content);
				}
			} else {
				$returnObj->status = false;
				$returnObj->reason = "UNAUTH";
				$returnObj->errorCode = "9900";
				$connection->send(json_encode($returnObj));
			}
			break;
		default:
			$returnObj->status = false;
			$returnObj->reason = "Action UNKNOW";
			$returnObj->errorCode = "8800";
			$connection->send(json_encode($returnObj));
			break;
	}

};

// 运行worker
Worker::runAll();
