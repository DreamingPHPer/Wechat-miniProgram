<?php
namespace app\common\controller;
use think\Controller;

//前台公共管理控制类
class BaseApi extends Controller {
    protected $sessionKey = '';
    protected $expire_time = 2592000;//单位：秒，默认30天
    
    public function __construct(){
        parent::__construct();
        
        if(!request()->isPost()){
            exit;
        }
        
        $this->sessionKey = getRequestHeaderParam('sessionkey');
    
        $cache = cache($this->sessionKey);
        if(!empty($cache)){
            cache($this->sessionKey,$cache,$this->expire_time);//将缓存时间重置
        }
    
    }
    
    //检测用户登录状态
    public function checkLoginStatus(){
        if(empty($this->sessionKey) || !$cache = cache($this->sessionKey)){
            return false;
        }
    
        $user_model = model('User');
        $field = 'uid,user_type,username,truename,nickname,phone,province,city,area,address,headimgurl,unionid,openid,small_openid,agent_uid,recommend_uid,if_subscribe,status';
        $user_info = $user_model->getUserInfoBySmallOpenid($cache['openid'],$field);
        
        if(empty($user_info)){
            return false;
        }
        
        $user_info['headimgurl'] = !empty($user_info['headimgurl']) ? getAttachmentUrl($user_info['headimgurl'],true) : '';
        
        return $user_info;
    }

}
