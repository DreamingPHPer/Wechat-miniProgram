<?php
namespace app\api\model;
use think\Model;

//求购信息控制模型
class Buyparts extends Model{

    //求购信息状态获取器
    public function getStatusAttr($value=null){
        $status = array(
            1 => '求购中',
            2 => '已成交',
            3 => '求购失效'
        );
    
        if(is_null($value)){
            return $status;
        }
    
        return isset($status[$value]) ? $status[$value] : '';
    
    }
    
    //获取求购详情
    public function getInfo($where,$field="*"){
        return $this->where($where)->field($field)->find();
    }
    
    //获取报价数
    public function getOfferPriceNum($where){
        return model('BuypartsOfferPrice')->where($where)->count('offer_id');
    }
    
    //获取求购信息
    public function getBuyparts($where, $field = "*", $if_paging = false, $query_params = array(), $page_size = 10){
        if($if_paging){
            return $this->where($where)->field($field)->order('add_time desc')->paginate($page_size,false,$query_params);
        }
        
        return $this->where($where)->field($field)->order('add_time desc')->select();
    }
    
}