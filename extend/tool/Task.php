<?php
namespace tool;

/**
 *短信发送
 */
class Task{
    protected $task_model = null;
    protected $attempt = 3;//任务最多尝试执行次数
    
    public function __construct(){
        $this->task_model = model('common/Task');   
    }
    
    /**
     * 开始执行任务
     * @param $type 任务类型
     * @return bool
     */
    public function start($task_type = null){
        //执行任务
        while (true){
            //获取任务
            $data = $this->getTask($task_type);
            if(!empty($data)) {
                $result = $this->dealTask($data);
                if($result == 1){
                    $this->changeTaskStatus($data,1);
                }elseif($result == 2){
                    $this->changeTaskStatus($data,2);
                    if($this->attempt > $data['attempt']){
                        $this->delayTask($data);
                    }else{
                        $this->stopTask($data);
                    }     
                }  
            }else{
                sleep(15);
                continue;
            }
        }
    }
    
    /*
     * 添加任务
     * pram data 
     * from 来源
     * param $if_multiterm 数据是否多条
     * */
    public function addTask($datas, $from = 1, $if_multiterm = false){
        if(empty($datas)){
            return false;
        }
        
        $task_model = $this->task_model;

        $new_data = array();
        if($if_multiterm){
            foreach($datas as $data){
                $new_data[] = array(
                    'type' => $data['type'],
                    'data' => json_encode($data['data'], JSON_UNESCAPED_UNICODE),
                    'add_time'=>time(),
                    'available_at'=>$data['available_at'],
                    'from' => $from
                );
            }
        }else{
            $new_data[] = array(
                'type' => $datas['type'],
                'data' => json_encode($datas['data'], JSON_UNESCAPED_UNICODE),
                'add_time'=>time(),
                'available_at'=>$datas['available_at'],
                'from' => $from
            );
        }
        
        return $task_model->saveAll($new_data);
    }
    
    
    //获取任务
    private function getTask($type = null){
        $task_model = $this->task_model;
        
        $where = array();
        
        if(!is_null($type)){
            $where[] = 'type = '.$type;
        }
        
        $where[] = 'status <> 1';
        $where[] = '(available_at > 0 and available_at <= '.time().')';
        $where = implode(' and ',$where);
        
        return $task_model->where($where)->order('available_at asc')->find();
    }
    
    //更新任务状态
    private function changeTaskStatus($data,$status){
        $task_id = $data['task_id'];
        $task_model = $this->task_model; 
        $result = $task_model->update(array('status'=>$status),array('task_id'=>$task_id));
        return $result;
    }
    
    //终止执行任务
    private function stopTask($data){
        $task_id = $data['task_id'];
        $task_model = $this->task_model;
        $result = $task_model->update(array('available_at'=>-1),array('task_id'=>$task_id));
        return $result;
    }
    
    //处理任务
    private function dealTask($data){
        $func = $data['type'];
        if(!empty($func) && method_exists($this,$func)){
            try {
                $result = $this->$func($data);
                $task_model = $this->task_model;
                $task_model->update(array('attempt'=>$data['attempt'] +1),array('task_id'=>$data['task_id']));
                return $result ? 1 : 2;
            }catch(\Exception $e){
                \think\Log::write($e->getMessage());
                return 0;
            }
        }
        
        return 0;
    }
    
    //延迟任务执行
    private function delayTask($task, $delay = 30){
        $task_model = $this->task_model;
        return $task_model->update(array('task_id'=>$task['task_id'],'available_at'=>$task['available_at'] + $delay));
    }

    /** 处理短信
     * @param $data
     * @return bool
     * 
     */
    private function sendSms($data){
        $handle = json_decode($data['data'],true);
        $result = sendSms($handle['phone'], $handle['module_id'], $handle['data']);
        return $result['status'];
    }
    
    //支付提醒
    private function payReminder($data){
        $handle = json_decode($data['data'],true);
        
        $order_model = model('common/Order');
        $order = $order_model->getInfo(array('order_id'=>$handle['order_id']));
        
        if(empty($order) || $order->getData('order_status') != 2){
            return true;
        }
        
        try{
            //添加系统消息
            $user_message_model = model('common/UserMessage');
            $content = sprintf('【付款通知】恭喜您求购成功（%s），价格为%s元，请尽快完成订单付款。',$order['buypart']['title'],$order['total_amount']);
            $user_message_model->addMessage($order['user_id'],'',$content);
            
            //添加平台消息
            $user_message_model = model('common/SysconfMessage');
            $content = sprintf('【报价通知】买方仍未付款（%s），请持续关注！',$order['buypart']['title']);
            $user_message_model->addMessage($order['user_id'],'',$content);
            
            //添加短信发送任务
            if(!empty($order['mobile'])){
                $task_data = array(
                    'type' => 'sendSms',
                    'data' => array(
                        'phone'=>$order['mobile'],
                        'module_id'=> '',
                        'data' => array()
                    ),
                    'available_at' => time()
                );
            
                $this->addTask($task_data);
            }
            
            //发送微信客服消息
            postCustomerMessage($order['user_id'], 10019, array($order['buypart']['title'],4000883993));
            return true;
        }catch(\Exception $e){
            return false;
        }
    }
    
    //确认收货提醒
    private function receiveReminder($data){
        $handle = json_decode($data['data'],true);
        
        $order_model = model('common/Order');
        $order = $order_model->getInfo(array('order_id'=>$handle['order_id']));
        
        if(empty($order) || $order->getData('order_status') != 2){
            return true;
        }
        
        try{
            //添加系统消息
            $user_message_model = model('common/UserMessage');
            $content = sprintf('【收货通知】您求购的商品已发货（%s），请收到货后尽快确认收货。',$order['buypart']['title']);
            $user_message_model->addMessage($order['user_id'],'',$content);
            
            //添加平台消息
            $user_message_model = model('common/SysconfMessage');
            $content = '【收货通知】订单发货已超过5天，请持续关注！';
            $user_message_model->addMessage($order['user_id'],'',$content);
            
            //添加短信发送任务
            if(!empty($order['mobile'])){
                $task_data = array(
                    'type' => 'sendSms',
                    'data' => array(
                        'phone'=>$order['mobile'],
                        'module_id'=> '',
                        'data' => array()
                    ),
                    'available_at' => time()
                );

                $this->addTask($task_data);
            }

            //发送微信客服消息
            postCustomerMessage($order['user_id'], 10019, array($order['buypart']['title'],4000883993),'miniprogrampage','');
            return true;
        }catch(\Exception $e){
            return false;
        }
    }
    
    //求购信息过期提醒
    private function outOfDateReminder($data){
        $handle = json_decode($data['data'],true);
        
        $buypart_model = model('common/Buyparts');
        $where = array();
        $where['buy_id'] = $handle['buy_id'];
        $where['status'] = 1;
        $where['is_delete'] = 0;
        $where['is_dongjie'] = 0;
        
        $buypart = $buypart_model->getInfo($where,'user_id,mobile,title,end_time');
        if(empty($buypart)){
            return true;
        }
        
        try{
            //将所有相关求购信息标记为过期失效
            $buypart_model->update(array('status'=>3),array('primitive_buy_id'=>$handle['buy_id']));
            
            //添加系统消息
            $user_message_model = model('common/UserMessage');
            $content = sprintf('【求购通知】您发布的求购（%s），现已到期，到个人中心重新发布吧！',$buypart['title']);
            $user_message_model->addMessage($buypart['user_id'],'',$content);
            
            //添加短信发送任务
            if(!empty($buypart['mobile'])){
                $task_data = array(
                    'type' => 'sendSms',
                    'data' => array(
                        'phone'=>$buypart['mobile'],
                        'module_id'=> '',
                        'data' => array()
                    ),
                    'available_at' => time()
                );
            
                $this->addTask($task_data);
            }
            
            //发送客服消息
            postCustomerMessage($buypart['user_id'], 10005, array($data['title'],4000883993),'miniprogrampage','');
      
            return true;
        }catch(\Exception $e){
            return false;
        }
        
    }
    
    //求购单定时转发
    private function transferPurchase($data){
        $handle = json_decode($data['data'],true);
        
        $buypart_model = model('common/Buyparts');
        $where = array();
        $where['buy_id'] = $handle['buy_id'];
        $where['is_delete'] = 0;//未删除
        $where['is_dongjie'] = 0;//未冻结
        $where['status'] = 1;//求购中
        $buypart = $buypart_model->getInfo(array('buy_id'=>$handle['buy_id']));
        
        //如果不存在，结束任务
        if(empty($buypart) || $buypart['is_forward'] || empty($buypart['agent_uid'])){
            return true;
        }
        
        //查询是否存在报价信息
        $buypart_offer_price_model = model('common/BuypartsOfferPrice');
        $offer_price_num = $buypart_offer_price_model->getOfferPriceNumByBuyId($handle['buy_id']);
        if($offer_price_num > 0){
            return true;
        }
        
        try{
            //将求购信息转至上级代理商
            $user_model = model('common/User');
            $agentor_info = $user_model->alias('u')->join('__USER_AGENT__ ua','u.uid = ua.uid')->field('u.*,ua.agent_level')->where(array('uid'=>$buypart['agent_uid']))->find();
            if(empty($agentor_info)){
                return true;
            }
            
            $user_agent_model = model('common/UserAgent');
            $agent_uids = $user_agent_model->getAgentUids($agentor_info['province'],$agentor_info['city'],$agentor_info['area']);
            
            $data = array();
            $data['user_id'] = $agentor_info['uid'];
            $data['add_time'] = time();
            $data['title'] = $buypart['title'];
            $data['contacts'] = $agentor_info['nickname'];
            $data['mobile'] = $agentor_info['phone'];
            $data['province'] = $agentor_info['province'];
            $data['city'] = $agentor_info['city'];
            $data['area'] = $agentor_info['area'];
            $data['address'] = $agentor_info['address'];
            $data['content'] = $buypart['content'];
            $data['img_count'] = $buypart['img_count'];
            $data['source_buy_id'] = $buypart['buy_id'];
            $data['primitive_buy_id'] = $buypart['primitive_buy_id'];
            $data['buying_type'] = 2;
            
            if($agentor_info['agent_level'] == 1){
                //省级代理，转给平台
                $data['agent_uid'] = 0;
            }elseif($agentor_info['agent_level'] == 2){
                //市级代理，转给省级代理
                $data['agent_uid'] = !empty($agent_uids['province']) ? $agent_uids['province'] : 0;
            }elseif($agentor_info['agent_level'] == 3){
                //区级代理，转给市级代理
                $data['agent_uid'] = !empty($agent_uids['city']) ? $agent_uids['city'] : (!empty($agent_uids['province']) ? $agent_uids['province'] : 0);
            }
            
            if($buypart_model->save($data)){
                $buypart_model->update(array('is_forward'=>1),array('buy_id'=>$handle['buy_id']));
            }
            return true;
        }catch(\Exception $e){
            return false;
        }
        
    }
}
