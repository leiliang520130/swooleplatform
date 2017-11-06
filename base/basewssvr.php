<?PHP
//原生的swoole 的 websocket server
//没有 onreceive了, 直接就是message了
class BaseWSSvr
{
    public $serv;
    public $host;
    public $port;
    public $rcvdata;
    public $setargv;

    function __construct($host, $port, $setargv)
    {
        $this->host = $host;
        $this->port = $port;
        $this->setargv = $setargv;
    }

    function run_pre()
    {
        set_error_handler(array($this, 'error_handler'));//函数设置用户自定义的错误处理程序
        register_shutdown_function(array($this, 'shutdown_handler'));

        $serv = new swoole_websocket_server($this->host, $this->port);
        $serv->set($this->setargv);
        $serv->on('Start', array($this, 'onStart'));
        $serv->on('Shutdown', array($this, 'onShutdown'));
        $serv->on('ManagerStart', array($this, 'onManagerStart'));
        $serv->on('Connect', array($this, 'onConnect'));
        $serv->on('Close', array($this, 'onClose'));
        $serv->on('WorkerStop', array($this, 'onWorkerStop'));
        $serv->on('WorkerError', array($this, 'onWorkerError'));
        $serv->on('Finish', array($this, 'onFinish'));
        $serv->on('Task', array($this, 'onTask'));
        $serv->on('WorkerStart', array($this, 'onWorkerStart'));
        $serv->on('Message', array($this, 'onMessage'));
        $serv->on('Open', array($this, 'onOpen'));
        return $serv;
    }

    function run()
    {
        $serv = $this->run_pre();
        $serv->start();
    }

    public function onStart($serv)
    {
        global $argv;
        global $glargv;
        slog('PHP Version: ' . PHP_VERSION );
        slog('Swoole Version: ' . SWOOLE_VERSION );
        renameporcess("php {$argv[0]}: {$glargv['zoneid']}@{$glargv['zonetype']} {$glargv['svrname']} master");
        slog("{$glargv['svrname']} MasterPid={$serv->master_pid}| {$glargv['svrname']} Manager_pid={$serv->manager_pid}");
        $glargv['serv'] = $serv;
    }

    public function onShutdown($serv)
    {
        slog('Server: onShutdown');
    }

    public function onManagerStart($serv)
    {
        global $argv;
        global $glargv;
        renameporcess("php {$argv[0]}: {$glargv['zoneid']}@{$glargv['zonetype']} {$glargv['svrname']} manager");
    }

    public function onConnect($serv, $fd, $from_id)
    {
        slog("Client[$fd@$from_id]: Connect.");
    }

    public function onClose($serv, $fd, $from_id)
    {
        slog("Client[$fd@$from_id]: fd=$fd is closed");
    }

    public function onWorkerStop($serv, $worker_id)
    {
        slog("WorkerStop[$worker_id]|pid=".posix_getpid());
        $start_fd = 0;
        while(true)
        {
            $conn_list = $serv->connection_list($start_fd, 10);
            if($conn_list===false)
                break;
            $start_fd = end($conn_list);
            foreach($conn_list as $fd)
                $serv->close($fd);
        } //end while
    }

    public function onWorkerError(swoole_server $serv, $worker_id, $worker_pid, $exit_code)
    {
        slog("worker error exit. WorkerId=$worker_id|Pid=$worker_pid|ExitCode=$exit_code");
        $start_fd = 0;
        while(true)
        {
            $conn_list = $serv->connection_list($start_fd, 10);
            if($conn_list===false)
                break;
            $start_fd = end($conn_list);
            foreach($conn_list as $fd)
                $serv->close($fd);
        } //end while
    }

    public function onFinish(swoole_server $serv, $task_id, $data)
    {
        slog("Task Finish: result=->{$data}<-. Task_id={$task_id} .PID=".posix_getpid());
    }

    public function onTask(swoole_server $serv, $task_id, $from_id, $data)
    {}

    public function onWorkerStart($serv, $worker_id)
    {
        $this->serv = $serv;
    }

    public function onOpen($serv, $req)
    {}

    public function onMessage($serv, $frame)
    {}

    public function error_handler($errno, $errstr, $errfile, $errline)
    {
        global $glargv;
        slog($glargv['svrname'] . ' error:');
        slog('$errno:' . var_export($errno, true));
        slog('$errstr:' . var_export($errstr, true));
        slog('$errfile:' . var_export($errfile, true));
        slog('$errline:' . var_export($errline, true));
        slog('svr rcvdata: ' . $this->rcvdata);
    }

    public function shutdown_handler()
    {
        global $glargv;
        if ($error = error_get_last())
        {
            slog($glargv['svrname'] . ' shutdown:');
            slog('$error:' . var_export($error, true));
        }
    }

    //这个实际就应该server已经实现的了的push
    public function sendws($fd, $message, $opcode = self::OPCODE_TEXT_FRAME, $end = true)
    {
        if ((self::OPCODE_TEXT_FRAME  === $opcode or self::OPCODE_CONTINUATION_FRAME === $opcode) and false === (bool) preg_match('//u', $message))
        {
            $this->log('Message [%s] is not in UTF-8, cannot send it.', 2, 32 > strlen($message) ? substr($message, 0, 32) . ' ' : $message);
            return false;
        }
        else
        {
            $out = $this->newFrame($message, $opcode, $end);
            return $this->serv->send($fd, $out);
        }
    }
}
