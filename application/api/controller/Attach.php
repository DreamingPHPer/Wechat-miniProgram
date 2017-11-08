<?php
namespace app\api\controller;
use app\common\controller\Attachment;

//上传控制类
class Attach extends Attachment{
    protected $type;//上传类型,1上传文件，2上传图片
    protected $width = 265;
    protected $height = 159;
    
    public function __construct(){
        parent::__construct();
        $this->type = input('post.uploadType/d',1);
        $this->path = input('post.uploadPath','');
    }
    
    public function upload(){
        $rule = array(
            'size'=>'5242880',
            'ext'=>'jpg,jpeg,gif,bmp,png'
        );
        $result = array();
        if($this->type == 1){
            $result = $this->file_upload($this->path,$rule);
        }elseif($this->type == 2){
            $result = $this->img_upload($this->path,$this->width,$this->height,$rule);
            if($result['status']){
                $img = array();
                $img['path'] = $result['file'];
                $img['full_path'] = getAttachmentUrl($result['file'],true);
                $result['file'] = $img;
            }
        }
        if($result['status']){
            return json(array('status'=>true,'info'=>'上传成功','file'=>$result['file'],'extra'=>$result['extra']));
        }else{
            return json(array('status'=>false,'info'=>$result['msg'],'file'=>''));
        }
    }
    
}