<?php
namespace tool;

//小程序控制类
class Smallprogram{
    private $appId;
    private $appSecret;

    public function __construct($app_id,$app_secret) {
        $this->appId = $app_id;
        $this->appSecret = $app_secret;
    }
    
    //模拟get请求
    private function httpGet($url) {
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
    
    //模拟get请求
    private function httpPost($url,$post_data=array()) {
        $curl = curl_init();
    
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
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

    //获取session_key
    public function getSessionKey($code){
        $appid = $this->appId;
        $secret = $this->appSecret;
        $get_session_key_api = "https://api.weixin.qq.com/sns/jscode2session?appid=".$appid."&secret=".$secret."&js_code=".$code."&grant_type=authorization_code";
        $result = json_decode($this->httpGet($get_session_key_api),true);
        return $result;
    }
    
    //获取3rd_session_id
    public function get3rdSessionId($uid,$len){
        $fp = @fopen('/dev/urandom','rb');
        $result = '';
        if ($fp !== FALSE) {
            $result .= @fread($fp, $len);
            @fclose($fp);
        }else{
            trigger_error('Can not open /dev/urandom.');
        }
        
        // convert from binary to string
        $result = base64_encode($result);
        
        // remove none url chars
        $result = strtr($result, '+/', '-_');
        return base64_encode($uid).substr($result, 0, $len);
    }
    
    //获取access_token
    public function getAccessToken(){
        $file = TEMP_PATH."small_program_access_token.json";
        if(!file_exists($file)){
            touch($file);
        }
        $data = @file_get_contents($file);
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
                $data['expire_time'] = time() + $result['expires_in'];
                $data['access_token'] = $access_token;
                $fp = fopen(TEMP_PATH."small_program_access_token.json", "w");
                fwrite($fp, json_encode($data));
                fclose($fp);
            }
        }
    
        return $access_token;
    }
    
    /**
     * 发送模板消息
     * @param string $openid 接收者（用户）的 openid
     * @param string $template_id 所需下发的模板消息的id
     * @param string $form_id 表单提交场景下，为 submit 事件带上的 formId；支付场景下，为本次支付的 prepay_id
     * @param string $data 模板内容
     * @param string $page 点击模板卡片后的跳转页面
     * @param string $emphasis_keyword 模板需要放大的关键词
     * @return mixed
     */
    protected function postTemplateMessage($openid,$template_id,$form_id,$data,$page="",$emphasis_keyword=""){
        $post_data = array();
        $post_data['touser'] = $openid;
        $post_data['template_id'] = $template_id;
        $post_data['form_id'] = $form_id;
        $post_data['data'] = $data;
        
        if(!empty($page)){
            $post_data['page'] = $page;
        }
        
        if(!empty($emphasis_keyword)){
            $post_data['emphasis_keyword'] = $emphasis_keyword;
        }
        
        $access_token = $this->getAccessToken();
        $post_template_message_api = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=".$access_token;
        $result = json_decode($this->httpPost($post_template_message_api,$post_data),true);
        return $result;
    }
    
    /**
     * 发送客服消息
     * @param unknown $openid 接收者（用户）的 openid
     * @param string $msgtype 消息类型，text文本消息，image图片消息，link链接消息
     * @param array $data
     */
    public function postCustomerMessage($openid,$msgtype = 'text',$data = array()){
        $post_data = array();
        $post_data['touser'] = $openid;
        $post_data['msgtype'] = $msgtype;
        $post_data[$msgtype] = $data;

        $access_token = $this->getAccessToken();
        $post_customer_message_api = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=".$access_token;
        $result = json_decode($this->httpPost($post_customer_message_api,$post_data),true);
        return $result;
    }
}