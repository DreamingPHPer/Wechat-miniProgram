<?php
namespace app\api\controller;
use app\common\controller\BaseWechat;

//微信公众号服务器验证控制类
class Index extends BaseWechat{

    //微信服务器验证
    public function Index(){
        $signature = input('param.signature','');
        $timestamp = input('param.timestamp','');
        $nonce = input('param.nonce','');
        $echostr = input('param.echostr','');
        if(!empty($echostr)){
            if($this->api->checkSignature($signature, $timestamp, $nonce)){
                return $echostr;
            }
        }else{
            $openid = input('param.openid','');
            $encryptType = input('param.encrypt_type','');
            $msgSignature = input('param.msg_signature','');
            $ifEncryType = empty($encryptType) || $encryptType == 'raw' ? false : true;
            return $this->react($openid,$ifEncryType,$msgSignature,$timestamp,$nonce);
        }
        
    }

    //推送响应
    private function react($openid,$ifEncryType = false,$msgSignature = '',$timestamp = '',$nonce = ''){
        //处理事件推送
        if(isset($GLOBALS['HTTP_RAW_POST_DATA'])){
            $string = $GLOBALS['HTTP_RAW_POST_DATA'];
            return $this->api->react($openid,$string,$ifEncryType,$msgSignature,$timestamp,$nonce);
        }
        
        return '';
    }

}