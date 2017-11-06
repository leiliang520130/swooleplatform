<?php  
$version_txt = array(
	//如果有zero，则从resurl地址中获得静态文件
	//只要注释了这个，就从本php文件中活动地址了
	//单列一行，就是为了好注释
	'zero'=> array(),  
	//edition 特殊版本地址，如果有这个版本，先使用这个版本
	'default'=> array(
		'ios'=>array(
			'core'=>'1.13',
			'version'=>'0.1.13.7401'),
		'android'=>array(
			'core'=>'1.13',
			'version'=>'0.1.13.7401'),
		'other'=>array(
			'core'=>'1.13',
			'version'=>'0.1.13.7401'),
		),
	);
