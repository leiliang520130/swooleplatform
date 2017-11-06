<?PHP
//$glconf,全局配置
$glconf = array(
    'gamename' => 'platform',
    'timezone' => 'Asia/Shanghai', //time 运行在那个时区,好计算同一天
    'zonetype' => 0,  //demo
    'zoneid' => 0,
    'mysqlsvr'=>array(
        'host'=>'127.0.0.1',
        'port'=>3306,
        'user'=>'root',
        'pass'=>'123456',
        'dbname'=>'platform',
        ),
    'redissvr'=>array(
        'host' => '127.0.0.1',
        'port' => 6379,
        'dbnum'=>0,     //使用第几个数据库
        ),
    'coresvr'=>array(
        //------这个地方用不上来
        'exthost' => '127.0.0.1',   //外网端口, websocket的端口
        'extport' => 9099,
        'inthost' => '127.0.0.1',   //内网端口
        'intport' => 9098,
        'mgrsalt' => 'mgrsalt',
        ),
    'wsgatesvr'=>array(
        'exthost' => '127.0.0.1',   //外网端口, websocket的端口
        'extport' => 9088,
        ),
    's2spacktype'=>1, //服务器和服务器之间包协议序列化方法
    'gameredissvr'=>array(
        0=>array(
            'host' => '127.0.0.1',
            'port' => 6379,
            'dbnum'=>5,     //使用第几个数据库
            ),
        ),
    );
date_default_timezone_set($glconf['timezone']);
$glconf['stopwatch'] = 1; //秒表, 服务器的每个请求记录时间
