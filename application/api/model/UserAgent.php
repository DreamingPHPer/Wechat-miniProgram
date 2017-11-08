<?php
namespace app\api\model;
use think\Model;

//代理商信息控制模型
class UserAgent extends Model{
    //获取代理商
    public function getAgentInfo($where){
        return $this->where($where)->find();
    }
    
    /**
     * 根据用户省市区获取代理商id
     * @param int $province 用户所在省份
     * @param int $city 用户所在城市
     * @param int $area 用户所在地区
     */
    public function getAgentUids($province,$city,$area){
        $agent_uids = array();
        
        if(!empty($area)){
            $area_agent = $this->where(array('area'=>$area,'status'=>1))->field('uid')->find();
            if(!empty($area_agent)){
                $agent_uids['area'] = $area_agent['uid'];
            }
        }
        
        if(!empty($city)){
            $city_agent = $this->where(array('city'=>$city,'status'=>1))->field('uid')->find();
            if(!empty($city_agent)){
                $agent_uids['city'] = $city_agent['uid'];
            }
        }
        
        if(!empty($province)){
            $province_agent = $this->where(array('province'=>$province,'status'=>1))->field('uid')->find();
            if(!empty($province_agent)){
                $agent_uids['province'] = $province_agent['uid'];
            }
        }
        
        return $agent_uids;
        
    }
}