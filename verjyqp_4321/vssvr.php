<?php
/*
    返回客户端不同的版本的更新，下载地址，以及版本控制，登录入口地址
    上层默认目录名 verGAMENAME_PORT，GAMENAME：不同游戏名，PORT，使用的端口
    目录名定义了，就当配置来用
*/
include('verlist.php');   //不同客户端的主入口地址和相关信息
include('notice.php');    //每个服公告
include('svrenter.php');  //服务器入口，原来 svrList.json 文件,如果为空，则从svrulr中获得（兼容考虑）
// include('version.php');   //不同客户端的版本号,如果为空，则从ulr中获得（兼容考虑）

function  slog($str, $level=0)
{
    echo 'slog@' . date('m-d-H:i:s') . ' > ' . $str . PHP_EOL;
}

function renameporcess($name)
{
    global $argv;
    global $gamename;
    global $port;

    $name = str_replace('php ', "php ver_$gamename:$port ", $name);
    if(PHP_OS!='Darwin')
        cli_set_process_title($name);
}

$glargv = array();
define('DEBUG', 1);

class BaseWebSvr
{
    public $host;
    public $port;
    public $setargv;

    function __construct($host, $port, $setargv)
    {
        $this->host = $host;
        $this->port = $port;
        $this->setargv = $setargv;
    }

    public function run_pre()
    {
        set_error_handler(array($this, 'error_handler'));

        $serv = new swoole_http_server($this->host, $this->port);
        $serv->set($this->setargv);
        $serv->on('Start', array($this, 'onStart'));
        $serv->on('ManagerStart', array($this, 'onManagerStart'));
        $serv->on('WorkerStart', array($this, 'onWorkerStart'));
        $serv->on('WorkerError', array($this, 'onWorkerError'));
        $serv->on('Request', array($this, 'onRequest'));
        return $serv;
    }

    function run()
    {
        $serv = $this->run_pre();
        $serv->start();
    }

    public function onStart($serv)
    {
        slog('PHP Version: ' . PHP_VERSION );
        slog('Swoole Version: ' . SWOOLE_VERSION );
        $va = explode('.', SWOOLE_VERSION);
        $ver = $va[0] + $va[1]/10.0;
        if($ver < 1.7)
        {
                print('swoole version is too lower');
                $serv->shutdown();
        }
        //用上级子目录来重命名进程，方便管理
        renameporcess("php Master_pid={$serv->master_pid}");
    }

    public function onManagerStart($serv)
    {
        renameporcess("php Manager_pid={$serv->manager_pid}");
    }

    public function onWorkerError(swoole_server $serv, $worker_id, $worker_pid, $exit_code)
    {
        slog("worker error exit. WorkerId=$worker_id|Pid=$worker_pid|ExitCode=$exit_code");
        $start_fd = 0;
        while(true)
        {
            $conn_list = $serv->connection_list($start_fd, 10);
            var_dump($conn_list);
            if($conn_list===false)
                break;
            $start_fd = end($conn_list);
            foreach($conn_list as $fd)
                $serv->close($fd);
        }
    }

    public function error_handler($errno, $errstr, $errfile, $errline)
    {
        slog('$errno:' . var_export($errno, true));
        slog('$errstr:' . var_export($errstr, true));
        slog('$errfile:' . var_export($errfile, true));
        slog('$errline:' . var_export($errline, true));
    }

    public function onWorkerStart($serv, $worker_id)
    {
        renameporcess("php worker $serv->worker_id");
    }


    public function onRequest($request, $response)
    {
        // if($request->server['request_uri'] == '/favicon.ico')
        // {
        //  $response->status(404);
        //  $response->end('404');
        // }
        $result = $this->getresult($request);
        $response->header('Connection', 'Close');
        if($result === -1)
        {
            $response->status(404);
            $response->end('404');
        }
        else
            $response->end($result);
    }

    private function getresult($request)
    {
        global $notice;
        global $verlist;
        global $iosaudit_ver;
        global $version_txt;
        global $serverlist_json;
        global $gamename;
	global $shenhe_vers;

        $result = array();
        //svrlistnew, 这块会比较复杂, 客户端会传5个参数上来
        /*
        edition : 客户端类型( gamename, 官网用的, gamenamedev, 开发用的, iosaudit: iso审核的)
        clientver: 版本数字编号
        channel: 是给游戏服务器用的, 目前只有1:ios, 2,android用的
        platform: 1: 聚众
        regfrom: 从哪里注册来的, 要么是web里面写的来源, 要么是写在不同的包里面的(1: ios , 2: android官网 3: adline 4, CGP(web原来注册的), (4, 不用写到客户端里面去), 5: 豌豆荚
        */
        if($request->server['request_uri'] == '/svrlistnew.php' or $request->server['request_uri'] == '/svrlist.php')
        {
            //开始正式的复杂流程
            if(isset($verlist[$request->get['edition']]))
                $edition = $request->get['edition'];
            else
                $edition = $gamename;


            if(isset($request->get['clientver']))
                $clientver = $request->get['clientver'];
            else
                $clientver = 1;

            if(isset($request->get['channel']))
                $channel = $request->get['channel'];
            else
                $channel = 2;
            //审核版本稍微特殊处理，还有特殊版本，可以参考类似处理
            if($clientver == $iosaudit_ver)
            {
                $edition = 'iosaudit';
                //$channel = 1;
            }
  
            if(isset($verlist[$edition][$channel]))
                $result["upgrade_json"] = $verlist[$edition][$channel];
            else
                $result["upgrade_json"] = $verlist[$gamename][2]; //默认返回官方android的更新

            /*
            $tmpvers = explode('.', $clientver);
            $binver = isset($tmpvers[2])?intval($tmpvers[2]):0;
            $resver = isset($tmpvers[3])?intval($tmpvers[3]):0;
            if($binver < $result['binverlimit'])
                $result['popwin'] = 3; //二进制基本都是建议更新，如果某次版本不兼容，这手工修改成3， 强制更新
            elseif($resver < $result['resverlimit'])
                $result['popwin'] = 1;
            else
                $result['popwin'] = 0;

            if(isset($shenhe_vers[$channel]) and $shenhe_vers[$channel] == $clientver )
                $result['androidshenhe'] = 1;
            */
            //带入serverlist_json 和 version_txt
            //独特版本和渠道的
            if(isset($serverlist_json[$edition.':'.$channel]))
                $result['serverlist_json'] = $serverlist_json[$edition.':'.$channel];
            elseif(isset($serverlist_json['zero'])) 
            {} //什么都不返回，客户端会去指定地方下载
            else
                $result['serverlist_json'] = $serverlist_json['default']; //通用的

            //空version，从静态文件中获得
            // if(isset($version_txt[$edition])) 
            //     $result['version_txt'] = $version_txt[$edition];
            // elseif(isset($version_txt['zero']))
            // {} //什么都不返回，客户端会去指定地方下载
            // else
            //     $result['version_txt'] = $version_txt['default']; //通用的

            //json 编码，加密
            $result = json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        elseif($request->server['request_uri'] == '/gsverlimit.php')  //游戏服得到版本号
        {
            $tmpvar = array();
            foreach ($verlist[$gamename] as $key => $value)
            {
                $tmpvar[$key] = array(
                    'binverlimit'=>$value['binverlimit'],
                    'resverlimit'=>$value['resverlimit'],);
            }
            $result = json_encode($tmpvar, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        elseif($request->server['request_uri'] == '/hostname.php')  //获得本机的hostname，知道是内网还是外网
        {
            $result = json_encode(file_get_contents('/etc/hostname'), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        elseif($request->server['request_uri'] == '/notice.php')  //去得到公告, 较老版本才用，以后应该不用了
        {
            $result = array('subject'=>'', 'content'=>'', 'button'=>'');
            $result = json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        return $result;
    }
}



$setargv = array(
    'max_request'=> 0,
    'worker_num' => 4,
    'dispatch_mode' => 2,
    //'daemonize' => 1,
    //'log_file' => '/tmp/swoole.log',
);

//上级目录格式“ver游戏名_port”,
$dirarray=explode('/', __FILE__);
$dirs = explode('_', $dirarray[count($dirarray)-2]);
$gamename = substr($dirs[0],3);
$port = 0;
if(isset($dirs[1])) $port = intval($dirs[1]);
if($port == 0)
    $port = 4321;
$svr = new BaseWebSvr('0.0.0.0', $port, $setargv);
$svr->run();

