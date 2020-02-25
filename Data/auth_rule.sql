-- ----------------------------
-- auth_rule 规则表
-- id:主键，name：规则唯一标识, title：规则中文名称 status 状态：为1正常，为0禁用，add_condition：规则表达式，为空表示存在就验证，不为空表示按照条件验证
-- ----------------------------
CREATE TABLE `auth_rule` (
    `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
    `name` char(80) NOT NULL DEFAULT '',
    `title` char(20) NOT NULL DEFAULT '',
    `status` tinyint(1) NOT NULL DEFAULT '1',
    `add_condition` char(100) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
