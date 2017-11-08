<?php
namespace app\api\validate;
use think\Validate;

class UserAddress extends Validate{
    protected $regex = array(
        'mobile' => '/^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$/'
    );

	protected $rule = array(
	   'name' => 'require|max:60',
       'tel' => 'require|regex:mobile',
       'province' => 'require|number',
	   'city' => 'require|number',
	   'area' => 'require|number',
	   'address' => 'require|max:150', 
	);
	
	protected $message = array(
	   'name.require' => '联系人不能为空',
	   'name.max' => '联系人最多可输入20个字符',
       'tel.require' => '联系电话不能为空',
	   'tel.regex' => '联系电话格式错误',
       'province' => '省份编码不能为空',
	   'city' => '城市编码不能为空',
	   'area' => '地区编码不能为空',
	   'address.require' => '详细地址不能为空',
	   'address.max' => '详细地址最多可输入50个字符'
	);
}