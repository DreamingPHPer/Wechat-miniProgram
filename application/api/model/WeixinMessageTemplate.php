<?php
namespace app\api\model;
use think\Model;

//微信消息控制模型
class WeixinMessageTemplate extends Model{
    /**
     * 根据模板编号获取模板
     * @param unknown $template_no 模板编号
     */
    public function getTemplateByNo($template_no){
        $template = $this->where(array('template_no'=>$template_no))->field("content")->find();
        return !empty($template) ? $template['content'] : '';
    }
}