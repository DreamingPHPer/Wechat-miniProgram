<?php
namespace app\oauth\controller;

//AccessToken分发控制类
class Token extends Base{
    public function index(){
        $server = $this->server;
        
        //获取请求
        $request = \OAuth2\Request::createFromGlobals();
        
        //发送AccessToken
        $server->handleTokenRequest($request)->send();
        
    }
}