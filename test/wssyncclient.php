<?php
//同步的阻塞的websocketclient
class WsSyncClient
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
    private $key;
    private $host;
    private $port;
    private $path;
    private $timeout;
    /**
     * @var swoole_client
     */
    private $socket;
    private $buffer = ''; //这个只用来分析http的头????
    private $origin = 'http://twotael.com';
    /**
     * @var bool
     */
    private $connected = false;

    /**
     * @param string $host
     * @param int    $port
     * @param string $path
     */
    function __construct($host = '127.0.0.1', $port = 8080, $path = '/', $origin = null, $timemout=3)
    {
        $this->host = $host;
        $this->port = $port;
        $this->path = $path;
        $this->timemout = $timemout;

        $this->origin = $origin;
        $this->key = $this->generateToken(self::TOKEN_LENGHT);
    }

    /**
     * Disconnect on destruct
     */
    function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Connect client to server
     *
     * @return $this
     */
    public function connect()
    {
        $this->socket = new \swoole_client(SWOOLE_SOCK_TCP);
        if (!$this->socket->connect($this->host, $this->port, $this->timemout))
        {
            return false;
        }
        $this->socket->send($this->createHeader());
        
        $a = $this->recv(); //这一步去确认是http的连接上了
        return $a;
    }

    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * Disconnect from server
     */
    public function disconnect()
    {
        $this->connected = false;
        $this->socket->close();
    }

    public function recv()
    {
        $data = $this->socket->recv();
        if ($data === false)
        {
            echo "Error: {$this->socket->errMsg}";
            return false;
        }
        $this->buffer .= $data;
        //如果已经连接上了, 则按照websocket后来的协议去分析,否则首先要分析http的头
        if($this->connected)
        {
            //从buffer中分解出一个完整的包来了, 就把剩下的重新放入buffer中
            $recv_data = $this->hybi10Decode($this->buffer);
            if ($recv_data)
            {
                // $this->buffer = substr($this->buffer, strlen($recv_data));
                return $recv_data;
            }
            else
            {
                return false;
            }
        }
        else
        {
            $recv_data = $this->parseIncomingRaw($this->buffer);
            if(isset($recv_data['Sec-Websocket-Accept'])
                and 
                base64_encode(pack('H*', sha1($this->key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')))
                === $recv_data['Sec-Websocket-Accept'])
            {
                $this->connected = true;
            }

        }
    }

    /**
     * @param        $data
     * @param string $type
     * @param bool   $masked
     */
    public function send($data, $type = 'text', $masked = true)
    {
        if($this->connected)
            return $this->socket->send($this->hybi10Encode($data, $type, $masked));
        else
            return $this->socket->send($this->hybi10Encode('', 'close', $masked));
    }


    /**
     * Create header for websocket client
     *
     * @return string
     */
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

    /**
     * Parse raw incoming data
     *
     * @param $header
     *
     * @return array
     */
    private function parseIncomingRaw($header)
    {
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

    /**
     * Generate token
     *
     * @param int $length
     *
     * @return string
     */
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

    /**
     * @param        $payload
     * @param string $type
     * @param bool   $masked
     *
     * @return bool|string
     */
    private function hybi10Encode($payload, $type = 'text', $masked = true)
    {
        $frameHead = array();
        $frame = '';
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

    /**
     * @param $data
     *
     * @return null|string
     */
    private function hybi10Decode(&$bytes)
    {
        if (empty($bytes))
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
                $mask = substr($bytes, 4, 4);
                $coded_data = substr($bytes, 8);
                $sublength = 8;
            }
            elseif ($dataLength === 127)
            {
                $mask = substr($bytes, 10, 4);
                $coded_data = substr($bytes, 14);
                $sublength = 14;
            }
            else
            {
                $mask = substr($bytes, 2, 4);
                $coded_data = substr($bytes, 6);
                $sublength = 6;
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
                $decodedData = substr($bytes, 4);
                $sublength = 4;
            }
            elseif ($dataLength === 127)
            {
                $decodedData = substr($bytes, 10);
                $sublength = 10;
            }
            else
            {
                $decodedData = substr($bytes, 2);
                $sublength = 2;
            }
        }
        //判断是否一个完整的包
        if(strlen($bytes) > $sublength + strlen($decodedData))
            $bytes = substr($bytes, $sublength + strlen($decodedData));
        else
            $bytes = '';

        return $decodedData;
    }
} 
