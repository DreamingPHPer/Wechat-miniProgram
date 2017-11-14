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
        return true;
    }
    
}
