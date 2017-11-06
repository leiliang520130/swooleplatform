<?PHP
// basecon 基本的 异步websocket 客户端, 可以用在s2s的连接中
class BaseWSCon
{
    const VERSION = '0.1.4';
    const TOKEN_LENGHT = 16;
    const TYPE_ID_WELCOME = 0;
    const TYPE_ID_PREFIX = 1;
    const TYPE_ID_CALL = 2;
    const TYPE_ID_CALLRESULT = 3;
    const TYPE_ID_ERROR = 4;
    const TYPE_ID_SUBSCRIBE = 5;
    const TYPE_ID_UNSUBSCRIBE = 6;
    const TYPE_ID_PUBLISH = 7;
    const TYPE_ID_EVENT = 8;
    public $key;
    public $buffer = ''; //这个只用来分析http的头
    public $tosvr;      //string  'data'  
    public $concli=0;
    public $handshake = false; //http头验证是否通过
    public $connected = false; //是否发送了connect命令
    private $host;
    private $port;
    private $origin = 'http://twotael.com';
    private $path = '/';
    public $cmds = array();// clicon 接收的数据解析出来的命令

    function __construct($tosvr, $inthost=0, $intport=0)
    {
        $this->key = $this->generateToken(self::TOKEN_LENGHT);
        $this->conn($tosvr, $inthost, $intport);
    }

    function __destruct()
    {
        $this->disconnect();
    }

    function conn($tosvr, $inthost, $intport)
    {
        global $glconf;      
        $this->tosvr = $tosvr;
        if($inthost == 0)
        {
            $this->host = $glconf[$this->tosvr . 'svr']['inthost'];
            $this->port = $glconf[$this->tosvr . 'svr']['intport'];
        }
        else
        {
            $this->host = $inthost;
            $this->port = $intport;
        }
        $concli = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $concli->on('connect',  array($this, 'cliconn'));
        $concli->on('receive',  array($this, 'clireceive'));
        $concli->on('error',    array($this, 'clierror'));
        $concli->on('close',    array($this, 'cliclose'));
        $concli->connect($this->host, $this->port, 5);
        $this->concli = $concli;
    }

    //一个svr一连接到另一个svr,就发送一个connect命令,把自己的名字报上去
    function cliconn($cli)
    {
        $cli->send($this->createHeader());
//        $glargv[$this->tosvr . 'con'] = $cli;
    }

    function clierror($cli)
    {
        global $glargv;
        slog($glargv[$this->tosvr . 'con'] . ' cli conn error');
    }

    function clireceive($cli, $data)
    {
        global $glargv;

        $this->buffer .= $data;
        do
        {
            //如果已经连接上了, 则按照websocket后来的协议去分析,否则首先要分析http的头
            if($this->handshake)
            {
                if($this->connected == false)
                {
                    $this->sendws(ws_encode(array('cmd'=>'connect', 'par'=>array('from'=>$glargv['svrname']))), 'binary');
                    $glargv[$this->tosvr . 'con'] = $this;  //因为不是纯的socket ,所以要用 this ,这样可以使用 sendws()来发送
                    $this->connected  = true;
                }
                //从buffer中分解出一个完整的包来了, 就把剩下的重新放入buffer中
                $recv_data = $this->hybi10Decode($this->buffer);
                // debugvar($recv_data);
                if ($recv_data)
                {
                    //解析一条,执行一条
                    $this->runonecmd(ws_decode($recv_data));
                    // $this->cmds[] = ws_decode($recv_data);
                    // debugvar$this->cmds);
                }
                else
                    break;  //为null 则退出循环,继续等待接收
            }
            else
            {
                $recv_data = $this->parseIncomingRaw($this->buffer);
                // debugvar($recv_data);
                if($recv_data === false)
                    break; //挑出循环继续接受
                if(isset($recv_data['Sec-Websocket-Accept'])
                    and 
                    base64_encode(pack('H*', sha1($this->key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')))
                    === $recv_data['Sec-Websocket-Accept'])
                {
                    $this->handshake = true;
                    $glargv[$this->tosvr . 'con'] = $cli;
                    //继续做后面的socket接收
                }
            }
        }
        while(true);
        //每次接收都要运行命令
        // $this->runcmds();
    }

    function cliclose($cli)
    {
        global $glargv;
        $glargv[$this->tosvr . 'con'] = 0;
        slog($this->tosvr . 'con' . ' cli conn close');
    }

    //主要重写这块的代码
    public function runonecmd($cmd)
    {
        debugvar($cmd, 'recv_cmd');
    }

    //主要重写这块的代码
    public function runcmds()
    {
        foreach($this->cmds as $v)
        {
            debuglog('runcmds:' . var_export($v, true));
        }
        $this->cmds = array();
    }

    public function disconnect()
    {
        $this->handshake = false;
        $this->concli->close();
    }

    //专门发送ws格式的数据, 这里可以把data数据用msgpack打包后发出去
    public function sendws($data, $type = 'text', $masked = false)
    {
        return $this->concli->send($this->hybi10Encode($data, $type, $masked));
    }


    private function createHeader()
    {
        $host = $this->host;
        if ($host === '127.0.0.1' || $host === '0.0.0.0')
        {
            $host = 'localhost';
        }
        return "GET {$this->path} HTTP/1.1" . "\r\n" .
        "Origin: {$this->origin}" . "\r\n" .
        "Host: {$host}:{$this->port}" . "\r\n" .
        "Sec-WebSocket-Key: {$this->key}" . "\r\n" .
        "User-Agent: PHPWebSocketClient/" . self::VERSION . "\r\n" .
        "Upgrade: websocket" . "\r\n" .
        "Connection: Upgrade" . "\r\n" .
        "Sec-WebSocket-Protocol: wamp" . "\r\n" .
        "Sec-WebSocket-Version: 13" . "\r\n" . "\r\n";
    }

    private function parseIncomingRaw($header)
    {
        //先判断header的头是否完整, 需要是 "\r\n\r\n"结尾的
        if(substr($header, -4) != "\r\n\r\n")
            return false;
        $retval = array();
        $content = "";
        $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));

        foreach ($fields as $field)
        {
            if (preg_match('/([^:]+): (.+)/m', $field, $match))
            {
                $match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./',
                    function ($matches)
                    {
                        return strtoupper($matches[0]);
                    },
                    strtolower(trim($match[1])));
                if (isset($retval[$match[1]]))
                {
                    $retval[$match[1]] = array($retval[$match[1]], $match[2]);
                }
                else
                {
                    $retval[$match[1]] = trim($match[2]);
                }
            }
            else
            {
                if (preg_match('!HTTP/1\.\d (\d)* .!', $field))
                {
                    $retval["status"] = $field;
                }
                else
                {
                    $content .= $field . "\r\n";
                }
            }
        }
        //分解完http的头, 应该buffer重新置空开始
        $this->buffer = '';
        return $retval;
    }

    /**
     * Generate token
     *
     * @param int $length
     *
     * @return string
     */
    private function generateToken($length)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"§$%&/()=[]{}';
        $useChars = array();
        // select some random chars:
        for ($i = 0; $i < $length; $i++)
        {
            $useChars[] = $characters[mt_rand(0, strlen($characters) - 1)];
        }
        // Add numbers
        array_push($useChars, rand(0, 9), rand(0, 9), rand(0, 9));
        shuffle($useChars);
        $randomString = trim(implode('', $useChars));
        $randomString = substr($randomString, 0, self::TOKEN_LENGHT);
        return base64_encode($randomString);
    }

    public function generateAlphaNumToken($length)
    {
        $characters = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
        srand((float)microtime() * 1000000);
        $token = '';
        do
        {
            shuffle($characters);
            $token .= $characters[mt_rand(0, (count($characters) - 1))];
        } while (strlen($token) < $length);
        return $token;
    }

    private function hybi10Encode($payload, $type = 'text', $masked = true)
    {
        $frameHead = array();
        $frame = '';
        // debugvar($payload);
        $payloadLength = strlen($payload);
        switch ($type)
        {
            case 'text':
                // first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 129;
                break;
            case 'binary':
                // first byte indicates FIN, Text-Frame (10000010):
                $frameHead[0] = 130;
                break;
            case 'close':
                // first byte indicates FIN, Close Frame(10001000):
                $frameHead[0] = 136;
                break;
            case 'ping':
                // first byte indicates FIN, Ping frame (10001001):
                $frameHead[0] = 137;
                break;
            case 'pong':
                // first byte indicates FIN, Pong frame (10001010):
                $frameHead[0] = 138;
                break;
        }
        // set mask and payload length (using 1, 3 or 9 bytes)
        if ($payloadLength > 65535)
        {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 255 : 127;
            for ($i = 0; $i < 8; $i++)
            {
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
            }
            // most significant bit MUST be 0 (close connection if frame too big)
            if ($frameHead[2] > 127)
            {
                $this->close(1004);
                return false;
            }
        }
        elseif ($payloadLength > 125)
        {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        }
        else
        {
            $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
        }
        // convert frame-head to string:
        foreach (array_keys($frameHead) as $i)
        {
            $frameHead[$i] = chr($frameHead[$i]);
        }
        if ($masked === true)
        {
            // generate a random mask:
            $mask = array();
            for ($i = 0; $i < 4; $i++)
            {
                $mask[$i] = chr(rand(0, 255));
            }
            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);
        // append payload to frame:
        for ($i = 0; $i < $payloadLength; $i++)
        {
            $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }
        // for ($i=0; $i < strlen($frame); $i++) { 
        //     echo ord($frame[$i]) . PHP_EOL;
        // }
        return $frame;
    }

    //@todo: 修改后, 接收包还有是有些问题, 解决了粘包, 但慢包,碎包可能还是有问题(没有验证)
    private function hybi10Decode($bytes)
    {
        if (empty($bytes) or strlen($bytes)<6)
        {
            return null;
        }
        $dataLength = '';
        $mask = '';
        $coded_data = '';
        $decodedData = '';
        $secondByte = sprintf('%08b', ord($bytes[1]));
        $masked = ($secondByte[0] == '1') ? true : false;
        $dataLength = ($masked === true) ? ord($bytes[1]) & 127 : ord($bytes[1]);
        $sublength = 0;   //$bytes 可能是多个包的, 需要而外去掉的消除长度
        if ($masked === true)
        {
            if ($dataLength === 126)
            {
                $dataLength = $this->realdatalength(substr($bytes,2, 2));
                $mask = substr($bytes, 4, 4);
                $sublength = 8;
                if(strlen($bytes) < $sublength + $dataLength) return null;
                $decodedData = substr($bytes, $sublength, $dataLength);
            }
            elseif ($dataLength === 127)
            {
                $dataLength = $this->realdatalength(substr($bytes,2, 8));
                $mask = substr($bytes, 10, 4);
                $sublength = 14;
                if(strlen($bytes) < $sublength + $dataLength) return null;
                $decodedData = substr($bytes, $sublength, $dataLength);
            }
            else
            {
                $mask = substr($bytes, 2, 4);
                $sublength = 6;
                if(strlen($bytes) < $sublength + $dataLength) return null;
                $decodedData = substr($bytes, $sublength, $dataLength);
            }
            for ($i = 0; $i < strlen($coded_data); $i++)
            {
                $decodedData .= $coded_data[$i] ^ $mask[$i % 4];
            }
        }
        else
        {
            if ($dataLength === 126)
            {
                $dataLength = $this->realdatalength(substr($bytes,2, 2));
                $sublength = 4;
                if(strlen($bytes) < $sublength + $dataLength) return null;
                $decodedData = substr($bytes, $sublength, $dataLength);
            }
            elseif ($dataLength === 127)
            {
                $dataLength = $this->realdatalength(substr($bytes,2, 8));
                $sublength = 10;
                if(strlen($bytes) < $sublength + $dataLength) return null;
                $decodedData = substr($bytes, $sublength, $dataLength);
            }
            else
            {
                $sublength = 2;
                if(strlen($bytes) < $sublength + $dataLength) return null;
                $decodedData = substr($bytes, $sublength, $dataLength);
            }
        }
        //判断是否一个完整的包
        if(strlen($bytes) > $sublength + $dataLength)
        {
            //@todo : 这里的代码需要清理 ,此处的bug还没有最终确认修复
            debugvar('tooloooooooooooooooooog');
            debugvar($this->buffer, '$this->buffer');
            $this->buffer = substr($bytes, $sublength + $dataLength);
        }
        else
            $this->buffer = '';

        return $decodedData;
    }

    //读取额外的长度,注意是网络序
    function realdatalength($string)
    {
        $return = '';
        for ($i = 0; $i < strlen($string); $i++)
            $return .= sprintf("%08b", ord($string[$i]));
        return bindec($return); 
    }

} 