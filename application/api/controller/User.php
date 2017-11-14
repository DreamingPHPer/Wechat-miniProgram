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
            
        //根据用户openid获取用户信息
        $user_model = model('User');
        $user_info = $user_model->getUserInfoByUnionid($result['unionid']);
        
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
        }elseif(empty($user_info['small_openid'])){
            $add_user_result = $this->_addUser($data,true,array('unionid'=>$result['unionid']));
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
        
        $area_model = model('Area');
        
        if(!empty($user_info['province'])){
            $user_info['province_txt'] = $area_model->getNameByAreaCode($user_info['province']);
        }
        
        if(!empty($user_info['city'])){
            $user_info['city_txt'] = $area_model->getNameByAreaCode($user_info['city']);
        }
        
        if(!empty($user_info['area'])){
            $user_info['area_txt'] = $area_model->getNameByAreaCode($user_info['area']);
        }

        return jsonReturn(SUCCESSED, '用户信息获取成功', $user_info);
    }
    
    //更新数据库中的用户信息
    public function updateUser(){
        //检测用户登录状态
        $user_info = $this->checkLoginStatus();
        if(empty($user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }

        $headimgurl = input('post.avatar','');
        $nickname = input('post.username','');
        $truename = input('post.truename','');
        $phone = input('post.phone','');
        $province = input('post.province/d',0);
        $city = input('post.city/d',0);
        $area = input('post.area/d',0);
        
        $data = array();
        
        if(!empty($headimgurl)){
            $data['headimgurl'] = $headimgurl;
        }
        
        if(!empty($nickname)){
            $data['nickname'] = $nickname;
        }
        
        if(!empty($truename)){
            $data['truename'] = $truename;
        }
        
        if(!empty($phone)){
            $data['phone'] = $phone;
        }
        
        if(!empty($province)){
            $data['province'] = $province;
        }
        
        if(!empty($city)){
            $data['city'] = $city;
        }
        
        if(!empty($area)){
            $data['area'] = $area;
        }

        $validate_result = $this->validate($data, 'User');
        if($validate_result !== true){
            return jsonReturn(WRONG_PARAM, $validate_result);
        }

        $result = $this->_addUser($data,true,array('small_openid'=>$user_info['small_openid']));
        if($result !== false){
            return jsonReturn(SUCCESSED, '操作成功');
        }
        
        return jsonReturn(BAD_SERVER, '操作失败');
    }
    
    //添加用户信息
    private function _addUser($data,$if_update = false,$where = array()){
        $user_model = model('User');
        $user_agent_model = model('UserAgent');
        
        if($if_update){
            $user_info = $user_model->where($where)->find();
            
            if(empty($user_info)){
                return false;
            }
            
            //检测是否需要更新用户的代理商uid
            if(empty($user_info['agent_uid'])){
                $province = isset($data['province']) ? $data['province'] : 0;
                $city = isset($data['city']) ? $data['city'] : 0;
                $area = isset($data['area']) ? $data['area'] : 0;
                
                //查询代理商
                $agent_uids = $user_agent_model->getAgentUids($province,$city,$area);
                
                if(!empty($agent_uids['area'])){
                    $data['agent_uid'] = $agent_uids['area'];
                }elseif(!empty($agent_uids['city'])){
                    $data['agent_uid'] = $agent_uids['city'];
                }elseif(!empty($agent_uids['province'])){
                    $data['agent_uid'] = $agent_uids['province'];
                }
            }

            $result = $user_model->save($data,$where);
            
            if($result === false){
                return false;
            }
            
            if(!empty($data['agent_uid'])){
                //发送客服消息
                postCustomerMessage($data['agent_uid'], 10002, array('4000883993'));

                $area_model = model('Area');
                $province = $city = $area = '';
            
                if(!empty($data['province'])){
                    $province = $area_model->getNameByAreaCode($data['province']);
                }
            
                if(!empty($data['city'])){
                    $city = $area_model->getNameByAreaCode($data['city']);
                }
            
                if(!empty($data['area'])){
                    $area = $area_model->getNameByAreaCode($data['area']);
                }
            
                //添加系统消息
                $content = sprintf('【有新用户加盟】您代理的区域（%s）有新用户加入啦，赶紧登录后台查看吧！',$province.$city.$area);
                $user_message_model = model('UserMessage');
                $user_message_model->addMessage(array('uid'=>$data['agent_uid'],'title'=>'','content'=>$content));
            }
            
            return true;
            
        }else{
            //查询代理商
            if(isset($data['province']) && isset($data['city']) && isset($data['area'])){
                $agent_uid = $user_model->getAgentUid($data['province'],$data['city'],$data['area']);
                if(!empty($agent_uid)){
                    $data['agent_uid'] = $agent_uid;
                }
            }

            try {
                //添加新用户
                $user_model->save($data);
                $uid = $user_model->uid;
                
                //新增账户
                $user_account_model = model('UserAccount');
                $user_account_model->save(array('uid'=>$uid));

                if(!empty($data['agent_uid'])){
                    //发送客服消息
                    postCustomerMessage($data['agent_uid'], 10002, array('4000883993'));
                    
                    $area_model = model('Area');
                    $province = $city = $area = '';
                
                    if(!empty($data['province'])){
                        $province = $area_model->getNameByAreaCode($data['province']);
                    }
                
                    if(!empty($data['city'])){
                        $city = $area_model->getNameByAreaCode($data['city']);
                    }
                
                    if(!empty($data['area'])){
                        $area = $area_model->getNameByAreaCode($data['area']);
                    }
                
                    //添加系统消息
                    $content = sprintf('【有新用户加盟】您代理的区域（%s）有新用户加入啦，赶紧登录后台查看吧！',$province.$city.$area);
                    $user_message_model = model('UserMessage');
                    $user_message_model->addMessage(array('uid'=>$data['agent_uid'],'title'=>'','content'=>$content));
                    
                }

                return $uid;
            }catch(\Exception $e){
                \think\Log::write($e->getMessage());
                return false;
            }
           
        }
    }
}