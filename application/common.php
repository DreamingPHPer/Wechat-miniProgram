<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件

/*
 * 得到附件的网址
 * is_remote,是否是远程，否时直接用本地图片
 * $need_site_url 是否需要站点
 * */
function getAttachmentUrl($fileUrl,$need_site_url=false){
    if(empty($fileUrl)){
        return '';
    }else{
        //如果已经是完整url地址，则不做处理
        if(strstr($fileUrl, 'http://') !== false || strstr($fileUrl, 'https://') !== false) {
            return $fileUrl;
        }

        //是否需要加上当前域名
        if($need_site_url){
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $url = $protocol.$_SERVER['SERVER_NAME'];
            $img_url = $url.'/uploads/' . $fileUrl;
        }else{
            $img_url = '/uploads/' . $fileUrl;
        }

        return $img_url;
    }
}

/*
 * 获取图片小图
 * @param $img_name 原图名称
 * */
function getSmallImg($img){
    if(!empty($img)){
        $img_arr = explode('.',$img);
        return count($img_arr) == 2 ? $img_arr[0].'_small.'.$img_arr[1] : '';
    }else{
        return '';
    }
}

/**
 * 文件上传
 * @param string $catalog
 * @param string $autoSub
 * @param string $is_thumb
 * @param string $width
 * @param string $height
 * @param number $maxSize
 * @param array $exts
 * @return boolean[]|string[]
 */
function uploadAttachment($filename,$catalog='',$is_thumb=true,$width='100',$height='100',$maxSize=10485760,$exts='jpg,png,gif,jpeg'){
    $file = request()->file($filename);
    $info = $file->validate(['size'=>$maxSize,'ext'=>$exts])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $catalog);

    if($info){
        return array('status'=>true,'info'=>'上传成功','data'=>$catalog.'/'.$info->getSaveName(),'extra'=>array('real_name'=>$realName));
    }else{
        return array('status'=>false,'info'=>$info->getError());
    }   
}

/*
 * param url 抓取的URL
 * param data post的数组
 */
function curlPost($url,$data = array()){
    //初始化curl
    $curl = curl_init();
    //设置参数
    curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_HEADER, 0); 
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($curl, CURLOPT_ENCODING, "" ); 
	curl_setopt($curl, CURLOPT_POST, TRUE);
	curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    //执行curl
    $result = curl_exec($curl);
    //关闭curl
    curl_close($curl);
    return $result;
}

/**
 * 微信终端检测
 */
function isWechat(){
    if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
        return true;
    }
    
    return false;
}

/**
 * JSON返回
 * @param bool $ret 状态码
 * @param string $msg 返回说明
 * @param array $data 返回数据
 */
function jsonReturn($ret,$msg,$data = array()){
    $return_data = array();
    
    $return_data['ret'] = $ret;
	$return_data['msg'] = $msg;
	
	if($ret === 0){
	    $return_data['data'] = $data;
	}

	return json($return_data);
}

/**
 * 发送短信
 * $phone 接收人的手机号
 * $module_id 短息模板编号
 * $data 传递的参数
 */
function sendSms($phone,$module_id,$data){
    $post_data['appkey']   = "ChaiChe";
    $post_data['moduleID'] = $module_id;
    $post_data['params']   = $data;
    $post_data['phone']    = $phone;
    
    $url="http://open.chaichew.com/service/sms/sendsms_neccw";
    //$result = curlPost($url,$post_data);
    
    $result = _curl($url,$post_data);
    
    if($result){
        $result = json_decode($result,true);
        if($result['code'] == '200'){
            return array('status'=>true,'info'=>'发送成功');
        }else{
            return array('status'=>false,'info'=>'发送失败');
        }
    }
    
    return array('status'=>false,'info'=>'发送失败');
}

/*
 * 1秒钟之后都会关闭该访问
 * */
function _curl($url,$data) {
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch,CURLOPT_TIMEOUT,1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

/**
 * 验证手机号码
 */
function checkMobile($mobile){
    if (!is_numeric($mobile)) {
        return false;
    }
    
    $pattern = '#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$#';
    
    return preg_match($pattern, $mobile) ? true : false;
}

/**
 * 格式化手机号码
 */
function formatMobile($mobile){
    return substr($mobile, 0, strlen($mobile)-4).'****';
}

/**
 * 获取请求头信息
 */
function getRequestHeaderParam($key){
    if(function_exists('apache_request_headers')){
        $params = apache_request_headers();
        return isset($params[$key]) ? $params[$key] : '';
    }
    
    return '';
}

/**
 * 发送客服消息
 * @param unknown $uid
 * @param unknown $template_no
 * @param unknown $data
 * @param string $msg_type 消息类型，text：文本消息，miniprogrampage：发送小程序卡片
 * @param string $pagepath 小程序页面路径
 * @return boolean
 */
function postCustomerMessage($uid, $template_no, $data, $msg_type = 'text',$pagepath = ''){
    if(empty($uid) || empty($template_no) || empty($data)){
        return jsonReturn(WRONG_PARAM, '参数传递错误');
    }
    
    $user_model = model('api/User');
    $user = $user_model->getUserInfoByUid($uid);
    
    if(empty($user) || $user['status'] == 1){
        return jsonReturn(NO_AUTHORITY, '用户不存在或未激活');
    }
    
    if(!$user['if_subscribe']){
        return jsonReturn(NO_AUTHORITY, '当前用户未关注公众号');
    }
    
    $weixin_message_template_model = model('api/WeixnMessageTemplate');
    $template = $weixin_message_template_model->getTemplateByNo($template_no);
    if(empty($template)){
        return jsonReturn(WRONG_PARAM, '当前模板不存在');
    }
    
    $data = json_decode($data,true);
    if(empty($data)){
        return jsonReturn(WRONG_PARAM, '模板参数必须为数组');
    }
    
    $wechat = new \tool\Wechat(config('config.APPID'), config('config.APPSECRET'));
    $user_model = model('api/User');
    $user = $user_model->getUserInfoByUid($uid,'openid');

    array_unshift($data,$template);
    $content = call_user_func_array('sprintf',$data);
    
    $post_data = array();
    
    if($msg_type == 'text'){
        $post_data['content'] = $content;
    }elseif($msg_type == 'miniprogrampage'){
        $post_data['title'] = $content;
        $post_data['appid'] = config('config.MIN_PROGRAM_APPID');
        $post_data['pagepath'] = $pagepath;
        $post_data['thumb_media_id'] = '';
    }
    
    $result = $wechat->postCustomerMessage($user['openid'],$post_data,$msg_type);
    return $result['errmsg'] == 'ok' ? true : false;
}
    
/**
 * 发送模板消息
 */
function postTemplateMessage($uid,$template_id,$data,$url="",$miniprogram=false,$miniprogram_apppid="",$miniprogram_pagepath=""){
    $wechat = new \tool\Wechat(config('config.APPID'), config('config.APPSECRET'));
    $user_model = model('api/User');
    $user = $user_model->getUserInfoByUid($uid,'openid');
    $result = $wechat->postTemplateMessage($user['openid'],$template_id,$data,$miniprogram,$miniprogram_apppid,$miniprogram_pagepath);
    return $result['errmsg'] == 'ok' ? true : false;
}