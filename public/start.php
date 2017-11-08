<?php
/**
 * 异步任务执行文件 
 */

// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');
define('GLOBAL_START', 1);

ini_set('display_errors', 'on');

// 检查扩展
if(!extension_loaded('pcntl'))
{
    exit("Please install pcntl extension.\n");
}

if(!extension_loaded('posix'))
{
    exit("Please install posix extension.\n");
}

// 加载基础文件
require __DIR__ . '/../thinkphp/base.php';

\think\App::initCommon();

// 加载所有Applications/*/start.php，以便启动所有服务
foreach(glob(__DIR__.'/*/start.php') as $start_file)
{
    require_once $start_file;
}

// 运行所有服务
Workerman\Worker::runAll();
