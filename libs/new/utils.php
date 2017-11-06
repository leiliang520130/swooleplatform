<?PHP
//一些通用函数

//根据$rid, token,在玩家登录进入loginsvr后,每次生成唯一的session
//算法来自短链接算法,http://www.cnblogs.com/zemliu/archive/2012/09/24/2700661.html
//但只用了头两段,12字节
function setsession($rid=0, $salt='jrtgame')
{
    // if(DEBUGSESSION) return 'AAAAAAAAAAAA';
    $session = '';
    $charset = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
    $ridhash = md5(mt_rand() . $salt. $rid);
    for ($i = 0; $i < 2; $i++)
    {
        $urlhash_piece = substr($ridhash, $i * 8, 8);
        $hex = hexdec($urlhash_piece) & 0x3fffffff; #此处需要用到hexdec()将16进制字符串转为10进制数值型，否则运算会不正常
        for ($j = 0; $j < 6; $j++)
        {
            $session .= $charset[$hex & 0x0000003d];
            $hex = $hex >> 5;
        }
    }
    return $session;
}

//数据填入reis里的编码,解码
function redis_encode($pack, $packtype=1)
{
    return json_encode($pack, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}

function redis_decode($pack, $packtype=1)
{
    return safe_json_decode($pack);
}

//websocket协议时的解码, 编码, 默认是json编码
function ws_encode($pack, $packtype=1)
{
    if($packtype == 1)
        return json_encode($pack, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    else
        return msgpack_pack($pack);
}

function ws_decode($pack, $packtype=1)
{
    if($packtype == 1)
        return json_decode($pack, true);
    else
        return msgpack_unpack($pack);
}

//svr 和 svr之间通讯的协议编码
function s2s_encode($pack)
{
    global $glconf;
    $packtype = 1;
    if(isset($glconf['s2spacktype']))
        $packtype = (int)$glconf['s2spacktype'];
    if($packtype == 1)
        return json_encode($pack, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    else
        return msgpack_pack($pack);
}

function s2s_senddata($pack)
{
    $sendStr = s2s_encode($pack);
    return pack('n', strlen($sendStr)). $sendStr;
}

function s2s_decode($pack)
{
    global $glconf;
    $packtype = 1;
    if(isset($glconf['s2spacktype']))
        $packtype = (int)$glconf['s2spacktype'];
    if($packtype == 1)
        $req = json_decode($pack, true);
    else
        $req = msgpack_unpack($pack);
    if(is_array($req) and isset($req['cmd']) and is_string($req['cmd']))
        return $req;
    else
    {
        if(DEBUG)
        {
            debugvar($pack);
            debugvar($req);
        }
        slog('s2s pack errro : ' . json_decode($req), '3');
        return array('cmd'=>'nocmd', 'par'=>array());
    }
}

//client 和 svr 之间的通讯协议编码
//只是cmdline 调用
function c2s_encode($pack, $packtype = 1)
{
    if($packtype == 1)
        return json_encode($pack, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    else
        return msgpack_pack($pack);
}

//以后这里可能要多做判断和检验
function c2s_decode($pack, $packtype = 1)
{
    if($packtype == 1)
        $req = json_decode($pack, true);
    else
        $req = msgpack_unpack($pack);
    if(is_array($req) and isset($req['cmd']) and is_string($req['cmd']) and strlen($req['cmd'])>2 and isset($req['par']) and is_array($req['par']))
        return $req;
    else
    {
        slog('c2s pack errro : ' . json_encode($req, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), '3');
        return false;
    }
}

//s2c 的发送,可能pack里面包含了自己的packtype , (主要是可能转发到其他服务器的问题)
//这个时候,需要将新的packtype返回给调用者
function s2c_encode(&$pack, $packtype = 1)
{
    if(is_array($pack) and isset($pack['packtype']))
    {
        $packtype = (int)$pack['packtype'];
        unset($pack['packtype']);
    }
    if($packtype == 1)
        return array($packtype, json_encode($pack, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    else
        return  array($packtype, msgpack_pack($pack));
}

//只是cmdline 调用
function s2c_decode($pack, $packtype = 1)
{
    if($packtype == 1)
        return json_decode($pack, true);
    else
        return msgpack_unpack($pack);
}

//s -> c 发送
function s2c_senddata($pack, $packtype = 1)
{
    //分成字符串,数组来分别处理.
    //字符串只是为了方便测试而已.
    //两个字节的长度,一个字节的packtype,这个来判断压缩算法的
    list($newpacktype, $sendStr) = s2c_encode($pack, $packtype);
    return pack('nC', strlen($sendStr)+1, $newpacktype). $sendStr;
}

//对传入的$request 数据检查是否有['par']，par中中的参数是否齐备
//$parlist 形如 array('aaa', 'bbb')
//如果失败，则返回true;
function checkreqpar(&$req, $parlist=array())
{
    if(!isset($req['par']))
        return false;
    foreach ($parlist as $k => $v)
        if(!isset($req['par'][$v]))
            return false;
    return true;
}

function debuglog($str)
{
    if(DEBUG)
    {
        if(is_string($str))
            echo 'debug@' . date('m-d-H:i:s') . ' > ' . $str . PHP_EOL;
        else
        {
            echo 'debug@' . date('m-d-H:i:s') . ' > vardump ------ '  . PHP_EOL;
            var_dump($str);
            echo 'debug@' . date('m-d-H:i:s') . ' > vardump ======'  . PHP_EOL;
        }

    }
}


//system log, 系统记录需要的
function  slog($str, $level=0)
{
    echo 'slog@' . date('m-d-H:i:s') . ' > ' . $str . PHP_EOL;
}

#只保留数字,字母,汉字,#-_,多国话的话,可能有问题,到时要注意.
function safestr($str)
{
    return preg_replace("/[^\.0-9a-zA-Z#\_\-\x{4e00}-\x{9fbb}]/iu", '', $str);
}

//只保留数字,字母#-_
function safeascii($str)
{
    return preg_replace("/[^\.0-9a-zA-Z#\_\-\@]/iu", '', $str);
}

//只保留数字,字母#-_
function safeusername($str)
{
    return preg_replace("/[^\.0-9a-zA-Z#\_\-\@]/iu", '', $str);
}


/*
 * 中英文混合字符串,按照显示长度完整截取
 * http://www.kutailang.com/skill/671.html
 * 其中三个参数$str,$limit_length,$type=false分别为：要截取的字符串、长度、截取后是否在后面加上省略号。
 */
function cesubstrs($str, $limit_length, $type = false)

{
    //返回的字符串
    $return_str = "";
    //总长度，一个汉字算两个位置
    $total_length = 0;
    // 以utf-8格式求字符串的长度，每个汉字算一个长度
    $len = mb_strlen($str, 'utf8');
    for ($i = 0; $i < $len; $i++) {
        //以utf-8格式取得第$i个位置的字符，取的长度为1
        $curr_char = mb_substr($str, $i, 1, 'utf8');
        //如果字符的ACII编码大于127，则此字符为汉字，算两个长度
        $curr_length = ord($curr_char) > 127 ? 2 : 1;
        // 计算下一个utf8单位字符的长度，结果存入next_length
        if ($i != $len - 1) {
            $next_length = ord(mb_substr($str, $i + 1, 1, 'utf8')) > 127 ? 2 : 1;
        } else {
            $next_length = 0; //如果到最后一个字符了，则结束
        }
        // 如果总长度加上当前长度加上下一个单位的长度大于limit，则返回字符串，否则继续循环
        if ($total_length + $curr_length + $next_length > $limit_length) {
            if ($type) {
                $return_str .= $curr_char;
                return "{$return_str}...";
            } else {
                $return_str .= $curr_char;
                return "{$return_str}";
            }
        } else {
            $return_str .= $curr_char;
            $total_length += $curr_length;
        }
    }
    return $return_str;
}

/*
 * 从cesubstrs()函数中抽取出来的算字符串显示长度
 */
function cestrlen($str)
{
    $total_length = 0;
    $len = mb_strlen($str, 'utf8');
    for ($i = 0; $i < $len; $i++) {
        $curr_char = mb_substr($str, $i, 1, 'utf8');
        $curr_length = ord($curr_char) > 127 ? 2 : 1;
        $total_length += $curr_length;
    }
    return $total_length;
}

/**
* 校验最大值，兼容数字和字符串，如果$value为字符串的话，则$max为最小长度
*/
function varmax($value, $max)
{
    if (is_string($value)) $value = strlen($value);
    return $value <= $max;
}

/**
 *
 * 校验最小值，兼容数字和字符串，如果$value为字符串的话，则$min为最小长度
 */
function varmin($value, $min)
{
    if (is_string($value)) $value = strlen($value);
    return $value >= $min;
}

/**
 * 校验范围，兼容数字和字符串，如果$value为字符串的话，则校验长度 $range：array(最小值，最大值)
 */
function varrange($value, $range)
{
    if (is_string($value)) $value = strlen($value);
    return $value >= $range[0] && $value <= $range[1];
}

/**
 * 看两个时间是否同一天
 * 要考虑时区,还有就是凌晨5点为界限
 */
function isSameDay( $time1, $time2, $gametimeoffset=GAMEZERO)
{
    global $glargv;
    $time1 = (int)(($time1 + $glargv['timeoffset'] - $gametimeoffset * 3600) / 86400);
    $time2 = (int)(($time2 + $glargv['timeoffset'] - $gametimeoffset * 3600) / 86400);
    return ($time1 == $time2);
}

//今天周几, 要带上时区和时间偏移,周日是0
function weekday($time)
{
    global $glargv;
    return date('w', $time-$glargv['timeoffset']);
}

function renameporcess($name)
{
    global $glconf;
    if(isset($glconf['gamename']))
        $name = str_replace('php ', 'php ' . $glconf['gamename'] . '::', $name);
    if(PHP_OS!='Darwin')
        cli_set_process_title($name);
}

//安全转码,必定会的到一个数组
function safe_json_decode($str)
{
    if(is_array($str))
        return $str;
    if(is_string($str))
    {
        if (strlen($str)<2)
            return array();
        $a = json_decode($str, true);
        if(is_array($a))
            return $a;
        else
            return array();
    }
    return array();
}

//数组翻倍
function multiarray($array, $num)
{
    $tmparray = array();
    foreach ($array as $key => $value)
        $tmparray[$key] = $value * $num;
    return $tmparray;
}

//从下面类似的权重数组中随机返回一个值
/*  $wa: 类似下面的权重数组
    array(
    'itemid'=>array('aa', 'bb', 'cc', 'dd'),
    'weight'=>array(10, 30, 70, 90),
        )
    先随机出 [1-90] 的数字来,然后看落在那个区间,如果是1-10:a, 11-30:b,31-70:c,71-90:d
*/
function randofweight(&$wa)
{
    // debugvar($wa);
    $len = count($wa['weight']);
    $maxv = $wa['weight'][$len-1];
    $rand = mt_rand(1, $maxv);
    $i=0;
    for ($i=0; $i < $len; $i++)
    {
        if($rand >$wa['weight'][$i])
            continue;
        else
            return $wa['itemid'][$i];
    }
    return false;
}

//@todo: 检查聊天内容的合法性
function checkchatcontent($content)
{
    //这里的content就是复杂的东西了
    return true;
}

//@todo: 检查角色名的合法性
function checkrolename($rolename)
{
    if(strlen($rolename)<6) //两个汉字
        return array(-1, 'retmsg'=>'rolename_too_short');
    if(strlen($rolename)>30) //10个汉字
        return array(-2, 'retmsg'=>'rolename_too_long');
    if(substr($rolename, 0, 2) == '_g')
        return array(-3, 'retmsg'=>'rolename_not_beiginwith=_g');
    return array(1);
}

function connectmysql()
{
    global $glconf;
    global $glargv;
    $mysqlcon = new mysqli(
        $glconf['mysqlsvr']['host'],
        $glconf['mysqlsvr']['user'],
        $glconf['mysqlsvr']['pass'],
        $glconf['mysqlsvr']['dbname'],
        $glconf['mysqlsvr']['port']
    );
    if($mysqlcon->connect_errno)
    {
        $glargv['mysqlcon'] = 0;
        return false;
    }
    else
    {
        $mysqlcon->set_charset('utf8');
        $glargv['mysqlcon'] = $mysqlcon;
        slog('-> OK :'. $glargv['serv']->worker_id . ': Connect mysql is OK');
        return true;
    }
}

function connectredis()
{
    global $glconf;
    global $glargv;
    $rediscon= new Redis();
    $rediscon->pconnect(
        $glconf['redissvr']['host'],
        $glconf['redissvr']['port']
    );
    $rediscon->select($glconf['redissvr']['dbnum']);
    $glargv['rediscon'] = $rediscon;
    slog('-> OK :'. $glargv['serv']->worker_id . ': Connect redis is OK');
}

function writehistory($rid, $arr, $type=0, $maxlen=10)
{
    global $glargv;
    $arr['nt'] = $glargv['nttime'];
    $redis_arr = redis_decode($glargv['rediscon']->get($rid . ':' . historyname($type)));
    if(count($redis_arr) > $maxlen)
        array_pop($redis_arr);
    array_unshift($redis_arr, $arr);
    $glargv['rediscon']->set($rid . ':' . historyname($type), redis_encode($redis_arr));
    return true;
}

function gethistory($rid, $type=0)
{
    global $glargv;
    return array(1, 'ret'=>redis_decode($glargv['rediscon']->get($rid . ':' . historyname($type))));
}

function historyname($type=0)
{
    if($type>2 or $type<0) $type = 0;
    return array('arenahistory', 'rapehistory', 'ladderhistory')[$type];
}

function debugvar($var, $str=false)
{
    global $glargv;
    
    $a = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 1);
    echo PHP_EOL . basename($a[0]['file']) . ':' . $a[0]['line'];
    if($str) echo PHP_EOL . $str ;
    echo PHP_EOL . '>>>>>> debugvar begin -- '; 
    if(isset($glargv['btime']) and $glargv['btime']>0) 
        $btime = $glargv['btime'];
    else
        $btime = intval(microtime(true) * 1000);
    echo date('md_H:i:s', intval($btime/1000)) . ' -- ' . $btime;
    echo ' -- ' . PHP_EOL;
    var_dump($var);
    echo '>>>>>> debugvar end ++++++++++' . PHP_EOL;
}

function agentnumoftalbe($tablenum)
{
    return intval($tablenum / 100000);
}

function roomnumoftable($tablenum)
{
    return intval($tablenum / 1000);
}

//根据一个sng桌子类型,找到一个room编号
function roomnumofsngtype($sngtype)
{
    global $glconf;
    foreach ($glconf['sngtable']['start'] as $key => $value)
    {
        if(isset($value[$sngtype]))
            return $key;
    }
    return 0;
}

function agentnumofsngtype($sngtype)
{
    return intval(roomnumofsngtype($sngtype) / 100);
}

function safe_array_merge($a, $b)
{
    if(is_array($a) and is_array($b))
        return array_merge($a, $b);
    elseif(is_array($a))
    {
        debugvar($b, 'is not array');
        return $a;
    }
    elseif(is_array($b))
    {
        debugvar($a, 'is not array');
        return $b;
    }
    else
        return array();
}

//随机生成一个不在对应表里的随机桌号
function creatrandnumber()
{
    global $glargv;
    $randnum = 0;  
    do
    {
        $randnum =   mt_rand(1,9) * 100000 + 
                     mt_rand(0,9) * 10000 + 
                     mt_rand(0,9) * 1000 + 
                     mt_rand(0,9) * 100 + 
                     mt_rand(0,9) * 10 + 
                     mt_rand(0,9);
    } while (isset($glargv['randnumber'][$randnum]));
    return $randnum;
}

function randidentcode()
{
    return mt_rand(0,9) . mt_rand(0,9) . mt_rand(0,9) . mt_rand(0,9) . mt_rand(0,9) . mt_rand(0,9);
}
