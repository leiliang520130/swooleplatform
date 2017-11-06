<?php
$serverlist_json = array(
	//如果有zero，则从svrurl地址中获得静态文件
	//只要注释了这个，就从本php文件中活动地址了
	//单列一行，就是为了好注释
//	'zero'=> array(),  
	//edition 特殊版本地址，如果有这个版本，先使用这个版本
	'iosaudit:1'=> array(
		1=>array(
			'Name'=>'php外网评审,用户测试',
			'GameLogins'=>array('106.75.84.42:8889',),
			'PlatForms'=>array('iosauditgame.juzhongjoy.com:9088',),
			//'PlatForms'=>array('106.75.7.48:9088',),
			),
		),
	//默认的，最常用的地址
	'default'=> array(
		1=>array(
			'Name'=>'内网服务器-测试:192.168.6.211',
			'GameLogins'=>array('192.168.6.211:8889',),
			'PlatForms'=>array('192.168.6.202:9088',), //需要配置外网的相关端口
			),
		2=>array(
			'Name'=>'冯宝金-个人主机-测试,81',
			'GameLogins'=>array('192.168.6.81:8889',),
			'PlatForms'=>array('192.168.6.202:9088',),
			),
		3=>array(
			'Name'=>'外网测试服:IP待定(现在6.80)',
			'GameLogins'=>array('192.168.6.80:8889',),
			'PlatForms'=>array('192.168.6.202:9088',),
			),
		4=>array(
			'Name'=>'曾令东-内网服务器-测试, 212',
			'GameLogins'=>array('192.168.6.212:8889',),
			'PlatForms'=>array('192.168.6.202:9088',),
			),
		5=>array(
			'Name'=>'曾令东-个人虚拟机-, 167',
			'GameLogins'=>array('192.168.6.167:8889',),
			'PlatForms'=>array('192.168.6.202:9088',),
			),
		),
	);
