<?php
namespace app\api\controller;
use app\common\controller\BaseApi;
use tool\Wechat;

//支付控制类
class Pay extends BaseApi{
    public $user_info = null;
    public $wechat = null;
    public $appid = '';
    public $appsecret = '';
    public $mch_id = '';
    
    public function __construct() {
        parent::__construct();
        $this->appid = config('config.APPID');
        $this->appsecret = config('config.APPSECRET');
        $this->wechat = new Wechat($this->appid, $this->appsecret);
        $this->mch_id = config('config.MCHID');
        $this->user_info = $this->checkLoginStatus();
    }
    
    //支付接口
    public function index(){
        $type = input('post.type/d',1);
        $order_id = input('post.orderId/d',0);
        $openid = input('post.oepnid','');
        
        if(empty($order_id) || ($type == 1 && empty($oepnid)) || !in_array($type, array(1,2))){
            return jsonReturn(WRONG_PARAM, '传递的参数错误');    
        }
        
        //小程序支付
        if($type == 2){
            if(empty($this->user_info['openid'])){
                return jsonReturn(WRONG_PARAM, '当前用户还未关注过公众号');
            }
            $openid = $this->user_info['openid'];
        }
        
        $order_model = model('Order');
        $order_where = array();
        $order_where['order_id'] = $order_id;
        $order_where['order_status'] = 0;
        $order = $order_model->getInfo($order_where,'order_id,order_sn,buy_id,total_amount,shipping_fee');
        if(empty($order)){
            return jsonReturn(WRONG_PARAM, '当前订单不可支付');
        }
        
        $data = array();
        $data['appid'] = $this->appid;
        $data['mch_id'] = $this->mch_id;
        $data['nonce_str'] = $this->wechat->createNonceStr(32);
        $data['body'] = $order['buypart']['title'];
        $data['out_trade_no'] = $order['order_sn'];
        $data['total_fee'] = $order['order_total'] * 100;//换算成分
        $data['spbill_create_ip'] = get_client_ip(0,true);
        $data['notify_url'] = url('api/pay/response','',true,true);
        
        if($type == 2){
            $data['trade_type'] = 'JSAPI';
        }else{
            $data['trade_type'] = 'NATIVE';
        }
        
        $data['oepnid'] = $openid;
        $data['sign'] = $this->getPaySign($data);
        
        //调用统一下单接口获取package
        $get_package_api = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        $result_xml = $this->wechat->httpPost($get_package_api,$this->generateXml($data));
        $result = $this->extractXml($result_xml);

        if($result === false){
            return jsonReturn(BAD_SERVER, 'xml数据解析错误');
        }
        
        if($result['return_code'] == 'FAIL'){
            return jsonReturn(BAD_SERVER, $result['return_msg']);
        }
        
        if($result['result_code'] == 'FAIL'){
            return jsonReturn(BAD_SERVER, $result['err_code_des']);
        }
        $package = 'prepay_id='.$result['prepay_id'];

        //小程序支付
        if($type == 2){
            $payment_data = array();
            $payment_data['appId'] = $this->appid;
            $payment_data['timeStamp'] = time();
            $payment_data['nonceStr'] = $this->wechat->createNonceStr();
            $payment_data['package'] = $package;
            $payment_data['signType'] = 'md5';
            $payment_data['paySign'] = $this->getPaySign($payment_data);
            return jsonReturn(SUCCESSED, '获取成功', $payment_data);
        }
        
        return jsonReturn(SUCCESSED, '获取成功', $result['code_url']);
    }
    
    //支付成功回调
    public function response(){
        //接受响应数据
        $response_xml = $_REQUEST;
        if($response_xml == null){
            $response_xml = file_get_contents("php://input");
        }
        if($response_xml == null){
            $response_xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        }
        
        //解析mxl
        $result = $this->extractXml($response_xml);
        
        $return_data = array();
        
        if($result === false){
            $return_data['return_code'] = 'FAIL';
            $return_data['return_msg'] = 'xml解析错误';
            return $this->generateXml($return_data);
        }

        if($result['return_code'] == 'FAIL'){
            $return_data['return_code'] = 'FAIL';
            $return_data['return_msg'] = $result['return_msg'];
            return $this->generateXml($return_data);
        }

        if($result['return_code'] == 'SUCCESS'){
            if($result['result_code'] == 'FAIL'){
                $return_data['return_code'] = 'FAIL';
                $return_data['return_msg'] = $result['err_code_des'];
                return $this->generateXml($return_data);
            }
            
            $data = array();
            
            foreach($result as $key => $value){
                if(!empty($value) && $key != 'sign'){
                    $data[$key] = $value;
                }
            }
            
            if($result['sign'] !== $this->getPaySign($data)){
                $return_data['return_code'] = 'FAIL';
                $return_data['return_msg'] = '数字签名错误';
            }else{
		//TODO::支付成功后的操作
                $return_data['return_code'] = 'SUCCESS';
                $return_data['return_msg'] = '支付成功';
            }
            
            return $this->generateXml($return_data);
        }
    }

    //生成paySign
    private function getPaySign($params){
        ksort($params);
        
        $string_arr = array();
        foreach($params as $key => $param){
            $string_arr[] = $key . '=' . $param;
        }
        $string = implode('&', $string_arr);
        $string .= config('config.PAYMENT_KEY');
        $string_md5 = md5($string);
        $string_upper = strtoupper($string_md5);
        
        return $string_upper;
    }

    //生成xml字符串
    private function generateXml($data){
        $xml_tpl = '<xml>';
        
        if(!empty($data)){
            foreach($data as $key => $value){
                $xml_tpl .= '<'.$key.'>'.$value.'</'.$key.'>';
            }
        }
        
        $xml_tpl .= '</xml>';

        return $xml_tpl;
    }
    
    //解析xml
    private function extractXml($xml_string){
        libxml_disable_entity_loader(true);
        $xml_obj = simplexml_load_string($xml_string, 'SimpleXMLElement', LIBXML_NOCDATA);
        
        if(!$xml_obj){
            return false;
        }
        
        $result = get_object_vars($xml_obj);

        return $result;
    }
    
    //从xml树中提取字段内容
    private function getMsgFromXmlTree($xml_tree,$tag){
        $tag_array = $xml_tree->getElementsByTagName($tag);
        $tag_value = $tag_array->length > 0 ? $tag_array->item(0)->nodeValue : '';
        return $tag_value;
    }
}