/*
Navicat MySQL Data Transfer

Source Server         : localhost_3306
Source Server Version : 50553
Source Host           : localhost:3306
Source Database       : platform

Target Server Type    : MYSQL
Target Server Version : 50553
File Encoding         : 65001

Date: 2017-10-18 19:25:52
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for `user`
-- ----------------------------
DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `uid` int(11) NOT NULL AUTO_INCREMENT COMMENT 'user 的唯一id',
  `authtype` int(11) NOT NULL DEFAULT '1' COMMENT '帐号类型,来自哪里,我们自己的是1 (2是微信)',
  `username` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '' COMMENT '用户名(uninid)',
  `cpass` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '' COMMENT '这里是客户端穿上来的md5的密码, (真实用户不存储)//拖库问题',
  `saltpass` varchar(32) NOT NULL DEFAULT '' COMMENT '加密后的密码(md5(uname+md5(upass))',
  `guestmobileid` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '' COMMENT '游客注册时是唯一码,如果是正式注册,或者绑定, 则填入*+uid',
  `regmobileid` varchar(32) NOT NULL DEFAULT '' COMMENT '唯一设备号, 为了数据统计, 如果是游客注册, 则和guestmobileid相等,永远不会变',
  `bindmobilenum` varchar(16) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '' COMMENT '绑定的手机号(保留字段)',
  `createtime` int(11) NOT NULL DEFAULT '0' COMMENT '注册时间',
  `recommrid` int(11) NOT NULL DEFAULT '0' COMMENT '(代理ID推荐人角色id  4位代理id  6位角色id)//push给服务器...推荐人的角色id, rid, 客户端注册使用.',
  `isblock` int(11) DEFAULT '0' COMMENT '帐号封禁止',
  `gameid` int(11) NOT NULL DEFAULT '1' COMMENT '注册游戏的id',
  PRIMARY KEY (`uid`),
  UNIQUE KEY `idx_username` (`username`) USING BTREE,
  UNIQUE KEY `idx_mobilrandid` (`guestmobileid`) USING BTREE,
  KEY `idx_bindmobil` (`bindmobilenum`) USING BTREE,
  KEY `idx_regmobileid` (`regmobileid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of user
-- ----------------------------
