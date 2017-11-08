<?php
namespace app\api\model;
use think\Model;

//报价信息图片控制模型
class BuypartsOfferPriceImg extends Model{
    
    //获取求购信息图片
    public function getImgsByBuyId($offer_id){
        $imgs = $this->where(array('offer_id'=>$offer_id))->select();
        
        if(!empty($imgs)){
            foreach($imgs as &$img){
                $img['imgPath'] = !empty($img['imgPath']) ? getAttachmentUrl($img['imgPath'],true) : '';
            }
        }
        
        return $imgs;
    }

}