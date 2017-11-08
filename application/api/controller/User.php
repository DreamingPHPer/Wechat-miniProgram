<?php
namespace app\api\controller;
use app\common\controller\BaseApi;
use tool\Smallprogram;

//用户信息控制类
class User extends BaseApi{
   
    //用户登录
    public function login(){
        $code = input('post.code','');

        if(empty($code)){
            return jsonReturn(LOGIN_CODE_EMPTY, '用户登录凭证不能为空');
        }

        $small_program = new Smallprogram(config('config.MIN_PROGRAM_APPID'), config('config.MIN_PROGRAM_APPSECRET'));
        $result = $small_program->getSessionKey($code);
        
        if(isset($result['openid']) && $result['openid']){
            //根据用户openid获取用户信息
            $user_model = model('User');
            $user_info = $user_model->getUserInfoBySmallOpenid($result['openid']);
            
            //用户信息添加、更新结果
            $add_user_result = false;
            
            $data = array();
            $data['small_openid'] = $result['openid'];
            if(empty($user_info)){
                $data['reg_time'] = time();
                $data['nickname'] = input('post.nickname','');
                $data['headimgurl'] = input('post.avatar','');
                $data['status'] = 2;
                $data['unionid'] =  $result['unionid'];
                $add_user_result = $this->_addUser($data);
            }elseif(isset($result['unionid']) && empty($user_info['unionid'])){
                $data['unionid'] =  $result['unionid'];
                $add_user_result = $this->_addUser($data,true,array('small_openid'=>$data['small_openid']));
            }
            
            if($add_user_result !== false){
                return jsonReturn(LOGIN_FAILED, '登录失败');
            }
            
            //获取用户uid
            $uid = !empty($user_info) ? $user_info['uid'] : $add_user_result;

            //保存登录状态
            if(!empty($user_info)){
                $get_3rdsession_id = $small_program->get3rdSessionId($uid,16);
                if(cache($get_3rdsession_id,$result,$this->expire_time)){
                    return jsonReturn(SUCCESSED, '登录成功',array('sessionKey'=>$get_3rdsession_id));
                }
            }
            return jsonReturn(LOGIN_FAILED, '登录失败');
        }else{
            return jsonReturn(LOGIN_FAILED, $result['errmsg']);
        }

    }
}