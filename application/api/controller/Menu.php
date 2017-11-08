<?php
namespace app\api\controller;
use app\common\controller\BaseWechat;

//微信公众号自定义菜单控制类
class Menu extends BaseWechat{
    
    //创建菜单
    public function create(){
        $data = array();
        $data['button'] = array(
            array(
                'name' => '零件供求',
                'type' => 'miniprogram',
                'appid' => config('config.MIN_PROGRAM_APPID'),
                'pagepath' => 'pages/flashList/flashList',
                'url' => 'https://weixin.dev.chaichew.cn'
            ),
            array(
                'name' => '代理申请',
                'type' => 'miniprogram',
                'appid' => config('config.MIN_PROGRAM_APPID'),
                'pagepath' => 'pages/apply/apply',
                'url' => 'https://weixin.dev.chaichew.cn'
            ),
            array(
                'name' => '用户中心',
                'type' => 'miniprogram',
                'appid' => config('config.MIN_PROGRAM_APPID'),
                'pagepath' => 'pages/user/user',
                'url' => 'https://weixin.dev.chaichew.cn'
            )
        );
        
        $result = $this->wechat->createMenu($data);
        
        if($result['errmsg'] == 'ok'){
            return jsonReturn(SUCCESSED, '操作成功');
        }
        
        return jsonReturn(BAD_SERVER, $result['errmsg']);
    }
    
    

}