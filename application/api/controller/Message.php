<?php
namespace app\api\controller;
use app\common\controller\BaseApi;

//消息控制类
class Message extends BaseApi{
    public $user_info = null;
    
    public function __construct() {
        parent::__construct();
        $this->user_info = $this->checkLoginStatus();
    }
    
    //消息列表
    public function lists(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $need_page = input('post.needPage/d',0);
        $page = input('post.page/d',1);
        
        $user_message_model = model('UserMessage');
        
        $where = $query_params = array();
        $where['uid'] = $this->user_info['uid'];

        $query_params['page'] = $page;
        $need_page = $need_page ? true : false;
        
        $messages = $user_message_model->getMessages($where,'*',$need_page,$query_params);
        return jsonReturn(SUCCESSED, '获取成功');
    }
    
    /**
     * 发送客服消息
     */
    public function postCustomerMessage(){
        $uid = input('post.uid/d',0);//接受人uid
        $template_no = input('post.templateNo/d',0);//微信消息模板编号
        $data = input('post.data','');//模板参数 json字符串

        $result = postCustomerMessage($uid, $template_no, $data);
        if($result){
            return jsonReturn(SUCCESSED, '操作成功');
        }
        return jsonReturn(BAD_SERVER, '操作失败');
    }
    
}