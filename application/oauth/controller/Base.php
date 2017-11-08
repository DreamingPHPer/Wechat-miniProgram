<?php
namespace app\oauth\controller;
use think\Controller;

class Base extends Controller{
    protected $server = null;
    
    public function __construct(){
        $config = config('database');
        $dsn = 'mysql:dbname=' . $config['database'] . ';host=' . $config['hostname'];
        $username = $config['username'];
        $password = $config['password'];

        $storage = new \OAuth2\Storage\Pdo(array('dsn'=>$dsn,'username'=>$username,'password'=>$password));
        $this->server = new \OAuth2\Server($storage);
        
        //添加客户端验证
        $this->server->addGrantType(new \OAuth2\GrantType\ClientCredentials($storage));
    }
    
}