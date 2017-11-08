<?php
namespace app\api\controller;
use app\common\controller\BaseApi;
use tool\Task;

//订单信息控制类
class Order extends BaseApi{
    public $user_info = null;
    
    public function __construct() {
        parent::__construct();
        $this->user_info = $this->checkLoginStatus();
    }
    
    //订单列表
    public function lists(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $order_type = input('post.type/d','1');//默认买家
        $order_status = input('post.status','all');
        $need_page = input('post.needPage/d',0);
        $page = input('post.page/d',1);
        
        //获取订单
        $order_model = model('Order');
        $where = array();
        
        $where['user_id'] = $this->user_info['uid'];
        if($order_type == 2){//卖家
            $where['offer_user_id'] = $this->user_info['uid'];
        }
        
        $status = null;
        switch ($order_status){
            case 'unpay':
                $status = 0;
                break;
            case 'undelivery':
                $status = 1;
                break;
            case 'unreceive':
                $status = 2;
                break;
        }
        
        if(!is_null($status)){
            $where['order_status'] = $status;
        }
 
        $need_page = $need_page ? true : false;
        $field = 'order_id,order_sn,user_id,order_status,total_amount';
        $orders = $order_model->getOrders($where,$field,$need_page,array('page'=>$page));
        
        return jsonReturn(SUCCESSED, '获取成功', $orders);
    }
    
    //订单详情
    public function detail(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $order_id = input('post.id/d',0);
        
        if(empty($order_id)){
            return jsonReturn(WRONG_PARAM, '订单ID不能为空');
        }
        
        $order_model = model('Order');
        
        $where = array();
        $where[] = 'order_id = '.$order_id;
        $where[] = 'user_id = '.$this->user_info['uid'].' or offer_user_id = '.$this->user_info['uid'];
        $where = implode(' and ', $where);
        
        $field = 'order_id,order_sn,user_id,order_status,consignee,province,city,area,address,mobile,total_amount,add_time,confirm_time,pay_time,shipping_time,buy_id';
        $order = $order_model->getInfo($where,$field);
        
        if(empty($order)){
            return jsonReturn(NO_AUTHORITY, '当前用户无权查看当前订单');
        }
        
        return jsonReturn(SUCCESSED, '获取成功', $order);
    }
    
    //更新订单状态
    public function updateOrderStatus(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $order_id = input('post.id/d',0);
        $order_type = input('post.type','');
        
        if(empty($order_id) || empty($order_type)){
            return jsonReturn(WRONG_PARAM, '参数id或type不能为空');
        }
        
        $data = array();//更新数据
        switch($order_type){
            case 'pay':
                $data['status'] = 1;
                $data['pay_time'] = time();
                break;
            case 'delivery':
                $data['status'] = 2;
                $data['shipping_time'] = time();
                break;
            case 'receive':
                $data['status'] = 3;
                $data['confirm_time'] = time();
                break;  
            default:
                $data['status'] = null;
        }
        
        if(is_null($data['status'])){
            return jsonReturn(WRONG_PARAM, '参数type传递错误');
        }
        
        $order_model = model('Order');
        $order = $order_model->getInfo(array('order_id'=>$order_id));
        
        if(empty($order)){
            return jsonReturn(NO_RESULT, '订单信息不存在');
        }
        
        if($order['user_id'] != $this->user_info['uid'] && $order['offer_user_id'] != $this->user_info['uid']){
            return jsonReturn(NO_AUTHORITY, '当前用户无权查看当前订单');
        }else if($order['user_id'] == $this->user_info['uid'] && !in_array($data['status'], array(1,3))){
            return jsonReturn(NO_AUTHORITY, '当前用户无权操作当前订单');
        }else if($order['offer_user_id'] == $this->user_info['uid'] && $data['status'] != 2){
            return jsonReturn(NO_AUTHORITY, '当前用户无权操作当前订单');
        }
        
        $result = $order_model->update($data,array('order_id'=>$order_id));
        
        if($result !== false){
            //已支付
            if($data['status'] == 1){
                //添加系统消息
                $content = sprintf('【下单通知】求购方已付款（%s），请尽快完成发货。',$order['buypart']['title']);
                $user_message_model = model('UserMessage');
                $user_message_model->addMessage(array('uid'=>$order['offer_user_id'],'title'=>'','content'=>$content));
                
                //添加平台消息
                $content = sprintf('【下单通知】有新的订单完成付款（%s），请持续关注！',$order['buypart']['title']);
                $sysconf_message_model = model('SysconfMessage');
                $sysconf_message_model->addMessage(array('uid'=>$order['offer_user_id'],'title'=>'','content'=>$content));
                
                //添加短信发送任务
                $user_model = model('User');
                $seller = $user_model->getUserInfoByUid($order['offer_user_id'],'phone');
                if(!empty($seller['phone'])){
                    $task_data = array(
                        'type' => 'sendSms',
                        'data' => array(
                            'phone'=>$seller['phone'],
                            'module_id'=> '',
                            'data' => array()
                        ),
                        'available_at' => time()
                    );
                
                    $task = new Task();
                    $task->addTask($task_data);
                }
                
                //发送客服消息
                postCustomerMessage($order['offer_user_id'], 10011, array($order['buypart']['title'],4000883993));
                
                //已发货
            }elseif($data['status'] == 2){
                //添加系统消息
                $content = sprintf('【发货通知】您求购的商品已发货（%s），请注意查收。',$order['buypart']['title']);
                $user_message_model = model('UserMessage');
                $user_message_model->addMessage(array('uid'=>$order['user_id'],'title'=>'','content'=>$content));
                
                //将确认收货提醒加入任务表，进行监听
                $task_datas = array();
                $task_datas[] = array(
                    'type' => 'receiveReminder',
                    'data' => array(
                        'order_id'=>$order_id
                    ),
                    'available_at' => $data['shipping_time'] + 5*24*3600
                );
                
                $user_model = model('User');
                $buyer = $user_model->getUserInfoByUid($order['user_id'],'phone');
                if(!empty($buyer['phone'])){
                    $task_datas[] = array(
                        'type' => 'sendSms',
                        'data' => array(
                            'phone'=>$buyer['phone'],
                            'module_id'=> '',
                            'data' => array()
                        ),
                        'available_at' => time()
                    );
                }

                $task = new Task();
                $task->addTask($task_datas, 1, true);
                
                //发送客服消息
                postCustomerMessage($order['user_id'], 10012, array($order['buypart']['title'],4000883993));

                //确认收货
            }elseif($data['status'] == 3){
                //分配佣金
                $result = $this->commissionDistribute($order);
                
                
                
            }
            
            return jsonReturn(SUCCESSED, '操作成功');
        }

        return jsonReturn(BAD_SERVER, '操作失败');
    }
    
    //生成订单
    public function add(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        //检测当前用户的默认地址是否存在
        $user_address_model = model('UserAddress');
        $default_address = $user_address_model->getInfo(array('uid'=>$this->user_info['uid'],'status'=>1,'is_default'=>1));
        if(empty($default_address)){
            return jsonReturn(NO_AUTHORITY, '请先设置您的默认地址');
        }
        
        $offer_id = input('post.id/d',0);
        $pay_type = input('post.payType/d',0);
        
        if(empty($offer_id)){
            return jsonReturn(WRONG_PARAM, '参数id不能为空');
        }
        
        if($pay_type != 1){
            return jsonReturn(WRONG_PARAM, '目前系统只支持微信支付');
        }
        
        $buyparts_offer_price_model = model('BuypartsOfferPrice');

        $buypart = $buyparts_offer_price_model->getBuypartByOfferId($offer_id);
        if(empty($buypart) || $buypart['is_delete'] || $buypart['is_dongjie']){
            return jsonReturn(NO_RESULT, '信息不存在');
        }
        
        if($buypart['buyer_id'] != $this->user_info['uid']){
            return jsonReturn(NO_AUTHORITY, '当前用户没有权限操作当前信息');
        }
        
        if($buypart['buypart_status'] != 1){
            return jsonReturn(NO_AUTHORITY, '当前信息不再求购中，不能进行此操作');
        }
        
        $data = array();
        $data['order_sn'] = $this->getOrderSn();
        $data['user_id'] = $this->user_info['uid'];
        $data['consignee'] = $default_address['name'];
        $data['mobile'] = $default_address['tel'];
        $data['province'] = $default_address['province'];
        $data['city'] = $default_address['city'];
        $data['area'] = $default_address['area'];
        $data['address'] = $default_address['address'];
        $data['zipcode'] = $default_address['zipcode'];
        $data['email'] = $default_address['email'];
        $data['pay_type'] = $pay_type;
        $data['total_amount'] = $buypart['offer_price'];
        $data['add_time'] = time();
        $data['buy_id'] = $buypart['buy_id'];
        $data['offer_id'] = $offer_id;
        $data['offer_user_id'] = $buypart['seller_id'];
        
        $order_model =model('Order');
        if($order_model->save($data)){
            $order_id = $order_model->order_id;
            
            //生成订单，更改求购信息状态
            $buypart_model = model('Buyparts');
            $buypart_model->save(array('status'=>2),array('buy_id'=>$buypart['buy_id']));
            
            //将订单加入任务表，进行监听是否支付
            $task_datas = array();
            $task_datas[] = array(
                'type' => 'payReminder',
                'data' => array(
                    'order_id'=>$order_id
                ),
                'available_at' => $data['add_time'] + 2*3600
            );
            
            //添加短信发送任务
            $user_model = model('User');
            $seller = $user_model->getUserInfoByUid($buypart['seller_id'],'phone');
            if(!empty($seller['phone'])){
                $task_datas[] = array(
                    'type' => 'sendSms',
                    'data' => array(
                        'phone'=>$seller['phone'],
                        'module_id'=> '',
                        'data' => array()
                    ),
                    'available_at' => time()
                );
            }
            
            $task = new Task();
            $task->addTask($task_datas, 1, true);
   
            //添加系统消息
            $content = sprintf('【报价通知】您的报价被对方接受啦（%s），价格为%s元，请持续关注交易动态！',$buypart['title'], $buypart['offer_price']);
            $user_message_model = model('UserMessage');
            $user_message_model->addMessage(array('uid'=>$buypart['seller_id'],'title'=>'','content'=>$content));
            
            //添加平台消息
            $content = sprintf('【报价通知】报价被接受了（%s），请持续关注！',$buypart['title']);
            $sysconf_message_model = model('SysconfMessage');
            $sysconf_message_model->addMessage(array('uid'=>$buypart['seller_id'],'title'=>'','content'=>$content));

            //发送客服消息给卖方
            postCustomerMessage($buypart['seller_id'], 10007, array($buypart['title'],$buypart['offer_price'],4000883993));
            
            return jsonReturn(SUCCESSED, '操作成功', $order_model->order_id);
        }

        return jsonReturn(BAD_SERVER, '操作失败');
        
    }
    
    /**
     * 生成订单号
     */
    private function getOrderSn(){
        $order_sn = date('YmdHis',$_SERVER['REQUEST_TIME']).mt_rand(10000,99999);
        return $order_sn;
    }
    
    /**
     * 分配佣金
     * @param unknown $order 订单信息
     */
    private function commissionDistribute($order){
        $config_model = model('Config');
        $commission = $config_model->getValueByName('commission');
        $commission = !empty($commission) ? $commission : 0;
        $commission = $order['total_amount'] * $commission;
        
        //统计总佣金
        if(empty($commission)){
            return false;
        }

        //查询佣金分配比例
        $setting_model = model('Setting');
        $setting = $setting_model->find();
        if(empty($setting)){
            return false;
        }
        
        //根据买家信息查询各级代理商
        $user_model = model('User');
        $agents = $user_model->getAllAgentsByUid($order['user_id']);
        
        if(empty($agents)){
            return false;
        }
        
        //分配佣金
        $account_model = model('UserAccount');
        if(!empty($agents['area']) && !empty($setting['area_rate'])){
            $province_commission = $commission * $setting['area_rate'] / 100;
            $account_model->setIncBalance(array('uid'=>$agents['area']));
        }
        
        if(!empty($agents['city']) && !empty($setting['city_rate'])){
            $province_commission = $commission * $setting['city_rate'] / 100;
            $account_model->setIncBalance(array('uid'=>$agents['city']));
        }
        
        if(!empty($agents['province']) && !empty($setting['province_rate'])){
            $province_commission = $commission * $setting['province_rate'] / 100;
            $account_model->setIncBalance(array('uid'=>$agents['province']));
        }
    }
    
}