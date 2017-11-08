<?php
/**
 * 定时任务处理程序
 */
use Workerman\Worker;
use Workerman\Lib\Timer;
use tool\Task;

if(!defined('GLOBAL_START'))
{
    // 定义应用目录
    define('APP_PATH', __DIR__ . '/../../application/');
    
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
    require __DIR__ . '/../../thinkphp/base.php';
    
    \think\App::initCommon();
}

$task_alarm_worker = new Worker();

// worker名称
$task_alarm_worker->name = 'taskAlarmWorker';

// Worker进程数量
$task_alarm_worker->count = 1;

$task_alarm_worker->onWorkerStart = function($task_alarm_worker){
    $task_handle = new Task();
    $task_handle->start();
};

// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}

