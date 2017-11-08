<?php
namespace app\api\controller;
use app\common\controller\BaseApi;
use tool\Task;

//求购信息控制类
class Purchase extends BaseApi{
    public $user_info = null;
    
    public function __construct(){
        parent::__construct();
        $this->user_info = $this->checkLoginStatus();
    }
    
    //求购列表
    public function lists(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $kw = input('post.kw','');
        $need_page = input('post.needPage/d',0);
        $page = input('post.page/d',1);
        
        $where = $query_params = array();
        if(!empty($kw)){
            $query_params['kw'] = $kw;
            $where[] = "(title like '%".$kw."%' or content like '%".$kw."%')";
        }
        
        $where[] = "(agent_uid = 0 or agent_uid = ".$this->user_info['uid'].")";
        $where[] = "status = 1";
        $where[] = "user_id <> ".$this->user_info['uid'];
        
        $where = implode(' and ', $where);
        $query_params['page'] = $page;

        $buyparts_model = model('Buyparts');
        $need_page = $need_page ? true : false;
        $field = 'buy_id,user_id,end_time,title,province,city,area,content';
        $buyparts = $buyparts_model->getBuyparts($where,$field,$need_page,$query_params);

        if($need_page && $buyparts->total() || !empty($buyparts)){
            foreach($buyparts as &$buypart){
                $buypart['end_time'] = !empty($buypart['end_time']) ? date('Y-m-d H:i',$buypart['end_time']) : '';
                
                //获取发布人信息
                $user_model = model('User');
                $buyer_info = $user_model->getUserInfoByUid($buypart['user_id'],'username,nickname,truename,headimgurl,phone');
                $buyer_info['headimgurl'] = !empty($buyer_info['headimgurl']) ? getAttachmentUrl($buyer_info['headimgurl'],true) : '';
                $buyer_info['phone'] = !empty($buyer_info['phone']) ? formatMobile($buyer_info['phone']) : '';
                $buypart['buyer'] = $buyer_info;
                
                //获取报价数量
                $offer_price_num = $buyparts_model->getOfferPriceNum(array('buy_id'=>$buypart['buy_id'],'status'=>array('neq',3)));
                $buypart['offer_price_num'] = $offer_price_num;
                
                //获取求购信息的位置
                $area_model = model('Area');
                $buypart['province'] = $area_model->getNameByAreaCode($buypart['province']);
                $buypart['city'] = $area_model->getNameByAreaCode($buypart['city']);
                $buypart['area'] = $area_model->getNameByAreaCode($buypart['area']);
            }
        }
        
        return jsonReturn(SUCCESSED, '获取成功', $buyparts);
    }
    
    //发布求购
    public function post(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $data = array();
        $data['title'] = input('post.title','');
        $data['contacts'] = input('post.contactUser','');
        $data['mobile'] = input('post.contactPhone','');
        $data['province'] = input('post.province','');
        $data['city'] = input('post.city','');
        $data['area'] = input('post.area','');
        $data['address'] = input('post.address','');
        $data['content'] = input('post.content','');
        
        $validate_result = $this->validate($data, 'Buyparts');
        if($validate_result !== true){
            return jsonReturn(WRONG_PARAM, $validate_result);
        }
        
        $images = input('post.image/a',array());
        if(!empty($images)){
            $data['img_count'] = count($images);
        }
        
        $data['user_id'] = $this->user_info['uid'];
        $data['add_time'] = time();
        $data['end_time'] = time() + 30*60;//截止日期，默认为发布时间后的30分钟

        //获取区级代理商
        if(!empty($this->user_info['agent_uid'])){
            $data['is_agent'] = 1;
            $data['agent_uid'] = $this->user_info['agent_uid'];
        }
        
        $buyparts_model = model('Buyparts');
        if($buyparts_model->save($data)){
            //发布成功，保存图片到图片表
            if(!empty($images)){
                $buy_id = $buyparts_model->buy_id;
                $images_data = array();
                foreach ($images as $image){
                    $images_data[] = array(
                        'buy_id' => $buy_id,
                        'imgPath' => $image
                    );
                }
            
                $buyparts_img_model = model('BuypartsImg');
                $buyparts_img_model->saveAll($images_data);
            }
            
            if(!empty($data['agent_uid'])){
                //添加系统消息
                $content = sprintf('【求购通知】又有新的求购啦（%s），在微信中报价或登录管理后台处理吧！',$data['title']);
                $user_message_model = model('UserMessage');
                $user_message_model->addMessage(array('uid'=>$data['agent_uid'],'title'=>'','content'=>$content));
                
                //添加平台消息
                $content = sprintf('【求购通知】有新的求购发布（%s），请持续关注！',$data['title']);
                $sysconf_message_model = model('SysconfMessage');
                $sysconf_message_model->addMessage(array('uid'=>$data['agent_uid'],'title'=>'','content'=>$content));
                
                //添加任务
                $task_datas = array();
                
                //添加到期提醒任务
                $task_datas[] = array(
                    'type' => 'outOfDateReminder',
                    'data' => array('buy_id'=>$buy_id),
                    'available_at' => $data['end_time']
                );

                //添加短信发送任务
                $user_model = model('User');
                $agent = $user_model->getUserInfoByUid($data['agent_uid'],'phone');
                if(!empty($agent['phone'])){
                    $task_datas[] = array(
                        'type' => 'sendSms',
                        'data' => array(
                            'phone'=>$agent['phone'],
                            'module_id'=> '',
                            'data' => array()
                         ),
                        'available_at' => $data['end_time']
                    );
                }

                $task = new Task();
                $task->addTask($task_datas, 1, true);
                
                //发送客服消息
                postCustomerMessage($data['agent_uid'], 10004, array($data['title'],4000883993),'miniprogrampage','');
                
            }

            return jsonReturn(SUCCESSED, '操作成功', date('Y-m-d H:i:s',$data['end_time']));
        }
        
        return jsonReturn(BAD_SERVER, '操作失败');
    }
    
    //求购信息详情
    public function detail(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $buy_id = input('post.id/d',0);
        if(!$buy_id){
            return jsonReturn(WRONG_PARAM, '求购信息ID不能为空');
        }
        
        $buyparts_model = model('Buyparts');
        
        $where = array();
        $where['buy_id'] = $buy_id;
        $where['is_delete'] = 0;
        $where['is_dongjie'] = 0;
        
        $buypart_field = 'buy_id,user_id,title,content,contacts,mobile,province,city,area,img_count,add_time,end_time,status';
        $buypart = $buyparts_model->getInfo($where,$buypart_field);
        if(empty($buypart)){
            return jsonReturn(NO_RESULT, '无法获取相关信息');
        }
        
        //获取求购信息的位置
        $area_model = model('Area');
        $buypart['province'] = $area_model->getNameByAreaCode($buypart['province']);
        $buypart['city'] = $area_model->getNameByAreaCode($buypart['city']);
        $buypart['area'] = $area_model->getNameByAreaCode($buypart['area']);
        
        //修改状态显示
        $buypart['status_code'] = $buypart->getData('status');

        //获取用户信息
        $user_model = model('User');
        $buyer_info = $user_model->getUserInfoByUid($buypart['user_id'],'username,headimgurl,phone');
        $buyer_info['headimgurl'] = !empty($buyer_info['headimgurl']) ? getAttachmentUrl($buyer_info['headimgurl'],true) : '';
        $buyer_info['phone'] = !empty($buyer_info['phone']) ? formatMobile($buyer_info['phone']) : '';
        $buypart['buyer'] = $buyer_info;
        
        //获取求购信息图片
        $buyparts_imgs = array();
        if($buypart['img_count']){
            $buyparts_img_model = model('BuypartsImg');
            $buyparts_imgs = $buyparts_img_model->getImgsByBuyId($buypart['buy_id']);
        }
        $buypart['images'] = $buyparts_imgs;
        
        //获取当前登录用户的报价信息
        $offer_prices = array();
        $offer_price_num = 0;
        $buyparts_offer_price_model = model('BuypartsOfferPrice');
        $buyparts_negotiated_price_model = model('BuypartsNegotiatedPrice');
        $offer_price_field = 'offer_id,add_time,price,content,status,img_count';
        
        $offer_price_where = array();
        $offer_price_where['user_id'] = $this->user_info['uid'];
        $offer_price_where['buy_id'] = $buypart['buy_id'];
        $offer_price_where['status'] = array('neq',3);
        $offer_prices = $buyparts_offer_price_model->getOfferPrices($offer_price_where,$offer_price_field);
        
        if(!empty($offer_prices)){
            $offer_price_num = count($offer_prices);
            
            foreach ($offer_prices as &$offer_price){
                $offer_price['add_time'] = date('Y-m-d H:i',$offer_price['add_time']);
                $offer_price['status_code'] = $offer_price->getData('status');
                
                //获取报价信息图片
                $offer_price_imgs = array();
                if($offer_price['img_count']){
                    $buyparts_offer_price_img_model = model('BuypartsOfferPriceImg');
                    $offer_price_imgs = $buyparts_offer_price_img_model->getImgsByBuyId($offer_price['offer_id']);
                }
                $offer_price['images'] = $offer_price_imgs;
                
                //获取议价信息
                $offer_price['negotiated_price'] = $buyparts_negotiated_price_model->getPrice(array('offer_id'=>$offer_price['offer_id']));
            }
        }
        
        $buypart['offer_prices'] = $offer_prices;
        $buypart['offer_price_num'] = $offer_price_num;
        
        return jsonReturn(SUCCESSED, '信息获取成功', $buypart);
    }
    
    //求购报价
    public function offerPrice(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $buy_id = input('post.id/d',0);
        
        //获取求购信息
        $buyparts_model = model('Buyparts');
        $where = array();
        $where['buy_id'] = $buy_id;
        $where['is_delete'] = 0;
        $where['is_dongjie'] = 0;
        
        $buypart = $buyparts_model->getInfo($where,'buy_id,user_id,mobile,title,is_agent,agent_uid');
        if(empty($buypart)){
            return jsonReturn(NO_RESULT, '求购信息不存在');
        }
        
        if(!$this->user_info['user_type']){
            return jsonReturn(NO_AUTHORITY, '只有代理商才能参与报价');
        }
        
        //已经分配代理商
        if($buypart['is_agent'] && $buypart['agent_uid'] != $this->user_info['uid']){
            return jsonReturn(NO_AUTHORITY, '只有所属代理商才能参与报价');
        }
        
        if($buypart['user_id'] == $this->user_info['uid']){
            return jsonReturn(NO_AUTHORITY, '自己发布的求购信息不能参与报价');
        }
        
        $data = array();
        $data['buy_id'] = $buy_id;
        $data['contacts'] = input('post.contactUser','');
        $data['mobile'] = input('post.contactPhone','');
        $data['price'] = input('post.price',0);
        $data['content'] = input('post.content','');
        
        $validate_result = $this->validate($data, 'BuypartsOfferPrice');
        if($validate_result !== true){
            return jsonReturn(WRONG_PARAM, $validate_result);
        }

        $data['add_time'] = time();
        $data['user_id'] = $this->user_info['uid'];
        
        $images = input('post.image/a',array());
        if(!empty($images)){
            $data['img_count'] = count($images);
        }
        
        $buyparts_offer_price_model = model('BuypartsOfferPrice');
        
        if($buyparts_offer_price_model->save($data)){

            //报价成功，保存图片到图片表
            if(!empty($images)){
                $offer_id = $buyparts_offer_price_model->offer_id;
                $images_data = array();
                foreach ($images as $image){
                    $images_data[] = array(
                        'offer_id' => $offer_id,
                        'imgPath' => $image
                    );
                }
            
                $buyparts_offer_price_img_model = model('BuypartsOfferPriceImg');
                $buyparts_offer_price_img_model->saveAll($images_data);
            }
            
            //添加系统消息
            $content = sprintf('【报价通知】又有新的报价啦（%s），报价为%s元，在微信中接受报价或登录管理后台处理吧！',$buypart['title'], $data['price']);
            $user_message_model = model('UserMessage');
            $user_message_model->addMessage(array('uid'=>$buypart['user_id'],'title'=>'','content'=>$content));
            
            //添加平台消息
            $content = sprintf('【报价通知】有新的报价发布（%s），请持续关注！',$buypart['title']);
            $sysconf_message_model = model('SysconfMessage');
            $sysconf_message_model->addMessage(array('uid'=>$buypart['user_id'],'title'=>'','content'=>$content));
            
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
                
                $task = new Task();
                $task->addTask($task_data);
            }
  
            //发送客服消息
            postCustomerMessage($buypart['user_id'], 10006, array($buypart['title'],$data['price'],4000883993),'miniprogrampage','');

            return jsonReturn(SUCCESSED, '操作成功');
        }
        
        return jsonReturn(BAD_SERVER, '操作失败');
    }
    
    //报价详情
    public function getOfferPrice(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $offer_id = input('post.id/d',0);
        if(!$offer_id){
            return jsonReturn(WRONG_PARAM, '参数id传递错误');
        }
        
        $buypart_offer_price_model = model('BuypartsOfferPrice');
        $field = 'offer_id,user_id,buy_id,price,content,status,add_time';
        $offer_price = $buypart_offer_price_model->getInfo(array('offer_id'=>$offer_id));
        
        return jsonReturn(SUCCESSED, '获取成功', $offer_price);
    }
    
    //获取我发布的求购
    public function getMyBuyparts(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $type = input('post.status','all');
        $need_page = input('post.needPage/d',0);
        $page = input('post.page/d',1);
        
        $where = $query_params = array();
        $query_params['page'] = $page;
        
        $where['user_id'] = $this->user_info['uid'];
        
        $status = null;
        switch ($type){
            case 'purchasing':
                $status = 1;
                break;
            case 'purchased':
                $status = 2;
                break;
            case 'failed':
                $status = 3;
                break;
        }
        
        if(!is_null($status)){
            $where['status'] = $status;
        }
        
        $where['is_delete'] = 0;
        
        $buyparts_model = model('Buyparts');
        $buyparts_offer_price = model('BuypartsOfferPrice');
        $need_page = $need_page ? true : false;
        $field = 'user_id,buy_id,title,end_time,status,province,city,area';
        $buyparts = $buyparts_model->getBuyparts($where,$field,$need_page,$query_params);
        if($need_page && $buyparts->total() || !empty($buyparts)){
            $area_model = model('Area');
            foreach($buyparts as &$buypart){
                $buypart['end_time'] = date('Y-m-d H:i',$buypart['end_time']);

                //获取报价数量
                $offer_price_num = $buyparts_model->getOfferPriceNum(array('buy_id'=>$buypart['buy_id']));
                $buypart['offer_price_num'] = $offer_price_num;

                if($buypart->getData('status') == 1){
                    //获取最高报价
                    $buypart['max_offer_price'] = $buyparts_offer_price->getExtOfferPrice($buypart['buy_id'],'max');
                }elseif($buypart->getData('status') == 2){
                    //获取成交价
                    $buypart['deal_offer_price'] = $buyparts_offer_price->getDealOfferPrice($buypart['buy_id']);
                }elseif($buypart->getData('status') == 3){
                    //获取最低报价
                    $buypart['min_offer_price'] = $buyparts_offer_price->getExtOfferPrice($buypart['buy_id'],'min');  
                }
                
                //获取省市区的中文名称
                $buypart['province_txt'] = $area_model->getNameByAreaCode($buypart['province']);
                $buypart['city_txt'] = $area_model->getNameByAreaCode($buypart['city']);
                $buypart['area_txt'] = $area_model->getNameByAreaCode($buypart['area']);
            }
        }
        
        return jsonReturn(SUCCESSED, '获取成功', $buyparts);  
    }
    
    //终止、重新发布
    public function changePostStatus(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $buy_id = input('post.id/d',0);
        $type = input('post.type','');
        
        if(empty($buy_id) || empty($type)){
            return jsonReturn(WRONG_PARAM, '参数不能为空');
        }
        
        $status = null;
        switch($type){
            case 'stop':
                $status = 3;
                break;
            case 'restart':
                $status = 1;
                break;
        }
        
        if(is_null($status)){
            return jsonReturn(WRONG_PARAM, '参数type错误');
        }
        
        $buyparts_model = model('Buyparts');
        $where = array();
        $where['buy_id'] = $buy_id;
        $where['is_delete'] = 0;
        $buypart = $buyparts_model->getInfo($where,'buy_id,user_id');
        if(empty($buypart)){
            return jsonReturn(NO_RESULT, '信息不存在');
        }
        
        if($buypart['user_id'] != $this->user_info['uid']){
            return jsonReturn(NO_AUTHORITY, '当前用户不能执行当前操作');
        }
        
        $data = array();
        $data['status'] = $status;
        
        $return_data = '';
        if($type == 'restart'){
            $data['end_time'] = time() + 30 * 60;
            $return_data = date('Y-m-d H:i',$data['end_time']);
        }
        
        $result = $buyparts_model->update($data,array('buy_id'=>$buy_id));
        
        if($result !== false){
            return jsonReturn(SUCCESSED, '操作成功', $return_data);
        }
        
        return jsonReturn(BAD_SERVER, '操作失败');
    }
    
    //获取报价列表
    public function getOfferPrices(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $buy_id = input('post.id/d',0);
        $sort = input('post.sort','time');
        
        if(empty($buy_id)){
            return jsonReturn(WRONG_PARAM, '参数不能为空');
        }
        
        $buyparts_model = model('Buyparts');
        $where = array();
        $where['buy_id'] = $buy_id;
        $where['is_delete'] = 0;
        $where['is_dongjie'] = 0;
        $buypart = $buyparts_model->getInfo($where,'buy_id,user_id');
        if(empty($buypart)){
            return jsonReturn(NO_RESULT, '当前求购信息不存在');
        }
        
        if($buypart['user_id'] != $this->user_info['uid']){
            return jsonReturn(NO_AUTHORITY, '当前用户不能查看当前信息');
        }
        
        switch($sort){
            case "price":
                $order = "price desc";
                break;
            default:
                $order = "add_time desc";
        }
        
        //获取报价
        $buyparts_offer_price_model = model('BuypartsOfferPrice');
        $buyparts_offer_price_img_model = model('BuypartsOfferPriceImg');
        $field = 'offer_id,user_id,add_time,price,content,img_count,status';
        $offer_prices = $buyparts_offer_price_model->getOfferPrices(array('buy_id'=>$buy_id,'status'=>array('neq',3)),$field,$order);
        if(!empty($offer_prices)){
            $user_model = model('User');
            foreach($offer_prices as &$offer_price){
                $offer_price['add_time'] = date('Y-m-d H:i',$offer_price['add_time']);
                $offer_price['status_txt'] = $offer_price['status'];
                $offer_price['status'] = $offer_price->getData('status');
                
                //获取报价信息图片
                $images = array();
                if($offer_price['img_count']){
                    $images = $buyparts_offer_price_img_model->getImgsByBuyId($offer_price['offer_id']);
                }
                $offer_price['images'] = $images;
                
                //获取报价信息用户
                $offer_price['seller'] = $user_model->getUserInfoByUid($offer_price['user_id'],'username,truename,phone');  
            }
        }
        
        return jsonReturn(SUCCESSED, '获取成功', $offer_prices);
    }
    
    //删除报价
    public function delOfferPrice(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $offer_id = input('post.id/d',0);
        
        if(empty($offer_id)){
            return jsonReturn(WRONG_PARAM, '参数id不能为空');
        }
        
        $buyparts_offer_price_model = model('BuypartsOfferPrice');
        $offer_price = $buyparts_offer_price_model->getBuypartByOfferId($offer_id);
        
        if(empty($offer_price)){
            return jsonReturn(NO_RESULT, '当前信息不存在');
        }
        
        if($offer_price['seller_id'] != $this->user_info['uid']){
            return jsonReturn(NO_AUTHORITY, '当前用户不能进行当前操作');
        }
        
        $result = $buyparts_offer_price_model->update(array('status'=>3),array('offer_id'=>$offer_id));
        
        if($result !== false){
            return jsonReturn(SUCCESSED, '操作成功');
        }
        
        return jsonReturn(BAD_SERVER, '操作失败');
    }
 
    //报价议价
    public function changeOfferPrice(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $offer_id = input('post.id/d',0);
        $price = input('post.price',0);
        
        if(empty($offer_id)){
            return jsonReturn(WRONG_PARAM, '参数id不能为空');
        }
        
        if(empty($price) || !is_numeric($price) || $price < 0 || $price > 100000000){
            return jsonReturn(WRONG_PARAM, '参数price有误');
        }
        
        $buyparts_offer_price_model = model('BuypartsOfferPrice');
        $offer_price = $buyparts_offer_price_model->getBuypartByOfferId($offer_id);
        
        if(empty($offer_price)){
            return jsonReturn(NO_RESULT, '当前信息不存在');
        }
        
        if($offer_price['offer_price'] < $price){
            return jsonReturn(WRONG_PARAM, '参数price的值不能大于原来的值');
        }
        
        if($offer_price['buyer_id'] != $this->user_info['uid']){
            return jsonReturn(NO_AUTHORITY, '当前用户不能进行当前操作');
        }
        
        if($offer_price['buypart_status'] != 1){
            return jsonReturn(NO_AUTHORITY, '报价已结束不能进行议价');
        }
        
        $buyparts_negotiated_price_model = model('BuypartsNegotiatedPrice');
        $where = array();
        $where['status'] = 0;
        $where['offer_id'] = $offer_id;
        $negotiated_price_count = $buyparts_negotiated_price_model->getNum($where);
        
        $data = array();
        $data['price'] = $price;
        $data['create_time'] = time();
        if($negotiated_price_count){
            $result = $buyparts_negotiated_price_model->save($data,$where);
        }else{
            $data['offer_id'] = $offer_id;
            $data['uid'] = $this->user_info['uid'];
            $result = $buyparts_negotiated_price_model->save($data);
        }

        if($result !== false){
            //添加系统消息
            $content = sprintf('【报价通知】对方对您的报价进行了议价（%s），价格为%s元，在微信中查看或登录管理后台处理吧！',$offer_price['title'],$price);
            $user_message_model = model('UserMessage');
            $user_message_model->addMessage(array('uid'=>$offer_price['seller_id'],'title'=>'','content'=>$content));
            
            //添加平台消息
            $content = sprintf('【报价通知】有新的议价发布（%s），请持续关注！',$offer_price['title']);
            $sysconf_message_model = model('SysconfMessage');
            $sysconf_message_model->addMessage(array('uid'=>$offer_price['seller_id'],'title'=>'','content'=>$content));
            
            //添加短信发送任务
            if(!empty($offer_price['seller_contact'])){
                $task_data = array(
                    'type' => 'sendSms',
                    'data' => array(
                        'phone'=>$offer_price['seller_contact'],
                        'module_id'=> '',
                        'data' => array()
                    ),
                    'available_at' => time()
                );
            
                $task = new Task();
                $task->addTask($task_data);
            }
            
            //发送客服消息
            postCustomerMessage($offer_price['seller_id'], 10008, array($offer_price['title'],$price,4000883993),'miniprogrampage','');
            
            return jsonReturn(SUCCESSED, '操作成功');
        }
        
        return jsonReturn(BAD_SERVER, '操作失败');
    }
    
    //接受议价
    public function acceptNegotiatedPrice(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $negotiated_id = input('post.id/d',0);
        $buyparts_negotiated_price_model = model('BuypartsNegotiatedPrice');
        $where = array();
        $where['id'] = $negotiated_id;
        $negotiated = $buyparts_negotiated_price_model->getInfo($where);
        
        if(empty($negotiated)){
            return jsonReturn(NO_RESULT, '当前议价不存在');
        }
        
        if($negotiated['status'] !== 0){
            return jsonReturn(NO_AUTHORITY, '当前议价不可操作');
        }

        $buyparts_offer_price_model = model('BuypartsOfferPrice');
        $offer_price = $buyparts_offer_price_model->getInfo(array('offer_id'=>$negotiated['offer_id']),'user_id');
        if($offer_price['user_id'] != $this->user_info['uid']){
            return jsonReturn(NO_AUTHORITY, '当前用户不可操作');
        }

        $negotiated_result = $buyparts_negotiated_price_model->update(array('status'=>1),$where);
        if($negotiated_result !== false){
            $result = $buyparts_offer_price_model->update(array('price'=>$negotiated['price']),array('offer_id'=>$negotiated['offer_id']));
            if($result !== false){
                return jsonReturn(SUCCESSED, '操作成功');
            }else{
                return jsonReturn(BAD_SERVER, '操作失败');
            }
        }else{
            return jsonReturn(BAD_SERVER, '操作失败');
        }
    }
    
    //我的报价列表
    public function getMyOfferPrices(){
        if(empty($this->user_info)){
            return jsonReturn(LOGIN_FAILED, '请先登录，谢谢');
        }
        
        $need_page = input('post.needPage/d',0);
        $page = input('post.page/d',1);

        $buyparts_offer_price_model = model('BuypartsOfferPrice');
        
        $where = array();
        $where['user_id'] = $this->user_info['uid'];
        $buy_ids = $buyparts_offer_price_model->getUndistinctBuyIds($where);
        
        $buyparts = $query_params = array();
        $query_params['page'] = $page;
        
        if(!empty($buy_ids)){
            $buyparts_model = model('Buyparts');

            $buyparts_where = array();
            $buyparts_where['buy_id'] = array('in',$buy_ids);
            
            $need_page = $need_page ? true : false;
            $field = 'buy_id,title,user_id,end_time,status';
            $buyparts = $buyparts_model->getBuyparts($buyparts_where,$field,$need_page,$query_params);
            if($need_page && $buyparts->total() || !empty($buyparts)){
                $new_buyparts = array();
                foreach($buyparts as $buypart){
                    //获取报价数量
                    $offer_price_where = array();
                    $offer_price_where['user_id'] = $this->user_info['uid'];
                    $offer_price_where['buy_id'] = $buypart['buy_id'];
                    $offer_price_where['status'] = array('neq',3);
                    
                    $offer_price_num = $buyparts_model->getOfferPriceNum($offer_price_where);
                    
                    if($offer_price_num > 0){
                     
                        $buypart['offer_price_num'] = $offer_price_num;
                        
                        $buypart['end_time'] = date('Y-m-d H:i',$buypart['end_time']);
                    
                        //获取发布人信息
                        $user_model = model('User');
                        $buyer_info = $user_model->getUserInfoByUid($buypart['user_id'],'nickname,truename,username,phone,headimgurl');
                        $buyer_info['headimgurl'] = !empty($buyer_info['headimgurl']) ? getAttachmentUrl($buyer_info['headimgurl'],true) : '';
                        $buypart['buyer'] = $buyer_info;
    
                        //获取当前登录用户的报价信息
                        $buyparts_offer_price_model = model('BuypartsOfferPrice');
                        $buyparts_negotiated_price_model = model('BuypartsNegotiatedPrice');
                        $buyparts_offer_price_img_model = model('BuypartsOfferPriceImg');
                        
                        $offer_price_field = 'offer_id,add_time,price,content,img_count,status';
                        $offer_prices = $buyparts_offer_price_model->getOfferPrices($offer_price_where,$offer_price_field);
                        if(!empty($offer_prices)){
                            foreach ($offer_prices as &$offer_price){
                                $offer_price['add_time'] = date('Y-m-d H:i',$offer_price['add_time']);
                                $offer_price['status_code'] = $offer_price->getData('status');
                                
                                //获取报价信息图片
                                $offer_price_imgs = array();
                                if($offer_price['img_count']){
                                    $offer_price_imgs = $buyparts_offer_price_img_model->getImgsByBuyId($offer_price['offer_id']);
                                }
                                $offer_price['images'] = $offer_price_imgs;
                                
                                //获取议价信息
                                $offer_price['negotiated_price'] = $buyparts_negotiated_price_model->getPrice(array('offer_id'=>$offer_price['offer_id']));
                            }
                        }
                        
                        $buypart['offer_prices'] = $offer_prices;
                        
                        $new_buyparts[] = $buypart;
                    }
                }
            }
        }
        
        return jsonReturn(SUCCESSED, '获取成功', $new_buyparts);
    }
    
}