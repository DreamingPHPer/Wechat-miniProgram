<?php
namespace app\api\model;
use think\Model;

//系统设置控制模型
class Config extends Model{
    
    //根据字段名称获取字段值
    public function getValueByName($name){
        $config = $this->where(array('name'=>$name))->field('value')->find();  
        return !empty($config) ? $config['value'] : '';
    }
}