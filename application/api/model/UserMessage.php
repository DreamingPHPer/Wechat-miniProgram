<?php
namespace app\api\model;
use think\Model;

//消息控制模型
class UserMessage extends Model{
    /**
     * 已读、未读获取器
     * @param unknown $value
     */
    public function getIsReadAttr($value = null){
        $is_read = array(
            0 => '未读',
            1 => '已读'
        );
        
        if(is_null($value)){
            return $is_read;
        }
        
        return isset($is_read[$value]) ? $is_read[$value] : '';
    }
    
    /**
     * 获取消息详情
     * @param unknown $where
     * @param string $field
     */
    public function getInfo($where, $field = "*"){
        return $this->where($where)->field($field)->find();
    }
    
    /**
     * 获取消息
     * @param unknown $where
     * @param string $field
     * @param string $if_paging
     * @param array $query_params
     * @param number $page_size
     */
    public function getMessages($where, $field = "*", $if_paging = false, $query_params = array(), $page_size = 10){
        if($if_paging){
            return $this->where($where)->field($field)->paginate($page_size,false,$query_params);
        }
        
        return $this->where($where)->field($field)->select();
    }
    
    /**
     * 添加系统消息
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