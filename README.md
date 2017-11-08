Wechat-miniProgram
===============

随着微信的普及，微信公众号和小程序的开发，成了很多人工作之一。虽然不同的项目关注的功能不一样，但是基本的开发流程几乎一致。
比如，对接微信服务器，调用微信接口，获取用户在微信中的信息等等。为让相关工作人员能将更多的精力集中在差异化的功能开发上，现开源以下代码。
本代码基于ThinkPHP5开发，集成了微信公众号开发、小程序开发等模块，包括微信服务器的验证、消息的推送、事件的响应以及定时任务、oauth2接口验证等等。
本代码开源旨在交流学习，共同进步，有不足之处，希望大家批评指出！

## 目录结构

初始的目录结构如下：

~~~
www  WEB部署目录（或者子目录）
├─application           应用目录
│  ├─common             公共模块目录
│  │  ├─controller      控制器目录
│  │  ├─model           模型目录
│  │  └─ ...            更多类库目录
│  ├─api                api接口模块
│  │  ├─controller      控制器目录
│  │  ├─model           模型目录
│  │  ├─validate        验证类目录
│  │  └─ ...            更多类库目录
│  ├─oauth              oauth2模块
│  │  ├─controller      控制器目录
│  │  └─ ...            更多类库目录
│  ├─extra              拓展配置目录
│  │
│  ├─command.php        命令行工具配置文件
│  ├─common.php         公共函数文件
│  ├─config.php         公共配置文件
│  ├─route.php          路由配置文件
│  ├─tags.php           应用行为扩展定义文件
│  └─database.php       数据库配置文件
│
├─public                WEB目录（对外访问目录）
│  ├─index.php          入口文件
│  ├─router.php         快速测试文件
│  └─.htaccess          用于apache的重写
│
├─thinkphp              框架系统目录
│  ├─lang               语言文件目录
│  ├─library            框架类库目录
│  │  ├─think           Think类库包目录
│  │  └─traits          系统Trait目录
│  │
│  ├─tpl                系统模板目录
│  ├─base.php           基础定义文件
│  ├─console.php        控制台入口文件
│  ├─convention.php     框架惯例配置文件
│  ├─helper.php         助手函数文件
│  ├─phpunit.xml        phpunit配置文件
│  └─start.php          框架入口文件
│
├─extend                扩展类库目录
│  ├─tool               工具类库
│  └─wechat         	微信服务器验证、响应目录
├─runtime               应用的运行时目录（可写，可定制）
├─vendor                第三方类库目录（Composer依赖库）
├─build.php             自动生成定义文件（参考）
├─composer.json         composer 定义文件
├─LICENSE.txt           授权说明文件
├─README.md             README 文件
└─think                 命令行入口文件

~~~
