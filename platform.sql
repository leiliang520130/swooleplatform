-- Adminer 4.3.1 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

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
  `recommrid` int(11) NOT NULL DEFAULT '0' COMMENT '玩家之间的推荐关系,被推荐玩家的游戏ID,推荐字段',
  `isblock` int(11) NOT NULL DEFAULT '0' COMMENT '帐号封禁止',
  `agencyrid` int(11) NOT NULL DEFAULT '0' COMMENT '被代理推荐值得,推荐代理的游戏id',
  `agencylevel` int(11) NOT NULL DEFAULT '0' COMMENT '如果是0表示自己,如果大于0表示代理,暂定0和1值',
  PRIMARY KEY (`uid`),
  UNIQUE KEY `idx_username` (`username`) USING BTREE,
  UNIQUE KEY `idx_mobilrandid` (`guestmobileid`) USING BTREE,
  KEY `idx_bindmobil` (`bindmobilenum`) USING BTREE,
  KEY `idx_regmobileid` (`regmobileid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `user` (`uid`, `authtype`, `username`, `cpass`, `saltpass`, `guestmobileid`, `regmobileid`, `bindmobilenum`, `createtime`, `recommrid`, `isblock`, `agencyrid`, `agencylevel`) VALUES
(5,	2,	'333333333',	'9c40f1eac2a8c601e4fef5c841a544f7',	'819fe330a19a38aafdee3975ffe45e08',	'*5',	'1234567897',	'',	1508331338,	0,	0,	0,	0),
(7,	2,	'233333333',	'7209d2d1752d5671e17035367b4a2c8b',	'22cf0c9348780186413bc9c63aa990e9',	'*7',	'2234567897',	'',	1508393776,	0,	0,	0,	0),
(8,	2,	'4443333333',	'9497fa946d7884563f8b666fdaa53bef',	'30e83876d571a1efb877762965e3a403',	'*8',	'33334567897',	'',	1508394155,	0,	0,	0,	0),
(10,	2,	'55553333333',	'5ae982955e54d047f78436a256088bb5',	'25f28f7584c38747e8c9d06d4a771593',	'*10',	'55554567897',	'',	1508811888,	0,	0,	0,	0),
(12,	2,	'66663333333',	'624d9b1faf10cb1bfac98408cb2cdec8',	'82db23f27316e3c3e8f055dd6da6e2ef',	'*12',	'66664567897',	'',	1508833527,	0,	0,	10001,	0),
(19,	2,	'oGlFC00m0UEFDgGLjXjRqTjGPzEg',	'3df23b85499508accff410dfa2a4bbf4',	'71401c42b3a6278701918c6c48294d23',	'*19',	'0',	'',	1508984500,	0,	0,	10001,	0),
(20,	2,	'oGlFC09bQ3wdQ47VRXjghePxNZnw',	'4dd32bfde086b4bcded69953145baa52',	'31c571269a54e6b2b09a849ce0756ec9',	'*20',	'0',	'',	1508994580,	0,	0,	10001,	0),
(22,	2,	'oGlFC00yc7eOsz6aR7sRJnbj-PhQ',	'025593f7d10cfbf9624012ba6f45a440',	'94f4a49cebaf08d8cfddf2c966e0f24a',	'*22',	'0',	'',	1509011810,	0,	0,	10001,	0),
(23,	2,	'oGlFC03FY9ZAddNbQu_gjhbYic00',	'9f8c4d84602d7af34b5dc3136bc65d6a',	'6ba5bc61d698f16ea0a4f81950e0a281',	'*23',	'0',	'',	1509016751,	0,	0,	10001,	0);

-- 2017-11-02 09:59:49