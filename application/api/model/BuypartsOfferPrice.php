<?php
namespace app\api\model;
use think\Model;

//求购信息报价控制模型
class BuypartsOfferPrice extends Model{
    public function getStatusAttr($value = null){
        $status = array(
            0 => '议价中',
            1 => '报价成功',
            2 => '报价失败'
        );
        
        if(is_null($value)){
            return $status;
        }
        
        return isset($status[$value]) ? $status[$value] : '';
    }
    
    /**
     * 获取报价详情
     * @param unknown $where
     * @param string $field
     */
    public function getInfo($where,$field = "*"){
        $offer_price = $this->where($where)->field($field)->find();
        
        $buypart = array();
        if(!empty($offer_price)){
            $buypart_model = model('Buyparts');
            $buypart_img_model = model('BuypartsImg');
            $buypart_field = "buy_id,title,img_count";
            $buypart = $buypart_model->getInfo(array('buy_id'=>$offer_price['buy_id']),$buypart_field);
            if(!empty($buypart) && $buypart['img_count']){
                $buypart['images'] = $buypart_img_model->getImgsByBuyId($buypart['buy_id']);
            }
        }
        $offer_price['buypart'] = $buypart;
        
        return $offer_price;
    }
    
    //获取报价信息
    public function getOfferPrices($where,$field = '*',$order = 'add_time desc'){
        $offer_prices = $this->where($where)->field($field)->order($order)->select();
        return $offer_prices;
    }
    
    //获取最高报价
    public function getExtOfferPrice($buy_id, $type = "max") {
        return $this->where(array('buy_id'=>$buy_id,'status'=>0))->$type('price');
    }
    
    //获取成交价
    public function getDealOfferPrice($buy_id){
        $result = $this->where(array('buy_id'=>$buy_id,'status'=>1))->field('price')->find();
        return !empty($result) ? $result['price'] : 0;
    }
    
    //根据报价id获取求购信息
    public function getBuypartByOfferId($offer_id){
        //求购信息表查询字段
        $field = "b.buy_id,b.user_id buyer_id,b.title,b.contacts buyer,b.mobile buyer_contact,b.province,b.city,b.area,b.address,b.is_delete,b.is_dongjie,b.status buypart_status,";
        //报价表查询字段
        $field .= "bop.offer_id,bop.user_id seller_id,bop.contacts seller,bop.mobile seller_contact,bop.price offer_price";
        return $this->alias('bop')
            ->field($field)
            ->join('__BUYPARTS__ b','bop.buy_id = b.buy_id')
            ->where(array('bop.offer_id'=>$offer_id))
            ->find();
    }
    
    //根据报价信息获取非重复的求购信息id
    public function getUndistinctBuyIds($where,$order = 'buy_id desc'){
        $offer_prices = $this->distinct(true)->field('buy_id')->order($order)->where($where)->select();
        
        $buy_ids = array();
        if(!empty($offer_prices)){
            foreach($offer_prices as $offer_price){
                $buy_ids[] = $offer_price['buy_id'];
            }
        }
        
        if(!empty($buy_ids)){
            $buy_ids = implode(',', $buy_ids);
        }
        
        return $buy_ids;
    }
}