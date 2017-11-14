<?php
namespace wechat;
use wechat\WXBizMsgCrypt;

//微信公众号控制类
class Api{
	public $appId;
	public $appSecret;
	public $encodingAesKey = '';
	public $token = '';

	public function __construct($app_id,$app_secret,$token = '',$encodingAesKey = '') {
		$this->appId = $app_id;
		$this->appSecret = $app_secret;
		$this->token = $token;
		$this->encodingAesKey = $encodingAesKey;
	}

	//验证消息的确来自微信服务器
	public function checkSignature($signature,$timestamp,$nonce){
	    $token = $this->token;
	
	    //对数组进行排序
	    $tmpArr = array($token, $timestamp, $nonce);
	    sort($tmpArr, SORT_STRING);
	
	    //对三个字段进行sha1运算
	    $tmpString = implode($tmpArr);
	    $tmpString = sha1($tmpString);
	
	    return $tmpString == $signature;
	}
	
	//检测数据类型类型，并响应对于事件
	public function react($openid,$string,$ifEncryType,$msgSignature,$createTime,$nonce){
        //从xml明文中提取数据
	    if(!empty($string)){
	        $xmlString = '';
	        
	        if($ifEncryType){
	            $wxBizMsgCrypt = new WXBizMsgCrypt($this->token,$this->encodingAesKey,$this->appId);
	            $result = $wxBizMsgCrypt->decryptMsg($msgSignature, $createTime, $nonce, $string, $xmlString);
	        }else{
	            $xmlString = $string;
	        }

	        $xmlTree = new \DOMDocument();
	        $xmlTree->loadXML($xmlString);

	        $func = $this->getMsgFromXmlTree($xmlTree,'MsgType');
	        if(method_exists($this, $func)){
	            return $this->$func($xmlTree,$openid);
	        }
	    }

	    return '';
	}
	
	//事件响应
	private function event($xmlTree,$openid = ''){
	    //获取事件类型
	    $eventType = $this->getMsgFromXmlTree($xmlTree,'Event');
	    
	    //实例化用户模型
	    $userModel = model('User');
	    
	    //用户关注公众号
	    if($eventType == 'subscribe'){
	        //添加用户，或更改用户状态
	        $userModel = model('User');
	        $userModel->addUserFromWechat(array('openid'=>$openid));
	        
	        //获取事件KEY值
	        $eventKey = $this->getMsgFromXmlTree($xmlTree,'EventKey');//事件KEY值，qrscene_为前缀，后面为二维码的参数值

	        //扫码关注，获取代理商id
	        if(!empty($eventKey)){
	            $sceneArr = explode('_', $eventKey);
	            $agent_uid = !empty($sceneArr[1]) ? $sceneArr[1] : 0;

	            //更新用户的推荐用户字段
	            $userModel->updateRecommentUid($openid,$agent_uid);
	        }
	        
	        $weiXinMessagetemplateModel = model('WeixinMessageTemplate');
	        $textMessage = $weiXinMessagetemplateModel->getTemplateByNo('10001');
	        $textMessage = !empty($textMessage) ? sprintf($textMessage,4000883993) : '谢谢关注';
	        
	        return $this->text($xmlTree,$openid,$textMessage);
	    }elseif($eventType == 'unsubscribe'){
	        //用户取消关注公众号，将用户信息更新为未关注状态
	        $userModel->updateUsers(array('if_subscribe'=>0),array('openid'=>$openid));
	    }
	}
	
	//文本响应
	private function text($xmlTree, $openid = '',$content = '文本消息'){
        $replyMsgTpl = '<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[text]]></MsgType>
            <Content><![CDATA[%s]]></Content>
            </xml>';
        
        $fromUserName = $this->getMsgFromXmlTree($xmlTree,'ToUserName');//开发者微信号
        $toUserName = $this->getMsgFromXmlTree($xmlTree,'FromUserName');//用户的openid
        $replyMsgXml = sprintf($replyMsgTpl,$toUserName,$fromUserName,time(),$content);

        return $replyMsgXml;
	}
	
	//图片响应
	private function image($xmlTree,$openid = ''){
	    return $this->text($xmlTree,$openid = '','图片消息');
	}
	
	//语音响应
	private function voice($xmlTree,$openid = ''){
	    return $this->text($xmlTree,$openid = '','语音消息');
	}
	
	//视频响应
	private function video($xmlTree,$openid = ''){
	    return $this->text($xmlTree,$openid = '','视频消息');
	}
	
	//小视频响应
	private function shortvideo($xmlTree,$openid = ''){
	    return $this->text($xmlTree,$openid = '','小视频消息');
	}
	
	//地理位置响应
	private function location($xmlTree,$openid = ''){
	    return $this->text($xmlTree,$openid = '','位置消息');
	}
	
	//链接响应
	private function link($xmlTree,$openid = ''){
	    return $this->text($xmlTree,$openid = '','链接消息');
	}
	
	//从xml树中提取字段内容
	private function getMsgFromXmlTree($xmlTree,$tag){
	    $tagArray = $xmlTree->getElementsByTagName($tag);
	    $tagValue = $tagArray->length > 0 ? $tagArray->item(0)->nodeValue : ''; 
	    return $tagValue;
	}

}