<?php
namespace app\api\model;
use think\Model;

//求购信息议价控制模型
class BuypartsNegotiatedPrice extends Model{
    /**
     * 状态获取器
     * @param unknown $value
     * @return string[]|string
     */
    public function getStatusAttr($value = null){
        $status = array(
            0 => '议价中',
            1 => '接受议价',
            2 => '议价失败'
        );
        
        if(is_null($value)){
            return $status;
        }
        
        return isset($status[$value]) ? $status[$value] : '';
    }

    /**
     * 获取议价信息
     * @param unknown $where
     * @param string $field
     * @param string $order
     */
    public function getNegotiatedPrices($where,$field = '*',$order = 'add_time desc'){
        $offer_prices = $this->where($where)->field($field)->order($order)->select();
        return $offer_prices;
    }
    
    /**
     * 获取议价数量
     * @param unknown $where
     */
    public function getNum($where){
        return $this->where($where)->count('id');
    }
    
    /**
     * 获取议价信息
     * @param unknown $where
     */
    public function getInfo($where,$field = '*',$order = 'create_time desc'){
        return $this->where($where)->field($field)->order($order)->find();
    }
    
    //获取议价价格
    public function getPrice($where){
        $result = $this->where($where)->field('price')->order('create_time desc')->find();
        return !empty($result) ? $result['price'] : '';
    }
}