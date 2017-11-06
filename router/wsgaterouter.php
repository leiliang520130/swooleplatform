<?PHP
//wsgatesvr接收到的的所有命令路由
//基本上都是转发

class WSGateRouter
{
    public static function __callStatic($fname, $args)
    {
        //本来都可以用转发, 但主要还是要去判断参数合法性, 需要一个个来做
        // if(in_array($fname, array('reguser', 'authuser', 'authguest', 'auththird', 'upgradeguest', 'restpass')))
        //     return array('core', $args[0]);
        // else
        {
            slog('wsgatesvr runcmd: ->' . $fname . '<- not exist.');
            //以后可以考虑不返回任何消息给客户端
            return array('send', array('reterr'=>-100, 'retmsg'=>'cmd: ->' . $fname . '<- not exist.'));
        }
    }

    public static function reguser($req)  
    {
        if(checkreqpar($req, array('username', 'cpass', 'randmobileid')) === false)
        {
            $req['reterr'] = -99;
            $req['retmsg'] = 'no_username_cpass_randmobileid';
            return array('send', $req);
        }
        //检查用户名: username 只允许因为英文, 数字, 下划线
        $username = $req['par']['username'];
        $safename = substr(safeascii($username), 0 , 32);
        if($username !== $safename)
        {
            $req['reterr'] = -98;
            $req['retmsg'] = 'username_illegal';
            return array('send', $req);
        }
        return array('core', $req);
    }

    //获取手机验证码
    public static function newidentcode($req)  
    {
        if(checkreqpar($req, array('mobilenum')) === false)
        {
            $req['reterr'] = -99;
            $req['retmsg'] = 'no_mobilenum';
            return array('send', $req);
        }
        //检查手机号码
        if(!preg_match("/^1[0-9]{10}$/", $req['par']['mobilenum']))
        {
            $req['reterr'] = -98;
            $req['retmsg'] = 'mobilenum_illegal';
            return array('send', $req);
        }
        return array('core', $req);
    }

    //获取手机验证码，写入游戏的redissvr，给游戏使用
    public static function gamemobileidentcode($req)  
    {
        if(checkreqpar($req, array('mobilenum')) === false)
        {
            $req['reterr'] = -99;
            $req['retmsg'] = 'no_mobilenum';
            return array('send', $req);
        }
        //检查手机号码
        if(!preg_match("/^1[0-9]{10}$/", $req['par']['mobilenum']))
        {
            $req['reterr'] = -98;
            $req['retmsg'] = 'mobilenum_illegal';
            return array('send', $req);
        }
        return array('core', $req);
    }

    //手机号注册用户
    public static function regmobile($req)  
    {
        if(checkreqpar($req, array('mobilenum', 'cpass', 'identcode', 'randmobileid')) === false)
        {
            $req['reterr'] = -99;
            $req['retmsg'] = 'no_mobilenum_cpass_identcode_randmobileid';
            return array('send', $req);
        }
        //检查手机号码
        if(!preg_match("/^1[0-9]{10}$/", $req['par']['mobilenum']))
        {
            $req['reterr'] = -98;
            $req['retmsg'] = 'mobilenum_illegal';
            return array('send', $req);
        }
        return array('core', $req);
    }

    //重置密码
    public static function resetpass($req)  
    {
        if(checkreqpar($req, array('username', 'mobilenum', 'cpass', 'identcode')) === false)
        {
            $req['reterr'] = -99;
            $req['retmsg'] = 'no_username_mobilenum_cpass_identcode';
            return array('send', $req);
        }
        //检查手机号码
        if(!preg_match("/^1[0-9]{10}$/", $req['par']['mobilenum']))
        {
            $req['reterr'] = -98;
            $req['retmsg'] = 'mobilenum_illegal';
            return array('send', $req);
        }
        return array('core', $req);
    }

    public static function authuser($req)  
    {
        if(checkreqpar($req, array('username', 'cpass')) === false)
        {
            $req['reterr'] = -99;
            $req['retmsg'] = 'no_user_cpass';
            return array('send', $req);
        }
        return array('core', $req);
    }

    //选择游客登录, 下面有两种可能, 一种是注册,一种是把老的数据推给客户端
    public static function authguest($req)  
    {
        if(checkreqpar($req, array('randmobileid')) === false)
        {
            $req['reterr'] = -99;
            $req['retmsg'] = 'no_randmobileid';
            return array('send', $req);
        }
        return array('core', $req);
    }

    //选择第三方登录, 下面有两种可能, 一种是注册,一种是把老的数据推给客户端
    public static function auththird($req)  
    {
        if(checkreqpar($req, array('unionid', 'refresh_token')) === false)
        {
            $req['reterr'] = -99;
            $req['retmsg'] = 'unionid,refresh_token is not null';
            return array('send', $req);
        }
        if(empty($req['par']['unionid'])){
            $req['reterr'] = -99;
            $req['retmsg'] = 'unionid is not null';
            return array('send', $req);
        }
        return array('core', $req);
    }

    //这个接口不用了
    public static function upgradeguest($req)  
    {
        if(checkreqpar($req, array('mobilenum', 'cpass', 'oldusername', 'oldcpass', 'identcode')) === false)
        {
            $req['reterr'] = -99;
            $req['retmsg'] = 'no_mobilenum_cpass_oldusername_oldcpass_identcode';
            return array('send', $req);
        }
        return array('core', $req);
    }

    public static function upgradeguest2wx($req)  
    {
        if(checkreqpar($req, array('unionid', 'oldusername', 'oldcpass')) === false)
        {
            $req['reterr'] = -99;
            $req['retmsg'] = 'no_unionid_oldusername_oldcpass';
            return array('send', $req);
        }
        return array('core', $req);
    }

    //这个接口不用了, 验证token采用的是算法
    public static function authtoken($req)
    {
        $ret = \user\authtoken($req['par']['uid'], $req['par']['token']);
        $req['reterr'] = $ret[0];
        return array('send', $req);
    }
}
