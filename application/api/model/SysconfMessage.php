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
    public function addMessage($datas, $if_multiterm = false){
        $new_datas = array();
        
        if($if_multiterm){
            if(!empty($datas)){
                foreach ($datas as $data){
                    $new_datas[] = array(
                        'uid' => $data['uid'],
                        'title' => $data['title'],
                        'content' => $data['content'],
                        'create_time' => time(),
                    );
                }
            } 
        }else{
            $new_datas[] = array(
                'uid' => $datas['uid'],
                'title' => $datas['title'],
                'content' => $datas['content'],
                'create_time' => time(),
            );
        }

        return $this->saveAll($new_datas);
    }
    
    
}