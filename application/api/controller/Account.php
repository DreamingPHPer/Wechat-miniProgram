<?php
namespace app\api\controller;
use app\common\controller\BaseApi;
use think;

//账户信息控制类
class Account extends BaseApi{
    public $user_info = null;
    
    public function __construct() {
        parent::__construct();
        $this->user_info = $this->checkLoginStatus();
    }
    
    //账户详情
    public function detail(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $balance_model = model('UserAccount');
        
        $balance = $balance_model->getInfo(array('uid'=>$this->user_info['uid']));
        
        if(empty($balance)){
            return jsonReturn(NO_RESULT, '当前用户账户不存在');
        }
        
        return jsonReturn(SUCCESSED, '信息获取成功', $balance);
    }
    
    //账户提现
    public function withdrawal(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $user_account_model = model('UserAccount');
        $balance = $user_account_model->getInfo(array('uid'=>$this->user_info['uid']),'balance');
        
        if(empty($balance)){
            return jsonReturn(NO_AUTHORITY, '当前用户账户不存在');
        }
        
        $money = input('post.money',0);
        
        if(empty($money) || !is_numeric($money) || $money < 0.01){
            return jsonReturn(WRONG_PARAM, '请正确填写提现金额');
        }
        
        if($money > $balance['balance']){
            return jsonReturn(NO_AUTHORITY, '提现金额不能大于账户余额');
        }

        //获取手续费比例
        $service_charge_rate = model('Config')->getValueByName('service_charge');
        $service_charge_rate = !empty($service_charge_rate) ? $service_charge_rate : 0;
        
        //添加提现申请记录
        $withdrawal_data = array();
        $withdrawal_data['uid'] = $this->user_info['uid'];
        $withdrawal_data['username'] = $this->user_info['truename'];
        $withdrawal_data['phone'] = $this->user_info['phone'];
        $withdrawal_data['withdrawal_type'] = 1;
        $withdrawal_data['amount'] = $money;
        $withdrawal_data['charge_price'] = $money*$service_charge_rate/100;
        $withdrawal_data['withdrawal_type_status'] = 1;
        $withdrawal_data['add_time'] = time();

        try{
            $withdrawal_model = model('Withdrawal');
            $balance_model = model('Balance');
            $withdrawal_result = $withdrawal_model->save($withdrawal_data);
            
            if($withdrawal_result){
                //更新账户余额和冻结金额
                $unbalance = $money + $withdrawal_data['charge_price'];
                $user_account_model->save(array('unbalance'=>$unbalance,'balance'=>$balance['balance']-$money),array('uid'=>$this->user_info['uid']));
                
                //添加账户金额出入记录
                $balance_data = array();
                $balance_data['uid'] = $this->user_info['uid'];
                $balance_data['type'] = 4;//余额提现
                $balance_data['amount'] = $unbalance;
                $balance_data['balance_amount'] = $balance['balance']-$money;
                $balance_data['add_time'] = time();
                $balance_model->save($balance_data);
                
                //给代理商添加系统消息
                $content = sprintf('【提现通知】您的提现申请（%s）已提交成功，系统预计会在1-3个工作日之内给您办理，敬请留意。如需帮助，请拨打客服电话%s。',$money,4000883993);
                $user_message_model = model('UserMessage');
                $user_message_model->addMessage(array('uid'=>$this->user_info['uid'],'title'=>'','content'=>$content));
                
                //给平台添加系统消息
                $content = sprintf('【提现通知】有新的提现申请（%s）提交，请在3个工作日内完成处理',$money);
                $user_message_model = model('SysconfMessage');
                $user_message_model->addMessage(array('uid'=>$this->user_info['uid'],'title'=>'','content'=>$content));
                
                //发送客服消息
                postCustomerMessage($this->user_info['uid'], 10009, array($money,4000883993));

                return jsonReturn(SUCCESSED, '提现申请成功');
            }else{
                return jsonReturn(BAD_SERVER, '操作失败');
            }
            
        }catch (\Exception $e){
            \think\Log::write($e->getMessage());
            return jsonReturn(BAD_SERVER, '操作失败');
        }
    }
}