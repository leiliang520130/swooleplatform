<?PHP
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
        // register_shutdown_function(array($this, 'shutdown_handler'));

        $serv = new swoole_http_server($this->host, $this->port);
        $serv->set($this->setargv);
        $serv->on('Start', array($this, 'onStart'));
        $serv->on('Shutdown', array($this, 'onShutdown'));
        $serv->on('ManagerStart', array($this, 'onManagerStart'));
        $serv->on('WorkerStart', array($this, 'onWorkerStart'));
        $serv->on('WorkerStop', array($this, 'onWorkerStop'));
        $serv->on('WorkerError', array($this, 'onWorkerError'));
        // if(DEBUG) $serv->on('Connect', array($this, 'onConnect'));
        // if(DEBUG) $serv->on('Close', array($this, 'onClose'));
        $serv->on('Task', array($this, 'onTask'));
        $serv->on('Finish', array($this, 'onFinish'));
        $serv->on('Timer', array($this, 'onTimer'));
        $serv->on('Request', array($this, 'onRequest'));
        $serv->on('Receive', array($this, 'onReceive'));
        $serv->setGlobal(HTTP_GLOBAL_ALL);
        // $serv->setGlobal(HTTP_GLOBAL_ALL, HTTP_GLOBAL_GET| HTTP_GLOBAL_POST| HTTP_GLOBAL_COOKIE);
        // $serv->setGlobal(HTTP_GLOBAL_ALL, HTTP_GLOBAL_GET | HTTP_GLOBAL_POST);
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
        renameporcess("php {$argv[0]}: {$glargv['zoneid']}@{$glargv['zonetype']} {$glargv['myname']} master");
        slog("{$glargv['myname']} MasterPid={$serv->master_pid}| {$glargv['myname']} Manager_pid={$serv->manager_pid}");
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
        renameporcess("php {$argv[0]}: {$glargv['zoneid']}@{$glargv['zonetype']} {$glargv['myname']} manager");
    }

    public function onConnect($serv, $fd, $from_id)
    {
        slog("Client[$fd@$from_id]: Connect.");
    }

    public function onClose($serv, $fd, $from_id)
    {
        slog("Client[$fd@$from_id]: fd=$fd is closed");
    }

    public function onFinish(swoole_server $serv, $task_id, $data)
    {
        slog("Task Finish: result=->{$data}<-. Task_id={$task_id} .PID=".posix_getpid());
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
            var_dump($conn_list);
            if($conn_list===false)
                break;
            $start_fd = end($conn_list);
            foreach($conn_list as $fd)
                $serv->close($fd);
        } //end while
    }

    public function onTimer($serv, $interval)
    {}

    public function onTask(swoole_server $serv, $task_id, $from_id, $data)
    {}

    public function onWorkerStart($serv, $worker_id)
    {}

    public function onReceive(swoole_server $serv, $fd, $from_id, $data)
    {}

    public function error_handler($errno, $errstr, $errfile, $errline)
    {
        global $glargv;
        slog($glargv['myname'] . ' error:');
        slog('$errno:' . var_export($errno, true));
        slog('$errstr:' . var_export($errstr, true));
        slog('$errfile:' . var_export($errfile, true));
        slog('$errline:' . var_export($errline, true));
    }

    public function shutdown_handler()
    {
        global $glargv;
        if ($error = error_get_last())
        {
            slog($glargv['myname'] . ' shutdown:');
            slog('$error:' . var_export($error, true));
        }
    }
}
