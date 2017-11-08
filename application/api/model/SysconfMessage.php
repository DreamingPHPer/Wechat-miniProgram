<?php
namespace app\api\model;
use think\Model;

//平台消息控制模型
class SysconfMessage extends Model{
    /**
     * 添加消息
     * @param unknown $uid
     * @param unknown $title
     * @param unknown $content
     */
    public function addMessage($uid,$title,$content){
        $data = array();
        $data['uid'] = $uid;
        $data['title'] = $title;
        $data['content'] = $content;
        $data['create_time'] = time();
        return $this->save($data);
    }
    
    
}