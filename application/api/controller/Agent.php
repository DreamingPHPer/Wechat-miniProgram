<?php
namespace app\api\controller;
use app\common\controller\BaseApi;
use tool\Wechat;

//代理商控制类
class Agent extends BaseApi{
    public $user_info = null;
    
    public function __construct() {
        parent::__construct();
        $this->user_info = $this->checkLoginStatus();
    }
    
    //申请代理商
    public function apply(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $user_info = $this->checkLoginStatus();
        if(empty($user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $user_model = model('User');
        if(!$user_model->isComplete($user_info['uid'])){
            return jsonReturn(NO_AUTHORITY, '请完善个人信息');
        }

        //查询用户的申请记录
        $user_agent_model = model('UserAgent');
        $agent = $user_agent_model->getAgentInfo(array('uid'=>$this->user_info['uid'],'status'=>array('neq',2)));
        if(!empty($agent)){
            if($agent['status'] < 1){
                return jsonReturn(NO_AUTHORITY, '您的代理申请正在审核中');
            }elseif($agent['status'] == 1){
                return jsonReturn(NO_AUTHORITY, '您已是代理商，请不要重复申请');
            }
        }
        
        //如果只是检测是否可以申请代理，到此处直接退出
        if(input('?post.ifCheck') && input('post.ifCheck/d')){
            return jsonReturn(SUCCESSED, '您当前可以申请代理');
        }
        
        $data = array();
        $data['province'] = input('post.province/d',0);
        $data['city'] = input('post.city/d',0);
        $data['area'] = input('post.area/d',0);
        
        if(empty($data['province']) && empty($data['city']) && empty($data['area'])){
            return jsonReturn(WRONG_PARAM, '请选择代理区域');
        }

        $where = array();
        
        //判断代理商级别
        if(!empty($data['province'])){
            $data['agent_level'] = 1;
            $where['province'] = $data['province'];
        }
        if(!empty($data['city'])){
            $data['agent_level'] = 2;
            $where['city'] = $data['city'];
        }
        if(!empty($data['area'])){
            $data['agent_level'] = 3;
            $where['area'] = $data['area'];
        }
        $where['status'] = 1;
        
        //判断当前选择的区域是否存在代理商
        $user_agent_model = model('UserAgent');
        $user_agent = $user_agent_model->getAgentInfo($where);
        if(!empty($user_agent)){
            return jsonReturn(NO_AUTHORITY, '当前代理区已存在代理商');
        }

        $data['uid'] = $user_info['uid'];
        $data['create_time'] = time();
        $data['agent_uid'] = $user_info['agent_uid'];

        if($user_agent_model->save($data)){
            //给平台添加系统消息
            $content = '【审核通知】有新的代理商申请，请在3个工作日内完成处理。';
            $user_message_model = model('SysconfMessage');
            $user_message_model->addMessage($this->user_info['uid'],'',$content);
            
            //发送客服消息
            postCustomerMessage($user_info['uid'], 10003, array($data['agent_level'],4000883993));
            
            return jsonReturn(SUCCESSED, '操作成功');
        } 
        
        return jsonReturn(BAD_SERVER,'操作失败');
    }

    //获取二维码
    public function getQrcode(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $uid = input('post.id',0);
        if(empty($uid)){
            return jsonReturn(WRONG_PARAM, '参数id不能为空');
        }
    
        $user_model = model('User');
        $user = $user_model->getUserInfoByUid($uid);
        if(empty($user)){
            return jsonReturn(NO_RESULT, '该用户不存在');
        }
    
        if($user['user_type'] != 1){
            return jsonReturn(NO_AUTHORITY, '只有代理商才可以获取二维码');
        }
    
        $ticket = $this->getQrcodeTicket($uid);
    
        $qrcode = '';
        if(!empty($ticket)){
            $wechat = new Wechat(config('config.APPID'), config('config.APPSECRET'));
            $qrcode = $wechat->createQrcode($ticket);
        }
    
        return jsonReturn(SUCCESSED, '获取成功', $qrcode);
    
    }
    
    //创建二维码
    private function getQrcodeTicket($scene){
        $data = array();
        $data['action_name'] = 'QR_LIMIT_STR_SCENE';
        $data['action_info'] = array(
            'scene' => array(
                'scene_str' => $scene
            ),
        );
    
        $wechat = new Wechat(config('config.APPID'), config('config.APPSECRET'));
        $result = $wechat->getQrcodeTicket($data);
        return !empty($result['ticket']) ? $result['ticket'] : '';
    }
    
}