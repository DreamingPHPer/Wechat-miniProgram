<?php
namespace app\oauth\controller;

//资源控制类
class Resource extends Base{
    
    //资源
    public function index(){
        $server = $this->server;
        
        //获取请求
        $request = \OAuth2\Request::createFromGlobals();
        
        //验证AccessToken
        if (!$server->verifyResourceRequest($request)) {
            $server->getResponse()->send();
            die;
        }
        
        return json(array('success' => true, 'message' => 'You accessed my APIs!'));
    }
}