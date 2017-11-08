<?php
namespace app\api\validate;
use think\Validate;

//报价验证类
class BuypartsOfferPrice extends Validate{
    protected $regex = array(
        'mobile' => '/^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$/'
    );
    
    //规则
	protected $rule = array(
		'buy_id' => 'require',
		'contacts' => 'require|max:60',
		'mobile' => 'require|regex:mobile',
        'price' => 'require|number|gt:0|elt:100000000',
		'content' => 'max:900'
	);
	
	//消息
	protected $message = array(
		'title.require' => '标题不能为空',
	    'title.max' => '标题最多可输入20个字符',
		'contacts.require' => '联系人不能为空',
	    'contacts.max' => '联系人最多可输入20个字符',
	    'mobile.require' => '联系人手机号码不能为空',
		'mobile.regex' => '联系人手机号码格式错误',
        'price.require' => '报价不能为空',
	    'price.gt' => '报价必须大于0',
	    'price.elt' => '报价如果不能超过100000000',
		'content' => '详情描述做的可输入300个字符'
	);
	
	//场景
	protected $scene = array(
		
	);
	
}