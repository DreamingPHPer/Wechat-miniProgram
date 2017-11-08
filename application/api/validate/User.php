<?php
namespace app\api\validate;
use think\Validate;

class User extends Validate{
    protected $regex = array(
        'mobile' => '/^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$/'
    );

	protected $rule = array(
	   'headimgurl'=> 'max:200',
	   'nickname' => 'require|max:60',
	   'truename' => 'require|max:60',
       'phone' => 'require|regex:mobile',
       'province' => 'require|number',
	   'city' => 'require|number',
	   'area' => 'require|number'
	);
	
	protected $message = array(
	    'headimgurl' => '头像名称太长',
	    'nickname.require' => '昵称不能为空',
	    'nickname.max' => '昵称最多可输入20个字符',
	    'truename.require' => '姓名不能为空',
	    'truename.max' => '姓名最多可输入20个字符',
	    'phone.require' => '手机号码不能为空',
	    'phone.regex' => '手机号码手机号码格式错误',
	    'province' => '请选择省份',
	    'city' => '请选择城市',
	    'area' => '请选择地区'
	);
}