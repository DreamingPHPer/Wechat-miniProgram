<?php
namespace app\api\model;
use think\Model;

//求购信息图片控制模型
class BuypartsImg extends Model{
    //获取求购信息图片
    public function getImgsByBuyId($buy_id){
        $imgs = $this->where(array('buy_id'=>$buy_id))->select();
        
        if(!empty($imgs)){
            foreach($imgs as &$img){
                $img['imgPath'] = !empty($img['imgPath']) ? getAttachmentUrl($img['imgPath'],true) : '';
            }
        }
        
        return $imgs;
    }
}