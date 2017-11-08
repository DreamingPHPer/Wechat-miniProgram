<?php
namespace app\api\model;
use think\Model;

//用户地址控制模型
class UserAddress extends Model{
    //获取地址详情
    public function getInfo($where, $field = "*"){
        $address = $this->where($where)->field($field)->find();
        
        if(!empty($address)){
            $area_model = model('Area');
            $address['add_time'] = !empty($address['add_time']) ? date('Y-m-d H:i:s',$address['add_time']) : '';
            $address['province_txt'] = !empty($address['province']) ? $area_model->getNameByAreaCode($address['province']) : '';
            $address['city_txt'] = !empty($address['city']) ? $area_model->getNameByAreaCode($address['city']) : '';
            $address['area_txt'] = !empty($address['area']) ? $area_model->getNameByAreaCode($address['area']) : '';
        }
        
        return $address;
    }
    
    //获取地址
    public function getAddresses($where, $field = "*", $order = "is_default desc"){
        $addresses = $this->where($where)->field($field)->order($order)->select();
        if(!empty($addresses)){
            $area_model = model('Area');
            
            foreach($addresses as &$address){
                $address['add_time'] = !empty($address['add_time']) ? date('Y-m-d H:i:s',$address['add_time']) : '';
                $address['province_txt'] = !empty($address['province']) ? $area_model->getNameByAreaCode($address['province']) : '';
                $address['city_txt'] = !empty($address['city']) ? $area_model->getNameByAreaCode($address['city']) : '';
                $address['area_txt'] = !empty($address['area']) ? $area_model->getNameByAreaCode($address['area']) : '';
            }
        }
        
        return $addresses;
    }
    
    //统计地址数量
    public function getNum($where){
        return $this->where($where)->count('address_id');
    }
    
    //判断是否存在默认地址
    public function ifHasDefaultAddress($uid){
        $where = array();
        
        $where['uid'] = $uid;
        $where['status'] = 1;
        $where['is_default'] = 1;
        
        $count = $this->getNum($where);
        
        return $count > 0 ? true : false;
    }
}