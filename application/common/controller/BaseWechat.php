<?php
namespace app\common\controller;
use think\Controller;
use tool\Wechat;
use wechat\Api;

//微信公众号公共管理控制类
class BaseWechat extends Controller {
    public $api = null;
    public $wechat = null;
    
    public function __construct(){
        parent::__construct();
        
        $this->api = new Api(config('config.APPID'),config('config.APPSECRET'),config('config.TOKEN'),config('config.ENCODINGAESKEY'));
        $this->wechat = new Wechat(config('config.APPID'),config('config.APPSECRET'));

    }
 
}
