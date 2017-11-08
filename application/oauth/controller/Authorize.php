<?php
namespace app\oauth\controller;

//验证控制类
class Authorize extends Base{
    public function index(){
        $server = $this->server;
        
        //获取请求
        $request = \OAuth2\Request::createFromGlobals();
        $response = new \OAuth2\Response();
        
        // 检测验证请求
        if (!$server->validateAuthorizeRequest($request, $response)) {
            $response->send();
            die;
        }
        
        // 展示验证输入框
        if (empty($_POST)) {
          exit('
            <form method="post">
              <label>Do You Authorize TestClient?</label><br />
              <input type="submit" name="authorized" value="yes">
              <input type="submit" name="authorized" value="no">
            </form>');
        }
        
        // print the authorization code if the user has authorized your client
        $is_authorized = ($_POST['authorized'] === 'yes');
        $server->handleAuthorizeRequest($request, $response, $is_authorized);
        if ($is_authorized) {
          // this is only here so that you get to see your code in the cURL request. Otherwise, we'd redirect back to the client
          $code = substr($response->getHttpHeader('Location'), strpos($response->getHttpHeader('Location'), 'code=')+5, 40);
          exit("SUCCESS! Authorization Code: $code");
        }
        
        $response->send();
        
    }
}