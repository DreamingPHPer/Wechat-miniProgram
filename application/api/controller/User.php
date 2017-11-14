<?php
namespace app\api\controller;
use app\common\controller\BaseApi;
use tool\Smallprogram;

//用户信息控制类
class User extends BaseApi{
    //获取用户相关的统计信息
    public function index(){
        $user_info = $this->checkLoginStatus();
        if(empty($user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $data = array();
        $data['user'] = $user_info;
        
        $order_model = model('Order');
        
        //获取卖家订单数
        $seller = $seller_where = array();
        $seller_where['offer_user_id'] = $user_info['uid'];
        $seller['unpay'] = $order_model->getNum(array_merge($seller_where,array('order_status'=>0)));
        $seller['undelivery'] = $order_model->getNum(array_merge($seller_where,array('order_status'=>1)));
        $seller['unreceive'] = $order_model->getNum(array_merge($seller_where,array('order_status'=>2)));
        $data['seller'] = $seller;
        
        //获取买家订单数
        $buyer = $buyer_where = array();
        $buyer_where['user_id'] = $user_info['uid'];
        $buyer['unpay'] = $order_model->getNum(array_merge($buyer_where,array('order_status'=>0)));
        $buyer['undelivery'] = $order_model->getNum(array_merge($buyer_where,array('order_status'=>1)));
        $buyer['unreceive'] = $order_model->getNum(array_merge($buyer_where,array('order_status'=>2)));
        $data['buyer'] = $buyer;
        
        return jsonReturn(SUCCESSED, '获取成功', $data);
    }
    
    //用户登录
    public function login(){
        $code = input('post.code','');

        if(empty($code)){
            return jsonReturn(LOGIN_CODE_EMPTY, '用户登录凭证不能为空');
        }

        $small_program = new Smallprogram(config('config.MIN_PROGRAM_APPID'), config('config.MIN_PROGRAM_APPSECRET'));
        $result = $small_program->getSessionKey($code);
        
        if(empty($result['openid'])){
            return jsonReturn(LOGIN_FAILED, $result['errmsg']);
        }elseif(empty($result['unionid'])){
            //关注了公众号，才会返回unionid
            return jsonReturn(NO_AUTHORITY, '请先关注相关公众号');
        }
            
        //TODO::根据用户openid获取用户信息，没有信息就添加信息
        $user_info = array();
        
        //保存登录状态
        if(!empty($user_info)){
            $get_3rdsession_id = $small_program->get3rdSessionId($user_info['uid'],16);
            if(cache($get_3rdsession_id,$result,$this->expire_time)){
                return jsonReturn(SUCCESSED, '登录成功',array('sessionKey'=>$get_3rdsession_id));
            }
        }
        return jsonReturn(LOGIN_FAILED, '登录失败');

    }
    
    /**
     * 获取用户信息
     */
    public function getUserInfo(){
        
        //检测用户登录状态
        $user_info = $this->checkLoginStatus();
        
        if(empty($user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
	//TODO::获取用户信息
	$user_info = array();

        return jsonReturn(SUCCESSED, '用户信息获取成功', $user_info);
    }
    
    //更新数据库中的用户信息
    public function updateUser(){
        //检测用户登录状态
        $user_info = $this->checkLoginStatus();
        if(empty($user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }

        //TODO::更新用户信息
	$result = true;//更新成功

        if($result !== false){
            return jsonReturn(SUCCESSED, '操作成功');
        }
        
        return jsonReturn(BAD_SERVER, '操作失败');
    }
}