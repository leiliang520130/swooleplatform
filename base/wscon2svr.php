<?PHP
//各svr之间的互联, 全部是内网连接, 采用websocket的链接
class WSCon2Svr extends BaseWSCon
{
    protected $router;
    function __construct($mytype, $tosvr, $inthost=0, $intport=0)
    {
        parent::__construct($tosvr, $inthost, $intport);
        if($tosvr == 'core') // wsgate, httpgate 连接到 core
        {
            if($mytype == 'wsgate')
                $this->router = 'W2CRouter';
            elseif($mytype == 'httpgate')
                $this->router = 'H2CRouter';
        }
    }

    function runonecmd($v)
    {
        global $glargv;
        global $glconf;
        //这里还不能确定是那个router,所以不好直接用静态函数调用方法,所以使用了  call_user_func_array()
        if($v['cmd'] != 'dotick')
            debugvar($v, 'runonecmd:');
        $result = call_user_func_array(array($this->router, $v['cmd']), array($v));
        if($result[0] == 'send') //这个链接返回消息
            $this->sendws(ws_encode($result[1]));
        elseif($result[0] == 'sendufd')
        {
            if($glconf['stopwatch'] and isset($result[1]['btime']))
            {
                $result[1]['spendtime'] = round(microtime(true)*1000 - $result[1]['btime'], 2);
                unset($result[1]['btime']);
            }
            $result[1]['svrtime'] = $glargv['nttime'];
            if(isset($result[1]['ufdpacktype']))
                $glargv['serv']->push($v['ufd'], ws_encode($result[1], $result[1]['ufdpacktype']));
            else
                $glargv['serv']->push($v['ufd'], ws_encode($result[1], 1));
        }
        elseif($result[0] == 'task') //data返回的结果,再走task这种情况应该比较少见了. $v['ufd'] 才是最终玩家的socket
            $glargv['serv']->task(s2s_encode(array_merge($result[1], array('fd'=>$v['ufd']))));
        elseif($result[0] == 'nothing')
        {}
    }
}
