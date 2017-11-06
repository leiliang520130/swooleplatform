<?php
// 来自texas的coresvr //服务器
include('includeall.php');

$glargv['svrtype'] = 'core';
$glargv['svrname'] = $glargv['svrtype'] . 'svr'; //coresvr

define('MYSLQINTERVAL', 300000); //检查mysql正常的时间, 300秒, 5分钟

class CoreSvr extends BaseWSSvr
{
    public $nextuserid = -1; //下一个新注册角色用到的roleid.只在主进程中有效

    public function run()
    {
        global $glconf;
        global $glargv;
        $serv = $this->run_pre();
        //function swoole_server->addListener(string $host, int $port, $type = SWOOLE_SOCK_TCP);
        //Swoole提供了swoole_server::addListener来增加监听的端口。业务代码中可以通过调用swoole_server::connection_info来获取某个连接来自于哪个端口。
        $serv->addlistener($glconf['coresvr']['inthost'], $glconf[$glargv['svrname']]['intport'], SWOOLE_SOCK_TCP);
        $serv->start();
    }

    //需要区分是连接的哪个服务端口
    public function onConnect($serv, $fd, $from_id)
    {
        //获取连接的信息
        /**
         * 如果传入的fd存在，将会返回一个数组
         * 连接不存在或已关闭，返回false
         * 第3个参数设置为true，即使连接关闭也会返回连接的信息
         */
        $info = $serv->connection_info($fd);
        slog("Client[$fd@$from_id]: Connect port=" . $info['server_port']);
    }

    public function onWorkerStart($serv, $worker_id)
    {
        global $argv;
        global $glconf;
        global $glargv;
        $glargv['this'] = $this; //对象自己 为0
        $glargv['serv'] = $serv;//服务程序自己 为0
        //platform/libs/utils.php 你们连接mysql使用mysqli的方式连接
        if(!connectmysql())
            //相当于执行kill -15 主进程PID
            $serv->shutdown(); //work,task都要链接到数据库上
        connectredis();

        if($worker_id >= $serv->setting['worker_num']) //在回调函数中可以访问运行参数的值
        {
            renameporcess("php {$argv[0]}: {$glargv['zoneid']}@{$glargv['zonetype']} {$glargv['svrname']} tasker $serv->worker_id");//给进程取名字
            //打印出进程的id
            slog("{$glargv['svrname']} WorkerStart: MasterPid={$serv->master_pid}|Manager_pid={$serv->manager_pid}|WorkerPid={$serv->worker_pid}|TaskerId={$serv->worker_id}");
        }
        else
        {   //work进程
            //进程重命名 rs.sh 方便杀死进程
            renameporcess("php {$argv[0]}: {$glargv['zoneid']}@{$glargv['zonetype']} {$glargv['svrname']} worker $serv->worker_id");//进程的名字
            $serv->tick(MYSLQINTERVAL, 'checkconn'); //检查mysql正常的时间, 300秒, 5分钟
            //把于用户无关的游戏全局数据从mysql中读入到redis中
            if($glargv['rediscon'] and $glargv['mysqlcon'])
            {
                $this->nextuserid = \user\getnextuserid($glargv['rediscon'], $glargv['mysqlcon']);//redis 和 mysql的连接 获取用户id
                slog('next roleid=' . $this->nextuserid);
                slog("{$glargv['svrname']} WorkerStart: MasterPid={$serv->master_pid}|Manager_pid={$serv->manager_pid}|WorkerPid={$serv->worker_pid}|WorkerId={$serv->worker_id}");
            }
            else
                $serv->shutdown();
        }
    }

    //处理消息

    /**
     * @param $serv
     * @param $frame
     * @return bool
     *
     *  $frame->fd，客户端的socket id，使用$server->push推送数据时需要用到
     *  $frame->data，数据内容，可以是文本内容也可以是二进制数据，可以通过opcode的值来判断
     *  $frame->opcode，WebSocket的OpCode类型，可以参考WebSocket协议标准文档
     *  $frame->finish， 表示数据帧是否完整，一个WebSocket请求可能会分成多个数据帧进行发送
     *
     */
    function onMessage($serv, $frame)
    {
        global $glargv;
        global $glconf;
        $fd = $frame->fd;
        $info = $serv->connection_info($fd);//查看返回一个数组
        $req = ws_decode($frame->data);//把json转换为数组
        if(!is_string($req['cmd']))
            return false;

        if($info['server_port'] == $glconf['coresvr']['intport']) //9098
        {
            //内网来的
            if( $req['cmd'] == 'reguser' or $req['cmd'] == 'regmobile')
            {
                $this->nextuserid = $glargv['rediscon']->incr('nextuserid'); //将 key 中储存的数字值增一
                $req['par']['nextuserid'] =  $this->nextuserid;
            }
            slog('-------------------------------'.$req['cmd']);
            $fn = $req['cmd'];
            //$result = CoreIntRouter::$req['cmd']($req, $fd); //路由到不同的命令去
            $result = CoreIntRouter::$fn($req, $fd); //路由到不同的命令去
            slog($glargv['svrname'] . ' recv: ->' . $req['cmd'] . '<-');
        }
        else
        {
            //外网来的,其实就是管理命令
        }
        debugvar($result, 'result'); //输出调试信息
        if($result[0] == 'send')
            $serv->push($fd, ws_encode($result[1]));//向websocket客户端连接推送数据，长度最大不得超过2M
        elseif($result[0] == 'task')
            //投递一个异步任务到task_worker池中。此函数是非阻塞的，执行完毕会立即返回。Worker进程可以继续处理新的请求。使用Task功能，必须先设置 task_worker_num，并且必须设置Server的onTask和onFinish事件回调函数。
            $serv->task(s2s_encode(array_merge($result[1], array('fd'=>$fd))));
    }

    //某个链接断了,说明是某个svr断开,需要把它从fdsvrlist中剥离开
    public function onClose($serv, $fd, $from_id)
    {
        global $glconf;
        global $glargv;
        slog("[$fd@$from_id] closed.");
    }

    //Task,在tasker进程中执行的. 主要是异步访问mysql

    /**
     * @param swoole_server $serv
     * @param $task_id
     * @param $from_id
     * @param $data
     * @return string
     *
     * $task_id是任务ID，由swoole扩展内自动生成，用于区分不同的任务。$task_id和$src_worker_id组合起来才是全局唯一的，不同的worker进程投递的任务ID可能会有相同
     * $src_worker_id来自于哪个worker进程
     * $data 是任务的内容
     *
     */
    public function onTask(swoole_server $serv, $task_id, $from_id, $data)
    {
        global $glargv;
        $req= s2s_decode($data); //编码
        if($req['cmd'] != 'checkconn') slog('coresvr task: ->' . $req['cmd'] . '<-');
        // if(DEBUG) slog('datasvr task: ->' . var_export($req) . '<-');
        $fname = 't_' . $req['cmd'];
        $result = CoreIntRouter::$fname($req); //待确认router运行原理
        if($result[0] == 'send')
        {
            $fd = $result[1]['fd']; // fd 是coresvr保持的哪个客户端(game,role)连接上来的,对最终用户来说是无意义的.
            //slog('============================'.$result);
            unset($result[1]['fd']);
            $serv->push($fd, ws_encode($result[1]));
        }
        elseif($result[0] == 'work') //返回给work来处理, 主要是去要主进程中获取nextuserid
        {
            return s2s_encode($result[1]);
        }
    }

    //其他task中执行的,基本都直接把结果send出去了,但只有新注册游客,需要 nextuserid
    //这里的流程是,当task发现需要新增一个游客,返回 regguest 命令给worker
    //worker 取到当前 nextuserid, 再次分发给tasker, 然后将其自增待下一个调用
    //这个worker通过redis的原子操作 incr ，得到唯一值
    /**
     * @param swoole_server $serv
     * @param $task_id
     * @param $data
     *  1.当worker进程投递的任务在task_worker中完成时，task进程会通过swoole_server->finish()方法将任务处理的结果发送给worker进程。
     *  2.$task_id是任务的ID
     *  3.$data是任务处理的结果内容
     *
     */
    public function onFinish(swoole_server $serv, $task_id, $data)
    {
        // slog("Task Finish: result=->{$data}<-. Task_id={$task_id} .PID=".posix_getpid());
        global $glargv;
        $req = s2s_decode($data);
        if($req['cmd'] == 'regguest' or $req['cmd'] == 'regthird')
        {
            //这里在par中添加一个参数
            $this->nextuserid = $glargv['rediscon']->incr('nextuserid');
            $req['par']['nextuserid'] =  $this->nextuserid;
            $serv->task(s2s_encode($req));
        }
    }

    //需确认这个消息的用处
    protected function checkmgrsession($cmd, $session)
    {
        global $glconf;
        if(DEBUG) return true;
        if(substr(md5($cmd . $glconf['coresvr']['mgrsalt']), 0, 12) == $session)
            return true;
        else
            return false;
    }
}

//定时检查mysql链接
function checkconn($tm, $par=null)
{
    global $glargv;
    // slog($glargv['serv']->worker_id . ':begin checkconn  mysqlcon');
    global $glargv;
    if($glargv['mysqlcon'] and $glargv['mysqlcon']->connect_errno == 0)
    {
        $a = $glargv['mysqlcon']->ping();
        if(false === $a or is_null($a))
        {
            $glargv['mysqlcon'] = 0;
            slog('Try to reconnect mysql');
            connectmysql();
        }
    }
    else
    {
        slog('Try to reconnect mysql');
        connectmysql();
    }
    //让task进程区检查 checkconn, 这个只由0号work进程来驱动了
    if($glargv['serv']->worker_id == 0)
    {
        for ($i=0; $i < $glargv['serv']->setting['task_worker_num'] ; $i++)
        { 
            $glargv['serv']->task(s2s_encode(array('cmd'=>'checkconn')), $i);
        }
    }
}

$setargv = array(
    'max_request'=> 0,
    'worker_num' => 2,
    'dispatch_mode' => 3,
    'task_worker_num' => 2, //后台可以去执行mysql, redis的进程数量(正式服应该10+)
    'task_ipc_mode' => 2,
    'message_queue_key' => ftok(__FILE__, 'l'),
    'log_file' => '/tmp/platform_' . $glargv['svrname'] . '_swoole.log',
);

$svr = new coresvr($glconf[$glargv['svrname']]['exthost'], $glconf[$glargv['svrname']]['extport'], $setargv);
$svr->run();
