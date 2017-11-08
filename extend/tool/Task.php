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
                if(!$result && $this->attempt > $data['attempt']){
                    $this->delayTask($data);
                }
            }else{
                sleep(15);
                continue;
            }
        }
    }
    
    /*
     * 添加任务
     * param type 标志
     * pram data 
     * form 来源
     * param $if_multiterm 数据是否多条
     * */
    public function addTask($datas, $form = 1, $type = 'sendSms',$if_multiterm = false){
        if(empty($datas)){
            return false;
        }
        
        $task_model = $this->task_model;

        $new_data = array();
        if($if_multiterm){
            foreach($datas as $data){
                $new_data[] = array(
                    'type' => $type,
                    'data' => json_encode($data['data'], JSON_UNESCAPED_UNICODE),
                    'add_time'=>time(),
                    'available_at'=>$data['available_at'],
                    'from' => $form
                );
            }
        }else{
            $new_data[] = array(
                'type' => $type,
                'data' => json_encode($datas['data'], JSON_UNESCAPED_UNICODE),
                'add_time'=>time(),
                'available_at'=>$datas['available_at'],
                'from' => $form
            );
        }
        
        return $task_model->saveAll($new_data);
    }
    
    
    //获取任务
    private function getTask($type = null){
        $task_model = $this->task_model;
        
        $where = array();
        
        if(!is_null($type)){
            $where['type'] = $type;
        }
        
        $where['status'] = array('neq',1);
        $where['available_at'] = array('elt',time());
        
        return $task_model->where($where)->order('available_at asc')->find();
    }
    
    //更新任务状态
    private function changeTaskStatus($data,$status){
        $task_id = $data['task_id'];
        $task_model = $this->task_model; 
        $result = $task_model->update(array('status'=>$status,'attempt'=>$data['attempt'] +1),array('task_id'=>$task_id));
        return $result;
    }
    
    //处理任务
    private function dealTask($data){
        if(empty($data) || $data['available_at'] > time()) {
            return false;
        }

        $result = '';
        $func = $data['type'];
        if(!empty($func) && method_exists($this,$func)){
            $result = $this->$func($data);
            return $result;
        }
        
        return false;
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
        $status = $result ? 1 : 2;
        $this->changeTaskStatus($data, $status);
        return $result['status'];
    }
    
    //支付提醒
    private function payReminder($data){
        $handle = json_decode($data['data'],true);
        
        $order_model = model('api/Order');
        $order = $order_model->getInfo(array('order_id'=>$handle['order_id']));
        
        if(empty($order) || $order['status'] != 2){
            $this->changeTaskStatus($data, 1);//将任务标记为完成
            return true;
        }
        
        try{
            //添加系统消息
            $user_message_model = model('api/UserMessage');
            $content = sprintf('【付款通知】恭喜您求购成功（%s），价格为%s元，请尽快完成订单付款。',$order['buypart']['title'],$order['total_amount']);
            $user_message_model->addMessage($order['user_id'],'',$content);
            
            //添加平台消息
            $user_message_model = model('api/SysconfMessage');
            $content = sprintf('【报价通知】买方仍未付款（%s），请持续关注！',$order['buypart']['title']);
            $user_message_model->addMessage($order['user_id'],'',$content);
            
            //发送微信客服消息
            postCustomerMessage($order['user_id'], 10019, array($order['buypart']['title'],4000883993));
            
            //发送短信
            sendSms($order['mobile'], 111111, array());
            
            //将任务标记为完成
            $this->changeTaskStatus($data, 1);
            
            return true;
        }catch(\Exception $e){
            //将任务标记为失败
            $this->changeTaskStatus($data, 2);
            return false;
        }
    }
    
    //确认收货提醒
    private function receiveReminder($data){
        $handle = json_decode($data['data'],true);
        
        $order_model = model('api/Order');
        $order = $order_model->getInfo(array('order_id'=>$handle['order_id']));
        
        if(empty($order) || $order['status'] != 2){
            $this->changeTaskStatus($data, 1);//将任务标记为完成
            return true;
        }
        
        try{
            //添加系统消息
            $user_message_model = model('api/UserMessage');
            $content = sprintf('【收货通知】您求购的商品已发货（%s），请收到货后尽快确认收货。',$order['buypart']['title']);
            $user_message_model->addMessage($order['user_id'],'',$content);
            
            //添加平台消息
            $user_message_model = model('api/SysconfMessage');
            $content = '【收货通知】订单发货已超过5天，请持续关注！';
            $user_message_model->addMessage($order['user_id'],'',$content);
            
            //发送微信客服消息
            postCustomerMessage($order['user_id'], 10019, array($order['buypart']['title'],4000883993),'miniprogrampage','');
            
            //发送短信
            sendSms($order['mobile'], 111111, array());
            
            //将任务标记为完成
            $this->changeTaskStatus($data, 1);
            
            return true;
        }catch(\Exception $e){
            //将任务标记为失败
            $this->changeTaskStatus($data, 2);
            return false;
        }
    }
    
    //求购信息过期提醒
    private function outOfDateReminder($data){
        $handle = json_decode($data['data'],true);
        
        $buypart_model = model('api/Buyparts');
        $where = array();
        $where['buy_id'] = $handle['buy_id'];
        $where['status'] = 1;
        $where['is_delete'] = 0;
        $where['is_dongjie'] = 0;
        
        $buypart = $buypart_model->getInfo($where,'user_id,mobile,title,end_time');
        if(empty($buypart)){
            $this->changeTaskStatus($data, 1);//将任务标记为完成
            return true;
        }
        
        //将求购信息标记为过期失效
        $buypart_model->update(array('status'=>3),array('buy_id'=>$handle['buy_id']));
        
        //添加系统消息
        $user_message_model = model('api/UserMessage');
        $content = sprintf('【求购通知】您发布的求购（%s），现已到期，到个人中心重新发布吧！',$buypart['title']);
        $user_message_model->addMessage($buypart['user_id'],'',$content);
        
        //发送客服消息
        postCustomerMessage($buypart['user_id'], 10005, array($data['title'],4000883993),'miniprogrampage','');
        
        //发送短信
        sendSms($buypart['mobile'], 111111, array());
    }
}
