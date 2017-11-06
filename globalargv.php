<?PHP
//online时间计算间隔
define('ONLINEINTERVAL', 300);
//游戏的零点时间, 5小时
define('GAMEZERO', 5);
//$glargv,全局变量数组
$glargv = array(
    'myname' => 'noname',
    'this'=> 0,     //对象自己
    'serv' => 0,    //服务程序自己
    'corecon' =>0, 	//到coresvr的链接
    'mysqlcon'=>0,  //只有authsvr会使用
    'rediscon'=>0,  //所有服务器都会连接
    'svrid'=>0,     //服务器自己的编号,authsvr为9, gamesvr从0开始.目前那注意不要超过4 (7是rolesvr用了, 6给了citysvr(它侦听了两个端口,5也用了), 8预留给mangsvr)
    );

$glargv['zonetype'] = $glconf['zonetype'];
$glargv['zoneid'] = $glconf['zoneid'];

$glargv['timeoffset'] = date_offset_get(new DateTime());
$glargv['svrmainver'] = '0.0.0.';
