<?php 
/**
 * 启动API路径
 */
use \Workerman\Worker;
use \Workerman\WebServer;
use \GatewayWorker\Gateway;
use \GatewayWorker\BusinessWorker;
use \Workerman\Autoloader;

// 自动加载类
require_once __DIR__ . '/../../Workerman/Autoloader.php';
Autoloader::setRootPath(__DIR__);

// WebServer
$web = new WebServer("http://0.0.0.0:3699");
// WebServer进程数量
$web->count = 2;
// 设置站点根目录
$web->addRoot('www.your_domain.com', __DIR__.'/API');


// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}

