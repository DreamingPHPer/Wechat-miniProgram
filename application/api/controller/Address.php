<?php
namespace app\api\controller;
use app\common\controller\BaseApi;

//地址信息控制类
class Address extends BaseApi{
    public $user_info = null;
    
    public function __construct() {
        parent::__construct();
        $this->user_info = $this->checkLoginStatus();
    }
    
    //获取地址列表
    public function lists(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $user_address_model = model('UserAddress');
        
        $where = array();
        $where['uid'] = $this->user_info['uid'];
        $where['status'] = array('neq',0);
        
        $addresses = $user_address_model->getAddresses($where);

        return jsonReturn(SUCCESSED, '获取成功', $addresses);
    }
    
    //添加、编辑地址
    public function add(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $data = array();
        $data['name'] = input('post.contactUser', '');
        $data['tel'] = input('post.contactPhone', '');
        $data['province'] = input('post.province/d', 0);
        $data['city'] = input('post.city/d', 0);
        $data['area'] = input('post.area/d', 0);
        $data['address'] = input('post.address', '');

        $validate_result = $this->validate($data, 'UserAddress');
        if($validate_result !== true){
            return jsonReturn(WRONG_PARAM, $validate_result);
        }
        
        $is_edit = false;
        if(input('?post.id') && $address_id = input('post.id/d')){
            $is_edit = true;
            $data['update_time'] = time();
        }
        
        $user_address_model = model('UserAddress');
        
        if($is_edit){
            $where = array(
                'uid'=>$this->user_info['uid'],
                'address_id'=>$address_id,
                'status'=>array('neq',0)
            );
            
            $address = $user_address_model->getInfo($where);
            
            if(empty($address)){
                return jsonReturn(NO_AUTHORITY, '当前用户不能编辑当前信息');
            }
            
            $result = $user_address_model->save($data,array('address_id'=>$address_id));
            
        }else{
            $data['add_time'] = time();
            $data['uid'] = $this->user_info['uid'];
            
            //查询当前用户是否存在默认地址
            if(!$this->ifHasDefaultAddress()){
                $data['is_default'] = 1;
            }
            
            $result = $user_address_model->save($data);
        }
        
        if($result !== false){
            return jsonReturn(SUCCESSED, '操作成功');
        }
        
        return jsonReturn(BAD_SERVER, '操作失败');
    }
    
    //获取地址详情
    public function detail(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $address_id = input('post.id/d',0);
        
        if(!$address_id){
            return jsonReturn(WRONG_PARAM, '地址ID传递错误');
        }

        $user_address_model = model('UserAddress');
        
        $where = array();
        $where['address_id'] = $address_id;
        
        $address = $user_address_model->getInfo($where);
        
        if(empty($address)){
            return jsonReturn(NO_RESULT, '暂无相关信息');
        }
        
        if($address['uid'] != $this->user_info['uid']){
            return jsonReturn(NO_AUTHORITY, '当前用户不能查看当前信息');
        }
        
        return jsonReturn(SUCCESSED, '获取成功', $address);
    }
    
    //设置默认地址
    public function setDefault(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $address_id = input('post.id/d',0);
        
        if(!$address_id){
            return jsonReturn(WRONG_PARAM, '地址ID传递错误');
        }

        $user_address_model = model('UserAddress');
        
        $where = array();
        $where['address_id'] = $address_id;
        
        $address = $user_address_model->getInfo($where);
        
        if(empty($address)){
            return jsonReturn(NO_RESULT, '暂无相关信息');
        }
        
        if($address['uid'] != $this->user_info['uid']){
            return jsonReturn(NO_AUTHORITY, '当前用户不能查看当前信息');
        }
        
        \think\Db::startTrans();
        try{
            $user_address_model->update(array('is_default'=>0),array('uid'=>$this->user_info['uid'],'is_default'=>1));
            $user_address_model->update(array('is_default'=>1),array('uid'=>$this->user_info['uid'],'address_id'=>$address_id));
            \think\Db::commit();
            return jsonReturn(SUCCESSED, '操作成功');
        }catch(\Exception $e){
            \think\Db::rollback();
            return jsonReturn(BAD_SERVER, '操作失败');
        }
        
    }
    
    //删除地址
    public function del(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $address_id = input('post.id/d',0);
        
        if(!$address_id){
            return jsonReturn(WRONG_PARAM, '地址ID传递错误');
        }

        $user_address_model = model('UserAddress');
        
        $where = array();
        $where['address_id'] = $address_id;
        
        $address = $user_address_model->getInfo($where);
        
        if(empty($address)){
            return jsonReturn(NO_RESULT, '暂无相关信息');
        }
        
        if($address['uid'] != $this->user_info['uid']){
            return jsonReturn(NO_AUTHORITY, '当前用户不能操作当前信息');
        }
        
        $count = $user_address_model->getNum(array('uid'=>$this->user_info['uid'],'status'=>1));
        if($count > 1 && $address['is_default']){
            return jsonReturn(NO_AUTHORITY, '请至少保留一个默认地址');
        }

        $result = $user_address_model->save(array('status'=>0),array('address_id'=>$address_id,'uid'=>$this->user_info['uid']));
    
        if($result !== false){
            return jsonReturn(SUCCESSED, '操作成功');
        }
        
        return jsonReturn(BAD_SERVER, '操作失败');
    }

    //检测当前用户是否存在默认地址
    private function ifHasDefaultAddress(){
        $user_address_model = model('UserAddress');
        
        $where = array();
        $where['uid'] = $this->user_info['uid'];
        $where['status'] = array('neq',0);
        $where['is_default'] = 1;
        
        $result = $user_address_model->where($where)->count('address_id');
        
        return $result > 0 ? true : false;
    }
    
}