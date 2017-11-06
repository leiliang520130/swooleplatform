<?PHP
//authsvr接收到所有命令路由

class CoreIntRouter
{
    public static function connect($req, $fd=0)
    {
        global $glargv;
        slog($req['par']['from'] . '[' . $fd .'@?] connect.');
        return array('sendsvr', array('cmd'=>'connect'));
    }

    public static function reguser($req)
    {
        return array('task', $req);
    }

    public static function newidentcode($req)
    {
        return array('task', $req);
    }

    public static function gamemobileidentcode($req)
    {
        return array('task', $req);
    }

    public static function regmobile($req)
    {
        return array('task', $req);
    }

    public static function resetpass($req)
    {
        return array('task', $req);
    }

    public static function authuser($req)
    {
        return array('task', $req);
    }

    public static function authguest($req)
    {
        return array('task', $req);
    }

    public static function auththird($req)
    {
        return array('task', $req);
    }

    public static function upgradeguest($req)
    {
        return array('task', $req);
    }

    public static function upgradeguest2wx($req)
    {
        return array('task', $req);
    }

    public static function authtoken($req)
    {
        return array('task', $req);
    }

    public static function t_reguser($req)
    {

        if(!isset($req['par']['recommrid']))
            $req['par']['recommrid'] = 0;
        if(!isset($req['par']['agencyrid']))
            $req['par']['agencyrid'] = 0;



        $ret = \user\reguser($req['par']['nextuserid'], $req['par']['username'], $req['par']['cpass'], $req['par']['randmobileid'], $req['par']['agencyrid']);
        if($ret[0]<0)
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['retmsg'];
        }
        else
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['ret'];
        }
        return array('send', $req);
    }

    //
    public static function t_newidentcode($req)
    {
        if(!isset($req['par']['isreg']))
            $req['par']['isreg'] = 1;
        $ret = \user\newidentcode($req['par']['mobilenum'], intval($req['par']['isreg']));
        if($ret[0]<0)
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['retmsg'];
        }
        else
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['ret'];
        }
        return array('send', $req);
    }

    public static function t_gamemobileidentcode($req)
    {
        if(!isset($req['par']['gametype']))
            $req['par']['gametype'] = 0;
        $ret = \user\gamemobileidentcode($req['par']['mobilenum'], intval($req['par']['gametype']));
        if($ret[0]<0)
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['retmsg'];
        }
        else
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['ret'];
        }
        return array('send', $req);
    }

    public static function t_regmobile($req)
    {
        global $glargv;
        if(!isset($req['par']['recommrid']))
            $req['par']['recommrid'] = 0;  //rid
        if(!isset($req['par']['channel']))
            $req['par']['channel'] = 0;

        //$gameid = 0; //gameid 删除
        /*if(!isset($req['par']['edition']))
            $gameid = 0;
        elseif( $req['par']['edition'] == 'texas')
            $gameid = 1;
        elseif( $req['par']['edition'] == 'doudizhu')
            $gameid = 2;*/


        //验证 identcode
        //$mobilenum = $req['par']['mobilenum'];
        /*$identcode = $req['par']['identcode'];  //无需验证邀请码
        $redisidentcode = $glargv['rediscon']->get($mobilenum. ':identcode');
        if(!DEBUG or substr($mobilenum, 5) != $identcode)
        {
            if($req['par']['identcode'] == $redisidentcode)
                $glargv['rediscon']->del($mobilenum. ':identcode');
            else
            {
                $req['reterr'] = -97;
                $req['retmsg'] = 'identcode_error';
                return array('send', $req);
            }
        }*/

        $ret = \user\reguser($req['par']['nextuserid'], $req['par']['mobilenum'], $req['par']['cpass'], $req['par']['randmobileid'], intval($req['par']['recommrid']), $req['par']['mobilenum']);
        if($ret[0]<0)
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['retmsg'];
        }
        else
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['ret'];
        }
        return array('send', $req);
    }


    public static function t_resetpass($req)
    {
        global $glargv;

        //验证 identcode
        $mobilenum = $req['par']['mobilenum'];
        $identcode = $req['par']['identcode'];
        $redisidentcode = $glargv['rediscon']->get($mobilenum. ':identcode');
        if(!DEBUG or substr($mobilenum, 5) != $identcode)
        {
            if($req['par']['identcode'] == $redisidentcode)
                $glargv['rediscon']->del($mobilenum. ':identcode');
            else
            {
                $req['reterr'] = -97;
                $req['retmsg'] = 'identcode_error';
                return array('send', $req);
            }
        }

        $ret = \user\resetpass($req['par']['username'], $req['par']['mobilenum'], $req['par']['cpass']);
        if($ret[0]<0)
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['retmsg'];
        }
        else
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['ret'];
        }
        return array('send', $req);
    }

    //传上来是用户名的明文, 加密后的cpass(存在文件里的,或者算出来的)
    public static function t_authuser($req)
    {
        $ret = \user\authuser($req['par']['username'], $req['par']['cpass']);
        if($ret[0]<0)
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['retmsg'];
        }
        else
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['ret'];
        }
        return array('send', $req);
    }

    public static function t_authguest($req)
    {
        global $glargv;

        // $ret =  array(-2, 'retmsg'=>'randmobileid_not_guest');
        $ret = \user\authguest($req['par']['randmobileid']);
        if($ret[0]<0)
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['retmsg'];
            return array('send', $req);
        }
        elseif($ret[0] == 0)
        {
            $req['cmd'] = 'regguest';
            return array('work', $req);//转给task操作了
        }
        else
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['ret'];
            return array('send', $req);
        }
    }
    //游客注册
    public static function t_regguest($req)
    {
        if(!isset($req['par']['reglocation']))
            $req['par']['reglocation'] = '';
        if(!isset($req['par']['platform']))
            $req['par']['platform'] = 1;
        if(!isset($req['par']['channel']))
            $req['par']['channel'] = 0;
        if(!isset($req['par']['regfrom']) or $req['par']['regfrom']==0)
            $req['par']['regfrom'] = intval($req['par']['channel']);

        $ret = \user\regguest($req['par']['nextuserid'], $req['par']['randmobileid']);
        if($ret[0]<0)
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['retmsg'];
        }
        else
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['ret'];
        }
        $req['cmd'] = 'authguest';
        unset($req['par']['nextuserid']);
        return array('send', $req);
    }

    /**
     * 注册入口***************************
     * @param $req
     * @return array
     *
     *
     */
    public static function t_auththird($req)  //验证入口 加入 dl 的 agencyrid
    {
        global $glargv;
        if(!isset($req['par']['unionid']))
            $req['par']['unionid'] = '';
        $ret = \user\auththird($req['par']['refresh_token'], $req['par']['unionid']);
        if($ret[0]<0) //错误信息
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['retmsg'];
            return array('send', $req);
        }
        elseif($ret[0] == 0)  //正常注册
        {
            $req['cmd'] = 'regthird'; //注册第三方    regthird 这个里面方法返回99
            return array('work', $req);//转给task操作了
        }
        else
        {
            $req['reterr'] = $ret[0];  //1  返回用户的基本信息111
            $req['retmsg'] = $ret['ret'];
            return array('send', $req);
        }
    }

    /**
     *
     * 没有注册
     * @param $req
     * @return array
     * 需要传一个推荐人的id   字段: recommrid 4位或者6位
     *
     */

    public static function t_regthird($req)
    {
        //如果有真实的unionid, 就用unionid作为用户名
        if(!isset($req['par']['channel']))
            $req['par']['channel'] = 0;
        if(!isset($req['par']['agencyrid'])){
            $req['par']['agencyrid'] = 0;
            $req['par']['agencylevel'] = 0;
        }
        if(!isset($req['par']['recommrid'])){
            $req['par']['recommrid'] = 0;
        }

        $ret = \user\regthird($req['par']['nextuserid'], $req['par']['unionid'], $req['par']['refresh_token'], $req['par']['authtype'], $req['par']['randmobileid'],$req['par']['recommrid'],$req['par']['agencyrid']);
        if($ret[0]<0)  //sql报错
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['retmsg'];
        }
        else
        {
            //$ret[0] = 99;  //用户注册成功后
            $req['reterr'] = $ret[0];//1  //返回参数确认99
            $req['retmsg'] = $ret['ret'];
        }
        $req['cmd'] = 'auththird';
        unset($req['par']['nextuserid']);        
        return array('send', $req);
    }

    //游客升级为正式手机帐号
    //需要新的用户名,密码,原来的游客帐号,原来密码密文
    public static function t_upgradeguest($req)
    {
        global $glargv;

        //验证 identcode
        $mobilenum = $req['par']['mobilenum'];
        $identcode = $req['par']['identcode'];
        $redisidentcode = $glargv['rediscon']->get($mobilenum. ':identcode');
        if(!DEBUG or substr($mobilenum, 5) != $identcode)
        {
            if($req['par']['identcode'] == $redisidentcode)
                $glargv['rediscon']->del($mobilenum. ':identcode');
            else
            {
                $req['reterr'] = -97;
                $req['retmsg'] = 'identcode_error';
                return array('send', $req);
            }
        }

        $ret = \user\upgradeguest($req['par']['mobilenum'], $req['par']['cpass'], $req['par']['oldusername'], $req['par']['oldcpass']);
        if($ret[0]<0)
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['retmsg'];
        }
        else
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['ret'];
        }
        return array('send', $req);
    }

    //游客升级为微信帐号
    //需要新的微信unionid,原来的游客帐号,原来密码密文
    public static function t_upgradeguest2wx($req)
    {
        global $glargv;

        $ret = \user\upgradeguest2wx($req['par']['unionid'], $req['par']['oldusername'], $req['par']['oldcpass']);
        if($ret[0]<0)
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['retmsg'];
        }
        else
        {
            $req['reterr'] = $ret[0];
            $req['retmsg'] = $ret['ret'];
        }
        return array('send', $req);
    }

    public static function t_authtoken($req)
    {
        $ret = \user\authtoken($req['par']['uid'], $req['par']['token']);
        $req['reterr'] = $ret[0];
        return array('send', $req);
    }

    //gm指令,获取数据库中的值, 传给task进程去做
    public static function t_gm_getdbdata($req)
    {
        global $glargv;
        global $gltable;

        $table = $req['par']['table'];
        $sql = 'select * from ' . $table . ' where rid=' . $req['par']['rid'] ;
        if(isset($req['par']['keyid']) and substr($table, 0, 2) == 'rm')
                $sql .= ' and keyid=' . $req['par']['keyid'];
        $result = $glargv['mysqlcon']->query($sql);
        $rows=array();
        if($result and $result->num_rows > 0)
        {
            $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
            foreach($rows as $row)
            {
                foreach($row as $k=>$v)
                {//从数据库中读出的都是字符串,需要根据变量的类型转换存入redis
                 //默认值是是数组,就要转成数组形式,但也有读取的是默认值,这个时候直接是数组
                    if(is_array($gltable[$table]['field'][$k]))
                        $row[$k] = safe_json_decode($v, true);
                    elseif(is_int($gltable[$table]['field'][$k]))
                        $row[$k] = intval($v);
                }
            }
        }
        $req['ret'] = $rows;
        return array('send', $req);
    }

    public static function t_checkconn($tm, $par=null)
    {
        checkconn($tm, $par=null);
    }

    public static function __callStatic($fname, $args)
    {
        slog('authsvr runcmd: ->' . $fname . '<- not exist.');
        return array('send', array('reterr'=>-100, 'retmsg'=>'cmd: ->' . $fname . '<- not exist.'));
    }
}

