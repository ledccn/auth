<?php
namespace PhalApi\Auth;

use PhalApi\Auth\Auth\Domain\Group as DomainAuthGroup;
use PhalApi\Auth\Auth\Domain\Rule as DomainAuthRule;
use PhalApi\Auth\Auth\Domain\User as DomainAuthUser;

class Lite
{
    /**
     * @var boolean $__apiIsServiceWhitelist 是否为白名单服务
     */
    private $__apiIsServiceWhitelist;

    public function __construct()
    {
    }

    /**
     * 检测接口白名单，基于接口维度
     * @return bool
     */
    public function checkApiServiceRights()
    {
        // 缓存返回，避免重复计算
        if ($this->__apiIsServiceWhitelist !== null) {
            return $this->__apiIsServiceWhitelist;
        }

        $this->__apiIsServiceWhitelist = false;

        $di = \PhalApi\DI();
        $api = $di->request->getServiceApi();
        $action = $di->request->getServiceAction();
        $namespace = $di->request->getNamespace();

        // 优先命名空间的单独白名单配置，再到公共白名单配置
        $serviceWhitelist = $di->config->get('app.auth.auth_service_whitelist.' . $namespace);
        $serviceWhitelist = $serviceWhitelist !== null
            ? $serviceWhitelist : $di->config->get('app.auth.auth_service_whitelist', array());

        foreach ($serviceWhitelist as $item) {
            $cfgArr = is_string($item) ? explode('.', $item) : array();
            if (count($cfgArr) < 2) {
                continue;
            }

            // 短路返回
            if ($this->equalOrIngore($api, $cfgArr[0]) && $this->equalOrIngore($action, $cfgArr[1])) {
                $this->__apiIsServiceWhitelist = true;
                break;
            }
        }

        return $this->__apiIsServiceWhitelist;
    }
    /**
     * 相等或忽略
     *
     * @param string $str 等判断的字符串
     * @param string $cfg 规则配置，*号表示通配
     * @return boolean
     */
    private function equalOrIngore($str, $cfg)
    {
        return strcasecmp($str, $cfg) == 0 || $cfg == '*';
    }
    /**
     * 检查权限，操作维度
     * @param name string|array  需要验证的规则列表,支持逗号分隔的权限规则或索引数组
     * @param uid  int           认证用户的id
     * @param relation string    如果为 'or' 表示满足任一条规则即通过验证;如果为 'and'则表示需满足所有规则才能通过验证
     * @return boolean           通过验证返回true;失败返回false
     */
    public function check($name, $uid, $relation = 'or')
    {
        $di = \PhalApi\DI();
        //判断权限检测开关
        if (!$di->config->get('app.auth.auth_on')) {
            return true;
        }
        //判断是不是免检用户
        if (in_array($uid, (array)$di->config->get('app.auth.auth_not_check_user'))) {
            return true;
        }

        //获取用户需要验证的所有有效规则列表
        $authList = $this->getAuthList($uid);
        if (empty($authList)) {
            return false;
        }
        if (is_string($name)) {
            $name = strtolower($name);
            if (strpos($name, ',') !== false) {
                $name = explode(',', $name);
            } else {
                $name = array($name);
            }
        }

        $list = array(); //保存验证通过的规则名
        foreach ($authList as $auth) {
            if (in_array($auth, $name)) {
                $list[] = $auth;
            }
        }

        if ($relation == 'or' and !empty($list)) {
            return true;
        }
        $diff = array_diff($name, $list);
        if ($relation == 'and' and empty($diff)) {
            return true;
        }
        return false;
    }

    /**
     * 根据用户id获取组,返回值为数组
     * @param  int $uid     用户id
     * @return array       用户所属的组
     */
    public function getGroups($uid)
    {
        static $groups = array();
        if (isset($groups[$uid])) {
            return $groups[$uid];
        }
        $groupDomain = new DomainAuthGroup();
        $user_groups = $groupDomain->getUserInGroups($uid);
        $groups[$uid] = $user_groups ? $user_groups : array();
        return $groups[$uid];
    }

    /**
     * 获得权限列表
     * @param integer $uid 用户id
     * @param integer $type
     * @return array
     */
    protected function getAuthList($uid)
    {
        static $_authList = array();    //保存用户验证通过的权限列表

        //读取用户所属组
        $groups = $this->getGroups($uid);
        $ids = array();         //保存用户所属组设置的所有权限规则id
        foreach ($groups as $g) {
            $ids = array_merge($ids, explode(',', trim($g['rules'], ',')));
        }
        $ids = array_unique($ids);
        if (empty($ids)) {
            $_authList[$uid] = array();
            return array();
        }

        $ruleDomain = new DomainAuthRule();
        $rules = $ruleDomain->getRulesInGroups($ids);

        //循环规则，判断结果。
        $authList = array();
        $userDomain = new DomainAuthUser();
        foreach ($rules as $rule) {
            if (!empty($rule['add_condition'])) {           //根据addcondition进行验证
                $user = $userDomain->getUserInfo($uid);     //获取用户信息,一维数组

                $command = preg_replace('/\{(\w*?)\}/', '$user[\'\\1\']', $rule['add_condition']);
                @(eval('$condition=(' . $command . ');'));
                if ($condition) {
                    $authList[] = strtolower($rule['name']);
                }
            } else {
                //只要存在就记录
                $authList[] = strtolower($rule['name']);
            }
        }
        $_authList[$uid] = $authList;
        return $authList;
    }
}
