<?php
namespace app\api\model;
use think\Model;

//用户信息
class User extends Model{
    /**
     * 根据openid获取用户信息
     * @param unknown $openid
     * @param string $field
     * @return string|unknown
     */
    public function getUserInfoByOpenid($openid,$field="*"){
        $unionid = $this->getUnionidByOpenid($openid);
    
        $where =array();
        $where['unionid'] = $unionid;
    
        $user_info = $this->where($where)->field($field)->find();
        return !empty($user_info) ? $user_info : '';
    }
    
    /**
     * 根据small_openid获取用户信息
     * @param unknown $openid
     * @param string $field
     */
	public function getUserInfoBySmallOpenid($openid,$field="*"){
		$where =array(
				'small_openid' => $openid
		);
		$user_info = $this->where($where)->field($field)->find();
		return !empty($user_info) ? $user_info : '';
	}
	
	/**
	 * 根据unionid获取用户信息
	 * @param unknown $unionid
	 * @param string $field
	 */
	public function getUserInfoByUnionid($unionid,$field="*"){
	    $where =array(
	        'unionid' => $unionid
	    );
	    $user_info = $this->where($where)->field($field)->find();
	    return !empty($user_info) ? $user_info : '';
	}
	
	/**
	 * 根据用户id获取用户信息
	 * @param unknown $uid
	 * @param string $field
	 */
	public function getUserInfoByUid($uid,$field="*"){
	    $where =array(
	        'uid' => $uid
	    );
	    $user_info = $this->where($where)->field($field)->find();
	    return !empty($user_info) ? $user_info : '';
	}
	
	/**
	 * 判断用户信息是否完整
	 * @param unknown $uid
	 */
	public function isComplete($uid){
	    $user_info = $this->getUserInfoByUid($uid,'nickname,truename,phone,province,city,area');
	    
	    if(empty($user_info['nickname']) || empty($user_info['truename']) || empty($user_info['phone']) || (empty($user_info['province'])) && empty($user_info['city']) && empty($user_info['area'])){
	        return false;
	    }
	    
	    return true;
	}
	
	/**
	 * 更新用户所属代理商
	 * @param unknown $where
	 * @param unknown $data
	 * @return \app\api\model\User
	 */
	public function updateUsers($where,$data){
	    return $this->update($where,$data);
	}
	
	/**
	 * 获取所有用户
	 * @param unknown $where
	 * @param string $field
	 */
	public function getUsers($where,$field="*"){
	    return $this->where($where)->field($field)->select();
	}
	
	/**
	 * 根据用户id获取各级代理商
	 * @param unknown $uid
	 */
	public function getAllAgentsByUid($uid){
	    //查询用户信息
	    $user = $this->getUserInfoByUid($uid,'uid,province,city,area');
	    
	    if(empty($user)){
	        return array();
	    }
	    
	    //查询各级代理
	    $user_agent_model = model('UserAgent');
	    $agents = $user_agent_model->getAgentUids($user['province'],$user['city'],$user['area']);
	    
	    return $agents;
	}
	
	/**
	 * 更新推荐用户字段
	 * @param $openid 被关联用户openid
	 * @param $agent_uid 代理商id
	 */
	public function updateRecommentUid($openid,$agent_uid){
	    $user = $this->getUserInfoByOpenid($openid,'uid,recomment_uid');
	    
	    if(empty($user)){
	        return false;
	    }
	    
	    if(!empty($user['recomment_uid'])){
	        return false;
	    }
	    
	    $result = $this->update(array('recomment_uid'=>$agent_uid),array('openid'=>$openid));
	    return $result !== false ? true : false;
	}
	
	/**
	 * 添加、更新用户信息
	 * @param unknown $data
	 * @param string $if_update
	 */
	public function addUserFromWechat($data){
	    $user = $this->where(array('openid'=>$data['openid']))->field('uid,unionid,if_subscribe')->find();
	    
	    $result = false;
	    $unionid = $this->getUnionidByOpenid($data['openid']);
	    if(empty($user)){
	        $data['unionid'] = $unionid;
	        $data['if_subscribe'] = 1;
	        $result = $this->save($data);
	    }elseif(empty($user['if_subscribe'])){
	        $result = $this->save(array('if_subscribe'=>1),array('uid'=>$user['uid']));
	    }elseif(empty($user['unionid'])){
	        $result = $this->save(array('unionid'=>$unionid),array('uid'=>$user['uid']));
	    }
	
	    return $result !== false ? true : false;
	}
	
	/**
	 * 根据openid获取unionid
	 * @param unknown $openid
	 * @return string|mixed
	 */
	private function getUnionidByOpenid($openid) {
	    //根据openid获取unionid
	    $wechat = new \tool\Wechat(config('APPID'), config('APPSECRET'));
	    return $wechat->getUnionid($openid);
	}
}