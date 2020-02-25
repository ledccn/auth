<?php
namespace PhalApi\Auth\Auth\Api;

use PhalApi\Api;
use PhalApi\Auth\Auth\Domain\Group as DomainAuthGroup;

/**
 * 组接口服务类
 * @author: hms 2015-6-8
 */
class Group extends Api
{
    private static $Domain = null;

    public function __construct()
    {
        \PhalApi\Translator::addMessage(API_ROOT.'/vendor/ledccn/auth');
        if (self::$Domain == null) {
            self::$Domain = new DomainAuthGroup();
        }
    }

    public function getRules()
    {
        return array(
            'getList' => array(
                'keyWord' => array('name' => 'keyword', 'type' => 'string', 'default' => '', 'desc' => '关键词'),
                'field' => array('name' => 'field', 'type' => 'string', 'default' => '*', 'desc' => '返回字段'),
                'limitPage' => array('name' => 'limit_page', 'type' => 'int', 'default' => '0', 'desc' => '分页页码'),
                'limitCount' => array('name' => 'limit_count', 'type' => 'int', 'default' => '20', 'desc' => '单页记录条数，默认为20'),
                'order' => array('name' => 'order', 'type' => 'string', 'default' => '', 'desc' => '排序参数，如：xx ASC,xx DESC')
            ),
            'getInfo' => array(
                'id' => array('name' => 'id', 'type' => 'int', 'require' => true, 'min' => 1, 'desc' => '组id')
            ),
            'add' => array(
                'title' => array('name' => 'title', 'type' => 'string', 'require' => true, 'desc' => '组名称'),
                'status' => array('name' => 'status', 'type' => 'int', 'default' => 1, 'desc' => '状态，1.正常，0.禁用')
            ),
            'edit' => array(
                'id' => array('name' => 'id', 'type' => 'int', 'require' => true, 'min' => 1, 'desc' => '需要修改的组id'),
                'title' => array('name' => 'title', 'type' => 'string', 'require' => true, 'desc' => '组名称'),
                'status' => array('name' => 'status', 'type' => 'int', 'desc' => '状态，1.正常，0.禁用')
            ),
            'del' => array(
                'ids' => array('name' => 'ids', 'type' => 'string', 'require' => true, 'min' => 1, 'desc' => '组id，逗号隔开多个')
            ),
            'setRules' => array(
                'id' => array('name' => 'id', 'type' => 'int', 'require' => true, 'min' => 1, 'desc' => '组id'),
                'rules' => array('name' => 'rules', 'type' => 'string', 'default' => '', 'desc' => '规则id，逗号隔开多个')
            ),
            'assUser' => array(
                'uid' => array('name' => 'uid', 'type' => 'int', 'require' => true, 'min' => 1, 'desc' => '用户id'),
                'group_id' => array('name' => 'gid', 'type' => 'string', 'default' => '', 'desc' => '组id，逗号隔开多个')
            )
        );
    }

    /**
     * 获取组列表
     * @desc 获取所有用户组列表
     * @return int code 业务代码
     * @return object info 组信息对象
     * @return object info.items 组数据行
     * @return int info.count 数据总数，用于分页
     * @return string msg 业务消息
     */
    public function getList()
    {
        $rs = array('code' => 0, 'info' => array(), 'msg' => '');
        $rs['info'] = self::$Domain->getGroupList($this);
        return $rs;
    }

    /**
     * 获取单个组信息
     * @desc 根据id查询组信息
     * @return int code 业务代码：0.获取成功，1.获取失败
     * @return object info 组信息对象,获取失败为空
     * @return string msg 业务消息
     */
    public function getInfo()
    {
        $rs = array('code' => 0, 'info' => array(), 'msg' => '');
        $r = self::$Domain->getGroupOne($this->id);
        if (is_array($r)) {
            $rs['info'] = $r;
        } else {
            $rs['code'] = 1;
            $rs['msg'] = \PhalApi\T('data get failed');
        }
        return $rs;
    }

    /**
     * 创建组
     * @desc 创建一个新用户组
     * @return int code 业务代码：0.操作成功，1.操作失败，2.组名重复
     * @return string msg 业务消息
     */
    public function add()
    {
        $rs = array('code' => 0, 'msg' => '');
        $r = self::$Domain->addGroup($this);
        if ($r == 0) {
            $rs['msg'] = \PhalApi\T('success');
        } elseif ($r == 1) {
            $rs['msg'] = \PhalApi\T('failed');
        } elseif ($r == 2) {
            $rs['msg'] = \PhalApi\T('group name repeat');
        }
        $rs['code'] = $r;
        return $rs;
    }

    /**
     * 修改组
     * @desc 修改一个用户组
     * @return int code 业务代码：0.操作成功，1.操作失败，2.组名重复
     * @return string msg 业务消息
     */
    public function edit()
    {
        $rs = array('code' => 0, 'msg' => '');
        $r = self::$Domain->editGroup($this);
        if ($r == 0) {
            $rs['msg'] = \PhalApi\T('success');
        } elseif ($r == 1) {
            $rs['msg'] = \PhalApi\T('failed');
        } elseif ($r == 2) {
            $rs['msg'] = \PhalApi\T('group name repeat');
        }
        $rs['code'] = $r;
        return $rs;
    }

    /**
     * 删除组
     * @desc 删除用户组，支持批量删除
     * @return int code 业务代码：0.操作成功，1.操作失败
     * @return string msg 业务消息
     */
    public function del()
    {
        $rs = array('code' => 0, 'msg' => '');
        $r = self::$Domain->delGroup($this->ids);
        if ($r == 0) {
            $rs['msg'] = \PhalApi\T('success');
        } else {
            $rs['msg'] = \PhalApi\T('failed');
        }
        $rs['code'] = $r;
        return $rs;
    }

    /**
     * 设置规则
     * @desc 设置用户的规则
     * @return int code 业务代码：0.操作成功，1.操作失败
     * @return string 业务消息
     */
    public function setRules()
    {
        $rs = array('code' => 0, 'msg' => '');
        $r = self::$Domain->setRules($this->id, $this->rules);
        if ($r == 0) {
            $rs['msg'] = \PhalApi\T('success');
        } else {
            $rs['msg'] = \PhalApi\T('failed');
        }
        $rs['code'] = $r;
        return $rs;
    }

    /**
     * 组关联用户
     * @desc 添加用户到指定组
     * @return int code 业务代码：0.操作成功，1.操作失败
     * @return string 业务消息
     */
    public function assUser()
    {
        $rs = array('code' => 0, 'msg' => '');
        $r = self::$Domain->assUser($this);
        if ($r == 0) {
            $rs['msg'] = \PhalApi\T('success');
        } else {
            $rs['msg'] = \PhalApi\T('failed');
        }
        $rs['code'] = $r;
        return $rs;
    }
}
