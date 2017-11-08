<?php
namespace app\api\model;
use think\Model;

//订单信息控制模型
class Order extends Model{
    //订单状态获取器
    public function getOrderStatusAttr($value = null){
        $status = array(
            0 => '待支付',
            1 => '待发货',
            2 => '待收货',
            3 => '已完成',
            4 => '已取消'
        );
        
        if(is_null($value)){
            return $status;
        }
        
        return isset($status[$value]) ? $status[$value] : null;
    }
    
    //获取订单
    public function getOrders($where, $field = "*", $if_paging = false, $query_params = array(), $page_size = 10){
        if($if_paging){
            $orders = $this->where($where)->field($field)->paginate($page_size,false,$query_params);
        }else{
            $orders = $this->where($where)->field($field)->select();
        }

        if($if_paging && $orders->total() || !empty($orders)){
        
            $buyparts_model = model('Buyparts');
            $buyparts_img_model = model('BuypartsImg');
        
            foreach($orders as &$order){
                //获取求购信息
                $buypart = $buyparts_model->getInfo(array('buy_id'=>$order['buy_id']));
                if($buypart['img_count']){
                    $buypart['images'] = $buyparts_img_model->getImgsByBuyId($order['buy_id']);
                }
                $order['buypart'] = $buypart;
            }
        }
        
        return $orders;
    }
    
    //获取订单详情
    public function getInfo($where, $field = "*"){
        $order = $this->where($where)->field($field)->find();
        
        if(!empty($order)){
            $buyparts_model = model('Buyparts');
            $buyparts_img_model = model('BuypartsImg');
            
            //获取求购信息
            $buypart_field = 'user_id,buy_id,title,img_count,offer_price';
            $buypart = $buyparts_model->getInfo(array('buy_id'=>$order['buy_id']),$buypart_field);
            $images = array();
            if($buypart['img_count']){
                $images = $buyparts_img_model->getImgsByBuyId($order['buy_id']);
            }
            $buypart['images'] = $images;
            $buypart['quantity'] = 1;
            
            $order['buypart'] = $buypart;
            $order['quantity'] = $buypart['quantity'];
            $order['order_total'] = number_format($order['total_amount'] + $order['shipping_fee'],2,'.','');
        }
        
        return $order;
    }
    
    public function getNum($where){
        return $this->where($where)->count('order_id');
    }
}