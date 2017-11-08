<?php
/**
 * Code By Safe3
 */

/*
//定义错误处理函数
function customError($errno, $errstr, $errfile, $errline)
 {
     echo "<b>Error number:</b> [$errno],error on line $errline in $errfile<br />" ;
     die();
}

//注册错误处理函数
set_error_handler("customError",E_ERROR);
*/

//定义过滤规则
$getfilter="'|(and|or)\\b.+?(>|<|=|in|like)|\\/\\*.+?\\*\\/|<\\s*script\\b|\\bEXEC\\b|UNION.+?Select|Update.+?SET|Insert\\s+INTO.+?VALUES|(Select|Delete).+?FROM|(Create|Alter|Drop|TRUNCATE)\\s+(TABLE|DATABASE)" ;
$postfilter="\\b(and|or)\\b.{1,6}?(=|>|<|\\bin\\b|\\blike\\b)|\\/\\*.+?\\*\\/|<\\s*script\\b|\\bEXEC\\b|UNION.+?Select|Update.+?SET|Insert\\s+INTO.+?VALUES|(Select|Delete).+?FROM|(Create|Alter|Drop|TRUNCATE)\\s+(TABLE|DATABASE)" ;
$cookiefilter="\\b(and|or)\\b.{1,6}?(=|>|<|\\bin\\b|\\blike\\b)|\\/\\*.+?\\*\\/|<\\s*script\\b|\\bEXEC\\b|UNION.+?Select|Update.+?SET|Insert\\s+INTO.+?VALUES|(Select|Delete).+?FROM|(Create|Alter|Drop|TRUNCATE)\\s+(TABLE|DATABASE)" ;

//xss攻击阻断函数
function StopAttack($StrFiltKey,$StrFiltValue,$ArrFiltReq){
     
    if(is_array($StrFiltValue))
    {
        $StrFiltValue=implode($StrFiltValue);
    }
    if (preg_match("/".$ArrFiltReq."/is",$StrFiltValue)==1){
        slog("<br><br>操作IP: ".get_client_ip(0,true)."<br>操作时间: ".strftime("%Y-%m-%d %H:%M:%S")."<br>操作页面:".$_SERVER["PHP_SELF"]."<br>提交方式: ".$_SERVER["REQUEST_METHOD"]."<br>提交参数: ".$StrFiltKey."<br>提交数据: ".$StrFiltValue);
        print "<div style='text-align:center;margin-top:100px;'>很抱歉，由于您访问的URL有可能对网站造成安全威胁，您的访问被阻断。</div>" ;
        exit();
    }
}

//$ArrPGC=array_merge($_GET,$_POST,$_COOKIE);
//监测get请求
if(!empty($_GET)){
    foreach($_GET as $key=>$value){
        StopAttack($key,$value,$getfilter);
    }
}

//监测post请求
/* if(!empty($_POST)){
    foreach($_POST as $key=>$value){
        StopAttack($key,$value,$postfilter);
    }
} */

//监测cookie请求
if(!empty($_COOKIE)){
    foreach($_COOKIE as $key=>$value){
        StopAttack($key,$value,$cookiefilter);
    }
}

//监测来源
$referer=empty($_SERVER['HTTP_REFERER']) ? array() : array($_SERVER['HTTP_REFERER']);
if(!empty($referer)){
    foreach($referer as $key=>$value){
        StopAttack($key,$value,$cookiefilter);
    }
}

/*
 if (file_exists('update360.php')) {
 echo "请重命名文件update360.php，防止黑客利用<br/>";
 die();
 }
 */

//将错误写入系统日志
function slog($logs)
{
    $toppath= LOG_PATH . date('Ym') . DS . "attack_" . date('d') . '.log';
    $Ts=fopen($toppath,"a+");
    fputs($Ts,$logs."\r\n");
    fclose($Ts);
}

/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv 是否进行高级模式获取（有可能被伪装） 
 * @return mixed
 */
function get_client_ip($type = 0,$adv=false) {
    $type       =  $type ? 1 : 0;
    static $ip  =   NULL;
    if ($ip !== NULL) return $ip[$type];
    if($adv){
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr    =   explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos    =   array_search('unknown',$arr);
            if(false !== $pos) unset($arr[$pos]);
            $ip     =   trim($arr[0]);
        }elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip     =   $_SERVER['HTTP_CLIENT_IP'];
        }elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip     =   $_SERVER['REMOTE_ADDR'];
        }
    }elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip     =   $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u",ip2long($ip));
    $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
}