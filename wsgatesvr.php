<?php
//来自原力的 平台服务器 //转发ser
define('CONNTIMER', 3000);          //检查连接的定时器 ms
include('includeall.php');

$glargv['svrtype'] = 'wsgate';
$glargv['svrname'] = $glargv['svrtype'] . 'svr';

class WSGateSvr extends BaseWSSvr
{
    public $condata = false;

    function onWorkerStart($serv, $worker_id)
    {
        global $argv;
        global $glconf;
        global $glargv;

        $glargv['this'] = $this;
        $glargv['serv'] = $serv;

        connectredis();
        if($worker_id >= $serv->setting['worker_num']) {
            renameporcess("php {$argv[0]}: {$glargv['zoneid']}@{$glargv['zonetype']} {$glargv['svrname']} tasker $serv->worker_id");
            slog("{$glargv['svrname']} WorkerStart: MasterPid={$serv->master_pid}|Manager_pid={$serv->manager_pid}|WorkerId={$serv->worker_id}|TaskerId={$serv->worker_id}");
        } else {
            renameporcess("php {$argv[0]}: {$glargv['zoneid']}@{$glargv['zonetype']} {$glargv['svrname']} worker $serv->worker_id");
            $contodata = new WSCon2Svr($glargv['svrtype'], 'core');
            slog("connect to coresvr : {$glconf['coresvr']['inthost']}:{$glconf['coresvr']['intport']}");
            $serv->tick(CONNTIMER, 'checkconn');          //gamecon 的检查和重连的
            slog("{$glargv['svrname']} WorkerStart: MasterPid={$serv->master_pid}|Manager_pid={$serv->manager_pid}|WorkerId={$serv->worker_id}|WorkerId={$serv->worker_id}");
        }
    }

    //处理消息
    function onMessage($serv, $frame)
    {
        global $glargv;
        global $glconf;
        $fd = $frame->fd;
        // $info = $serv->connection_info($frame->fd);
        // echo "received ".strlen($frame->data)." bytes\n";
        $req = ws_decode($frame->data);
        debugvar($req);
        $glargv['btime'] = intval(microtime(true) * 1000);
        $glargv['nttime'] = intval($glargv['btime']/1000);
        //连接loginsvr的时候, rid肯定都是0的,所以不用特殊考虑
        if(!is_string($req['cmd']))
            return false;
        $fn = $req['cmd'];
        $result = WSGateRouter::$fn($req, $fd);
        if($result[0] == 'send')
        {
            $result[1]['svrtime'] = $glargv['nttime'];
            if($glconf['stopwatch'])
                $result[1]['spendtime'] = round(microtime(true)*1000 - $glargv['btime'], 2);
            $serv->push($fd, ws_encode($result[1]));
        }
        elseif($result[0] == 'task')
            $serv->task(s2s_encode(array_merge($result[1], array('fd'=>$fd))));
        elseif($result[0] == 'core')
        {
            if($glconf['stopwatch'])
                $glargv['corecon']->sendws(ws_encode(array_merge($result[1], array('ufd'=>$fd, 'btime'=>$glargv['btime']))));
            else
                $glargv['corecon']->sendws(ws_encode(array_merge($result[1], array('ufd'=>$fd))));
        }
    }
}

function checkconn($tm, $par=null)
{
    global $glargv;
    global $glconf;
    if($glargv['corecon'] === 0)
    {
        slog('Try to reconnect coresvr');
        $contodata = new WSCon2Svr($glargv['svrtype'], 'core');
    }
}

$setargv = array(
    'max_request'=> 0,
    'worker_num' => 2,
    'dispatch_mode' => 3,
    'task_worker_num' => 0,
    'task_ipc_mode' => 2,
    'message_queue_key' => ftok(__FILE__, 'l'),
    'log_file' => '/tmp/platform_' . $glargv['svrname'] . '_swoole.log',
    );

$svr = new WSGateSvr('0.0.0.0', $glconf[$glargv['svrname']]['extport'], $setargv, $glargv['svrname']);
$svr->run();
