<?php
namespace app\api\model;
use think\Model;

//省市区控制模型
class Area extends Model{
    /**
     * 根据父类编码获取子类
     * @param unknown $parent_code
     */
    public function getSons($parent_code,$field = 'area_code,area_name') {
        $where = array();
        $where['parent_code'] = $parent_code;
        return $this->where($where)->field($field)->select();
    }
    
    /**
     * 根据编码获取中文名
     * @param unknown $area_code
     */
    public function getNameByAreaCode($area_code){
        $where = array();
        $where['area_code'] = $area_code;
        $area = $this->where($where)->field('area_name')->find();
        return !empty($area) ? $area['area_name'] : '';
    }
}