<?php
namespace tool;

//微信公众号控制类
class Wechat{
	public $appId;
	public $appSecret;
	public $encodingAesKey = '';
	public $token = '';

	public function __construct($app_id,$app_secret) {
		$this->appId = $app_id;
		$this->appSecret = $app_secret;
	}

	//获取接口token签名
    public function getAccessToken(){
		$flie_path = TEMP_PATH."access_token.json";

		//判断文件是否存在
		if(!file_exists($flie_path)){
			touch($flie_path);
		}
		
		$data = @file_get_contents($flie_path);
		
		$is_call_api = false;//是否调用接口
		
		if(!$data){
			$is_call_api = true;
		}else{
			$data = json_decode($data,true);
			$access_token = $data['access_token'];
			
			if ($data['expire_time'] < time()) {
				$is_call_api = true;
			}
		}
		
		if($is_call_api){
			$access_token = '';
			//如果过期，则再次调用接口获取token值
			$app_id = $this->appId;
			$secret = $this->appSecret;
			$get_access_token_url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$app_id.'&secret='.$secret;
			$result = json_decode($this->httpGet($get_access_token_url),true);
			if(!empty($result['access_token'])){
				$access_token = $result['access_token'];
				
				$data = array();
				$data['expire_time'] = time() + 7000;
				$data['access_token'] = $access_token;
				
				$fp = fopen($flie_path, "w");
				fwrite($fp, json_encode($data));
				fclose($fp);
			}
		}
		
		return $access_token;
	}

	//获取jsapi_ticket
	public function getJsapiTicket(){
	    $flie_path = TEMP_PATH."access_token.json";
	    
	    //判断文件是否存在
	    if(!file_exists($flie_path)){
	        touch($flie_path);
	    }
	    
		$data = @file_get_contents($flie_path);
		
		$is_call_api = false;//是否调用接口
		
		if(!$data){
			$is_call_api = true;
		}else{
			$data = json_decode($data,true);
			$ticket = $data['jsapi_ticket'];
			if ($data['expire_time'] < time()) {
				$is_call_api = true;
			}
		}

		if($is_call_api){
			$ticket = '';
			$access_token = $this->getAccessToken();
			$get_jsapi_ticket_url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token='.$access_token;
			$result = json_decode($this->httpGet($get_jsapi_ticket_url),true);
			if(!empty($result['ticket'])){
				$ticket = $result['ticket'];
				$data = array();
				$data['expire_time'] = time() + 7000;
				$data['jsapi_ticket'] = $ticket;
				$fp = fopen($flie_path, "w");
				fwrite($fp, json_encode($data));
				fclose($fp);
			}
		}
		
		return $ticket;
	}
	
	//获取signature
	public function getSignature(){
		$jsapiTicket = $this->getJsapiTicket();

		// 注意 URL 一定要动态获取，不能 hardcode.
		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
		$url = $protocol.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
		$timestamp = time();
		$nonceStr = $this->createNonceStr();
		
		// 这里参数的顺序要按照 key 值 ASCII 码升序排序
		$string = "jsapi_ticket=".$jsapiTicket."&noncestr=".$nonceStr."&timestamp=".$timestamp."&url=".$url;
		
		$signature = sha1($string);
		
		$signPackage = array(
			"appId"	 => $this->appId,
			"nonceStr"  => $nonceStr,
			"timestamp" => $timestamp,
			"url"	   => $url,
			"signature" => $signature,
			"rawString" => $string
		);
		return $signPackage;
	}
	
	//创建菜单
	public function createMenu($data){
		$access_token = $this->getAccessToken();
		$create_menu_api = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$access_token;
		$result = json_decode($this->httpPost($create_menu_api,$data),true);
		return $result;
	}
	
	//获取二维码Ticket
	public function getQrcodeTicket($data){
	    $access_token = $this->getAccessToken();
	    $create_menu_api = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=".$access_token;
	    $result = json_decode($this->httpPost($create_menu_api,$data),true);
	    return $result;
	}
	
	//生成二维码
	public function createQrcode($ticket){
	    return "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".urlencode($ticket);
	}
	
	//获取unionid
	public function getUnionid($openid){
	    $access_token = $this->getAccessToken();
	    $get_unionid_api = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$access_token."&openid=".$openid."&lang=zh_CN";
	    $result = json_decode($this->httpGet($get_unionid_api),true);
	    return !empty($result['unionid']) ? $result['unionid'] : '';
	}
	
	//模拟get请求
	public function httpGet($url) {
		$curl = curl_init();
		
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 500);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_URL, $url);
	
		$res = curl_exec($curl);
		
		curl_close($curl);
	
		return $res;
	}
	
	//模拟post请求
	public function httpPost($url,$post_data=array()) {
		$curl = curl_init();
		
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_POST, true);
		
		if(is_array($post_data)){
		    $post_data = json_encode($post_data,JSON_UNESCAPED_UNICODE);
		}
		
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 500);
		
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		
		$res = curl_exec($curl);
		curl_close($curl);
		
		return $res;
		
	}
	
	//创建随机字符串
	public function createNonceStr($length = 16) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$str = "";
		for ($i = 0; $i < $length; $i++) {
			$str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
		}
		return $str;
	}
	
	//重定向到openid获取页面(静默授权)
	public function redirectToOpenIdPage($redirect_url){
		$appid = $this->appId;
		$redirect_url = urlencode($redirect_url);
		$get_openid_url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$appid."&redirect_uri=".$redirect_url."&response_type=code&scope=snsapi_base&state=123#wechat_redirect ";
		return $get_openid_url;
	}
	
	//重定向到用户信息获取页面(获取code 链接)
	public function redirectToUserInfoPage($redirect_url){
		$appid = $this->appId;
		$redirect_url = urlencode($redirect_url);
		$get_userinfo_url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$appid."&redirect_uri=".$redirect_url."&response_type=code&scope=snsapi_userinfo&state=12#wechat_redirect";
		return $get_userinfo_url;
	}
	
	//获取用户信息
	public function getUserInfo($login_access_token, $openid){
		$get_user_info_api = "https://api.weixin.qq.com/sns/userinfo?access_token=".$login_access_token."&openid=".$openid."&lang=zh_CN";
		$result = $this->httpGet($get_user_info_api);
		
		$result = json_decode($result,true);
		//若是接口返回错误；则返回false
		if(isset($result['errcode'])){
			return false;
		}else{
			return $result ;
		}
	}


	//获取页面授权token,openid
	public function getLoginAccessToken($code){
		$appid = $this->appId;
		$secret = $this->appSecret;
		$get_token_api = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$appid."&secret=".$secret."&code=".$code."&grant_type=authorization_code";
		$result = $this->httpGet($get_token_api);
		
		$result = json_decode($result,true);
		
		//若是接口返回错误；则返回false
		if(isset($result['errcode'])){
			return false;
		}else{
			return $result ;
		}
	}
	
	//检测页面授权token是否有效
	public function checkToken($login_access_token,$openid){
		$appid = $this->appId;
		$check_token_api = "https://api.weixin.qq.com/sns/auth?access_token=".$login_access_token."&openid=".$openid;
		$result = $this->httpGet($check_token_api);
		return json_decode($result,true);
	}
	
	//刷新页面授权token
	public function refreshToken($refresh_token){
		$appid = $this->appId;
		$refresh_token_api = "https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=".$appid."&grant_type=refresh_token&refresh_token=".$refresh_token;
		$result = $this->httpGet($refresh_token_api);
		return json_decode($result,true);
	}
	
	//获取模板id
	protected function getTemplateId($template_id_short){
		$post_data = array();
		$post_data['template_id_short'] = $template_id_short;
		
		$access_token = $this->getAccessToken();
		$get_template_id_api = "https://api.weixin.qq.com/cgi-bin/template/api_add_template?access_token=".$access_token;
		$result = json_decode($this->httpPost($get_template_id_api,$post_data),true);
		return $result['errmsg'] == 'ok' ? $result['template_id'] : '';
	}
	
	//发送模板消息
	public function postTemplateMessage($openid,$template_id,$data,$url="",$miniprogram=false,$miniprogram_apppid="",$miniprogram_pagepath=""){
		$post_data = array();
		$post_data['touser'] = $openid;
		$post_data['template_id'] = $template_id;
		$post_data['data'] = $data;
		
		if(!empty($url)){
			$post_data['url'] = $url;
		}

		if($miniprogram && !empty($miniprogram_apppid) && empty($miniprogram_pagepath)){
			$post_data['miniprogram'] = array(
				'appid' => $miniprogram_apppid,
				'pagepath' => $miniprogram_pagepath
			);
		}

		$access_token = $this->getAccessToken();
		$post_template_message_api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".$access_token;
		$result = json_decode($this->httpPost($post_template_message_api,$post_data),true);
		return $result;
	}
	
	/**
	 * 发送客服信息
	 * @param unknown $openid
	 * @param string $msgtype
	 * @param array $data
	 * @return mixed
	 */
	public function postCustomerMessage($openid,$data = array(),$msgtype = "text"){
		$post_data = array();
		$post_data['touser'] = $openid;
		$post_data[$msgtype] = $data;

		$access_token = $this->getAccessToken();
		$post_customer_message_api = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=".$access_token;
		$result = json_decode($this->httpPost($post_customer_message_api,$post_data),true);
		return $result;
	}
}