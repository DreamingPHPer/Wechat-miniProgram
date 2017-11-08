<?php
namespace app\common\controller;
use think\Controller;

//文件上传控制类
class Attachment extends Controller{
	
	/**
	 * 上传图片
	 * @param string $rand_num 图片分类名称
	 * @param number $width 截图宽
	 * @param number $height 截图高
	 */
	public function img_upload($category='',$width=265,$height=265,$rule=array()) {
		if (!empty($_FILES['file']) && $_FILES['file']['error'] != 4) {
			//用户ID 
			$uid = session('user_session.uid');
			if(empty($uid)){
				$uid = '00';
			}
			// 产生目录结构
			if(empty($category)){
			    $rand_num = 'images/' . date('Ym', $_SERVER['REQUEST_TIME']) . '/'. date('d', $_SERVER['REQUEST_TIME']) . '/'. date('H', $_SERVER['REQUEST_TIME']) . '/'. $uid . '/';;
			}else{
			    $rand_num = 'images/' .strtolower($category).'/'. date('Ym', $_SERVER['REQUEST_TIME']) . '/'. date('d', $_SERVER['REQUEST_TIME']) . '/'. date('H', $_SERVER['REQUEST_TIME']) .  '/'. $uid . '/';;
			}
			
			// 获取表单上传文件
			$file = request()->file('file');
			// 移动到框架应用根目录/public/uploads/ 目录下
			$u = ROOT_PATH . 'public/uploads/'.$rand_num;
			$info = $file->validate($rule)->move($u);
			
			if($info){
				//获取文件名称
				$img_name = $info->getSaveName();
				
				//上传的文件
				$u_img = $u.$img_name;
				
				// 生成$width*$height缩略图
				$small_name = getSmallImg($img_name);
				if($small_name){
					$small_name = $u.$small_name;
					$image = \think\Image::open($u_img);
					// 按照原图的比例生成一个最大为150*150的缩略图并保存为thumb.png
					$image->thumb($width, $height,\think\Image::THUMB_CENTER)->save($small_name);
				}
				
				return array('status'=>true,'msg'=>'上传成功','file' =>$rand_num.$img_name,'extra'=>array('realname'=>$_FILES['file']['name']));
			}else{
				// 上传失败获取错误信息
				//echo $file->getError();
				return array('status' => false, 'msg' => $file->getError());
				exit;
			}
		}else{
			return array('status' => false, 'msg' => '请上传相应的图片');
			exit;
		}
	}
	
	/**
	 * 文件上传
	 * @param string $category 文件存储路径
	 * @param array $rule 文件验证规则
	 */
	public function file_upload($category ='',$rule = array()) {
		if (!empty($_FILES['file']) && $_FILES['file']['error'] != 4) {
			//用户ID
			$uid = session('user_session.uid');
			if(empty($uid)){
				$uid = '00';
			}
			// 产生目录结构
			if(empty($category)){
				$rand_num = 'file/' . date('Ym', $_SERVER['REQUEST_TIME']) . '/'. date('d', $_SERVER['REQUEST_TIME']) . '/'. date('H', $_SERVER['REQUEST_TIME']) . '/'. $uid . '/';;
			}else{
				$rand_num = 'file/' .strtolower($category).'/'. date('Ym', $_SERVER['REQUEST_TIME']) . '/'. date('d', $_SERVER['REQUEST_TIME']) . '/'. date('H', $_SERVER['REQUEST_TIME']) .  '/'. $uid . '/';;
			}
			
			// 获取表单上传文件
			$file = request()->file('file');
			
			// 移动到框架应用根目录/public/uploads/ 目录下
			$u = ROOT_PATH . 'public/uploads/'.$rand_num;
				
			$info = $file->validate($rule)->move($u);
			if($info){
				//获取文件名称
				$file_name = $info->getSaveName();
				//上传的文件
				$u_img = $u.$file_name;
	
				return array('status'=>true,'msg'=>'上传成功','file' =>$rand_num.$file_name,'extra'=>array('realname'=>$_FILES['file']['name']));
			}else{
				// 上传失败获取错误信息
				//echo $file->getError();
				return array('status' => false, 'msg' => $file->getError());
				exit;
			}
		}else{
			return array('status' => false, 'msg' => '请上传相应的文件');
			exit;
		}
	}
}