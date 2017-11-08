<?php
namespace app\api\controller;
use app\common\controller\BaseApi;

//省市区控制类
class Area extends BaseApi{

    //省市区数据字典
    public function getData(){
        $parent_code = input('post.areaCode/d',0);
        $area_model = model('Area');
        $areas = $area_model->getSons($parent_code);
        return jsonReturn(SUCCESSED, '获取成功',$areas);
    }
    
    
}