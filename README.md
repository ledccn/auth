
# 基于PhalApi的Auth权限扩展


## 描述

基于PhalApi2的Auth扩展，从仓库拉取https://github.com/twodayw/auth.git后，修复适配。
截止2020年2月23日，可以用于PhalApi的最新版本为：

PhalApi Pro 专业版 v1.2.0

PhalApi 开源版 v2.10.1

项目GitHub地址:[https://github.com/ledccn/auth.git](https://github.com/ledccn/auth.git "项目Git地址")


## 安装PhalApi-Auth权限扩展

在项目的composer.json文件中，添加：

```json
{
    "require": {
        "ledccn/auth": "dev-master"
    },
    "autoload": {
        "psr-4": {
            "PhalApi\\Auth\\Auth\\": "vendor/ledccn/auth/src/Auth"
        }
    }
}
```

配置好后，执行`composer update`更新操作即可。

## 使用方法

首先要说明一个问题，有很多同学都会对Auth和OAuth这名称特别相似的东西傻傻分不清楚，但实际这是两个概念，Auth扩展指的是实现了基于用户与组的权限认证功能，与RBAC权限认证类似，主要用于对服务级别的功能进行权限控制，而OAuth，大概可以理解为接口签名认证。

如果有开发网站后台或者管理系统经验的同学应该明白权限认证的重要性，所以基于这个情况，我就写了这个扩展，当然，这个扩展是移植于TP的Auth类，并做了相关的优化，也提供了相关操作的Api接口， 并没有什么技术性，希望能帮助并方便到大家。

我并不是一个专业的phper，只是出自于自己对这份事情的爱好，所以在编码的规范，代码的使用上或许存在不少的弊端，也希望赏脸用了这个扩展类的同学，多提宝贵意见。让我不断地进步。

## 了解Auth权限认证
想要详细了解Auth权限认证的思路，请移步 [比RBAC更好的权限认证方式（Auth类认证）](http://www.thinkphp.cn/topic/4029.html)。
在此我就不过多地对Auht本身进行说明了。

### （1）、数据库表导入：
要使用Auth扩展，必须先导入相关的数据表，需要导入以下表：
```bash
$ cd ./vendor/ledccn/auth
$ tree

├── Data
│   ├── auth_group.sql
│   ├── auth_rule.sql
│   ├── auth_group_access.sql
```
> 导入前，可以自行调整表的前缀。

> **特别注意：** 要实现Auth权限认证，数据库中必须存在User表，用于存放用户信息，但User表是根据项目需求自主创建的，只要存在ID主键即可。

### （2）、项目配置：
将 `./vendor/ledccn/auth/Config/app.php` 里面的配置拷贝到你的项目配置：
```php
return array(
    //请将以下配置拷贝到 ./Config/app.php 文件对应的位置中
    'auth' => array(
        'auth_on' => true, // 认证开关
        'auth_user' => 'user', // 用户信息表,
        'auth_group' => 'auth_group', // 组数据表名
        'auth_group_access' => 'auth_group_access', // 用户-组关系表
        'auth_rule' => 'auth_rule', // 权限规则表
        'auth_not_check_user' => array(1) //跳过权限检测的用户
    )
);
```
### （3）、入口注册：
```php
// 必须显式注册，以便可以让服务自行初始化
// Auth权限扩展
$di->authLite = new \PhalApi\Auth\Lite();
```

## 入门使用
经过前面的配置，马上就可以实现权限认证功能了。
### （1）、用户登录
Auth是基于用户和组的认证方式，所以在认证之前，首先要实现用户登录，登录成功后，接口访问地址必须带上UserID参数，
至于登录过程由各位同学自行实现，此处不做描述。
### （2）、权限检测
Auth权限认证使用非常简单，权限检测操作建议放在接口自定义签名认证的函数里面的，示例如下：
```php
<?php
namespace App\Common;
use App\Domain\Users as DomainUsers;
use PhalApi\Exception\BadRequestException;
/**
 * 过滤器接口
 * Class SignFilter
 * @package App\Common
 */
class SignFilter extends Filter
{
    public function check()
    {
        $di = \PhalApi\DI();

        // 前置检测：判断是否可使用后台API
        $this->checkAdminOrNot();

        // 核心检测：基于爱语飞飞token
        $token = $di->request->get('sign');
        $DomainUsers = new DomainUsers();
        $uid = $DomainUsers->checkSign($token);

        // 刷新上下文信息
        $di->context = new Context($token, $uid);

        // 后置判断：Auth权限检测
        $this->checkAuth();

        // 后置判断：检测用户状态
        $this->checkUserActive();
    }

    /**
     * Auth权限检测
     * @throws BadRequestException
     */
    protected function checkAuth()
    {
        $di = \PhalApi\DI();
        $appKey = $di->context->getAppKey();
        $service = $di->request->getService();
        $uid = $di->context->getUid();

        // 检测基础服务白名单
        if($di->authLite->checkApiServiceRights()){
            return true;
        }
        // 检测服务权限
        $r = $di->authLite->check($service, $uid);
        if (!$r) {
            throw new BadRequestException('用户Auth权限不足', 7);
        }
    }
```

给项目增加了权限检测的代码之后，访问接口，通常会抛出异常：
```
//访问地址
127.0.0.1/PhalApi/Public/Dome/&user_id=1;
```
```
//异常
{code:401,data:null,msg:"没有接口访问权限"}
```
此时抛出的异常是正常的，因为数据表里面并没有定义相关的规则，也没有创建相关的组和关联，所以下面的操作，才是关键。

### （3）、数据库操作：
#### （3.1）创建组：
```mysql
INSERT INTO `phalapi`.`phalapi_auth_group` (`id`, `title`, `status`, `rules`)
 VALUES (NULL, '超级管理员', '1', '');
```
![输入图片说明](http://git.oschina.net/uploads/images/2015/0820/170627_12df7af2_377287.jpeg "在这里输入图片标题")

### （3.2）用户与组关联：
```mysql
INSERT INTO `phalapi`.`phalapi_auth_group_access` (`uid`, `group_id`) VALUES ('1', '1');
```
![输入图片说明](http://git.oschina.net/uploads/images/2015/0820/170938_77e82702_377287.jpeg "在这里输入图片标题")
> **注意：** 一个用户可以关联多个组

### （3.3）创建规则：
```mysql
INSERT INTO `phalapi`.`phalapi_auth_rule` (`id`, `name`, `title`, `status`, `add_condition`) VALUES (NULL, 'Default.Index', '默认接口', '1', ''); 
```
![输入图片说明](http://git.oschina.net/uploads/images/2015/0820/172154_41168797_377287.jpeg "在这里输入图片标题")

对于规则，需要做一下说明，通常做权限认证就是对访问Url的认证，

RBAC的权限认证方式，是通过在数据库建立节点，模块/控制器/方法，然后在检测的时候获取url里面的指定参数，如：M=dome&a=Default&c=Index，跟数据库的数据做对比，如果节点存在，则通过认证，

Auth的规则实现更加简单，直接在规则表的name字段加入接口地址“Default.Index”即可，name字段存储的正是url的service参数。
###（3.4）组关联规则：
```mysql
UPDATE `phalapi`.`phalapi_auth_group` SET `rules` = '1' WHERE `phalapi_auth_group`.`id` = 1;
```
![输入图片说明](http://git.oschina.net/uploads/images/2015/0820/175120_42f293a5_377287.jpeg "在这里输入图片标题")
更新组表的rules字段，将规则id加入该字段。

### （3.5）完成
经过上面的数据库操作之后，再访问刚才的连接，就不会抛出异常了，如果访问别的接口，还是会抛出异常，下面要做的就是往数据库里不断更新规则了，
当然，如果所有的操作都要想上面去操作数据库的话，肯定是不合理的，所以Auth扩展也提供了，所有操作的Api接口：
```sh
$ cd /Library/Auth
$ tree

├── Auth
│   ├── Api
│   │    ├──Group.php
│   │    ├──Rule.php
│   ├── Domain
│   ├── Model
```
当然，您也可以自己编写接口来实现这些功能。

## 其他说明

### （1）、免检用户
如果您需要保留一个或多个用户可以不经过权限检测，可以访问所有接口，您可以在配置中加入免检用户ID：
```php
'auth_not_check_user' => array(1，2) //跳过权限检测的用户
```

### （2）、多数据库支持
如果您的用户表与auth的3个表不在同一个数据库，请编辑Model/User::getNotORM()方法，返回您的数据库实例即可！
```php
protected function getNotORM()
{
    // 支持多数据库
    return \PhalApi\DI()->notorm_reseed;
}
```

**如果大家有更好的建议可以私聊或加入到PhalApi大家庭中前来一同维护PhalApi**
**注:笔者能力有限有说的不对的地方希望大家能够指出,也希望多多交流!**