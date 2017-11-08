<?php
namespace app\api\controller;
use app\common\controller\BaseApi;

//省市区控制类
class Area extends BaseApi{
    public $user_info = null;
    
    public function __construct() {
        parent::__construct();
        $this->user_info = $this->checkLoginStatus();
    }

    //省市区数据字典
    public function getData(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $express_list_model = model('ExpressList');
        $expresses = $express_list_model->order('sort desc')->select();
        return jsonReturn(SUCCESSED, '获取成功',$expresses);
    }
    
    
}