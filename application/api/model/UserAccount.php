<?php
namespace app\api\model;
use think\Model;

//账户信息控制模型
class UserAccount extends Model{
    /**
     * 获取账户详情
     * @param unknown $where
     * @param string $field
     */
    public function getInfo($where, $field = "*"){
        return $this->where($where)->field($field)->find();
    }

    /**
     * 增加账户余额
     * @param unknown $where
     * @param number $inc_balance
     */
    public function setIncBalance($where,$inc_balance = 1){
        return $this->where($where)->setInc('balance',$inc_balance);
    }
    
    /**
     * 减少账户余额
     * @param unknown $where
     * @param number $dec_balance
     */
    public function setDecBalance($where,$dec_balance = 1){
        return $this->where($where)->setDec('balance',$dec_balance);
    }
}