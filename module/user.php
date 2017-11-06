<?PHP
/*

ALTER TABLE `user` CHANGE COLUMN `cpass` `cpass` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '' COMMENT '这里是客户端穿上来的md5的密码, (真实用户不存储)', ADD COLUMN `saltpass` varchar(32) NOT NULL DEFAULT '' COMMENT '加密后的密码(md5(uname+md5(upass))' AFTER `cpass`;

update user set `saltpass`=md5(CONCAT(`username`, `cpass`));

2017.3.28 增加一个gameid字段，获得从哪个游戏注册来的信息
1，德州
2，斗地主
alter table user add gameid int(11) NOT NULL DEFAULT '1' comment '注册游戏的id';
*/

namespace user;
//阿里大于验证码命名空间
use Aliyun\Core\Config;
use Aliyun\Core\Profile\DefaultProfile;
use Aliyun\Core\DefaultAcsClient;
use Aliyun\Api\Sms\Request\V20170525\SendSmsRequest;
use Aliyun\Api\Sms\Request\V20170525\QuerySendDetailsRequest;


/**
 * Class SmsDemo
 *
 * @property \Aliyun\Core\DefaultAcsClient acsClient
 */
class SmsDemo
{

    /**
     * 构造器
     *
     * @param string $accessKeyId 必填，AccessKeyId
     * @param string $accessKeySecret 必填，AccessKeySecret
     */
    public function __construct($accessKeyId, $accessKeySecret)
    {

        // 短信API产品名
        $product = "Dysmsapi";

        // 短信API产品域名
        $domain = "dysmsapi.aliyuncs.com";

        // 暂时不支持多Region
        $region = "cn-hangzhou";

        // 服务结点
        $endPointName = "cn-hangzhou";

        // 初始化用户Profile实例
        $profile = DefaultProfile::getProfile($region, $accessKeyId, $accessKeySecret);

        // 增加服务结点
        DefaultProfile::addEndpoint($endPointName, $region, $product, $domain);

        // 初始化AcsClient用于发起请求
        $this->acsClient = new DefaultAcsClient($profile);
    }

    /**
     * 发送短信范例
     *
     * @param string $signName <p>
     * 必填, 短信签名，应严格"签名名称"填写，参考：<a href="https://dysms.console.aliyun.com/dysms.htm#/sign">短信签名页</a>
     * </p>
     * @param string $templateCode <p>
     * 必填, 短信模板Code，应严格按"模板CODE"填写, 参考：<a href="https://dysms.console.aliyun.com/dysms.htm#/template">短信模板页</a>
     * (e.g. SMS_0001)
     * </p>
     * @param string $phoneNumbers 必填, 短信接收号码 (e.g. 12345678901)
     * @param array|null $templateParam <p>
     * 选填, 假如模板中存在变量需要替换则为必填项 (e.g. Array("code"=>"12345", "product"=>"阿里通信"))
     * </p>
     * @param string|null $outId [optional] 选填, 发送短信流水号 (e.g. 1234)
     * @return stdClass
     */
    public function sendSms($signName, $templateCode, $phoneNumbers, $templateParam = null, $outId = null) {

        // 初始化SendSmsRequest实例用于设置发送短信的参数
        $request = new SendSmsRequest();

        // 必填，设置雉短信接收号码
        $request->setPhoneNumbers($phoneNumbers);

        // 必填，设置签名名称
        $request->setSignName($signName);

        // 必填，设置模板CODE
        $request->setTemplateCode($templateCode);

        // 可选，设置模板参数
        if($templateParam) {
            $request->setTemplateParam(json_encode($templateParam));
        }

        // 可选，设置流水号
        if($outId) {
            $request->setOutId($outId);
        }

        // 发起访问请求
        $acsResponse = $this->acsClient->getAcsResponse($request);

        // 打印请求结果
        // var_dump($acsResponse);

        return $acsResponse;

    }

    /**
     * 查询短信发送情况范例
     *
     * @param string $phoneNumbers 必填, 短信接收号码 (e.g. 12345678901)
     * @param string $sendDate 必填，短信发送日期，格式Ymd，支持近30天记录查询 (e.g. 20170710)
     * @param int $pageSize 必填，分页大小
     * @param int $currentPage 必填，当前页码
     * @param string $bizId 选填，短信发送流水号 (e.g. abc123)
     * @return stdClass
     */
    public function queryDetails($phoneNumbers, $sendDate, $pageSize = 10, $currentPage = 1, $bizId=null) {

        // 初始化QuerySendDetailsRequest实例用于设置短信查询的参数
        $request = new QuerySendDetailsRequest();

        // 必填，短信接收号码
        $request->setPhoneNumber($phoneNumbers);

        // 选填，短信发送流水号
        $request->setBizId($bizId);

        // 必填，短信发送日期，支持近30天记录查询，格式Ymd
        $request->setSendDate($sendDate);

        // 必填，分页大小
        $request->setPageSize($pageSize);

        // 必填，当前页码
        $request->setCurrentPage($currentPage);

        // 发起访问请求
        $acsResponse = $this->acsClient->getAcsResponse($request);

        // 打印请求结果
        // var_dump($acsResponse);

        return $acsResponse;
    }

}


//获得rid应该的最大值,可以直接在程序中使用
//分别从数据库和redis中获取.以最大值为准
function getnextuserid($rediscon, $mysqlcon)
{
    $mmax = $rmax = 0;
    $result = $mysqlcon->query('show table status');
    while($row = mysqli_fetch_assoc($result))//关联数组
    {
        if(($row['Name']) == 'user')
        {
            $mmax = intval($row['Auto_increment']);
            break;
        }
        else
            continue;
    }

    $rmax = intval($rediscon->get('nextuserid'));
    if($rmax>=$mmax)
        return max(12345, $rmax);
    else
    {
        $rediscon->set('nextuserid', $mmax);
        return max(12345, $mmax);
    }
}

//普通注册的时候, 生成一个 $randmobileid = '*'+$uid, 因为 * 会被safeascii() 过滤掉, 这样不会被regguest()利用
//这里的cpass穿上的是md5的密码
//($req['par']['nextuserid'], $req['par']['mobilenum'], $req['par']['cpass'], $req['par']['randmobileid'], intval($req['par']['recommrid']), $req['par']['mobilenum'],  $gameid);
function reguser($uid, $username, $cpass, $regmobileid, $recommrid=0, $agencyrid=0)
{
    global $glargv;
    
    $username = strtolower(substr(safeascii($username), 0 , 32));
    if(strlen($username) < 6 or strlen($username)> 30)
        return array(-1, 'retmsg'=>'error_length_username');
    if(!preg_match('/^[a-z]{1}[a-z0-9\-\.\@]{5,30}$/', $username) and !filter_var($username, FILTER_VALIDATE_EMAIL) and !preg_match("/^1[0-9]{10}$/", $username))
        return array(-2, 'retmsg'=>'error_username');
    $sql = 'select * from user where `username`="' . $username . '"';
    $result = $glargv['mysqlcon']->query($sql);
    if($result->num_rows != 0)
        return array(-3, 'retmsg'=>'username_had_reged');
    $randmobileid = '*' . $uid;
    $sql = sprintf('
insert into user (`uid`, `authtype`, `username`, `cpass`, `saltpass`, `guestmobileid`, `regmobileid`,  `createtime`, `recommrid`, `agencyrid`) 

values (%d, %d,  "%s", "%s", "%s", "%s", "%s", "%s", "%d", "%d")',
        $uid,1, $username, 'cpass', md5($username . $cpass), $randmobileid, $regmobileid, time(), $recommrid, $agencyrid);
    $result = $glargv['mysqlcon']->query($sql);
    if($result)
    {
        $token = debugtoken($uid);
        $glargv['rediscon']->set($uid. ':token', $token, array('ex'=>120));
        return array(
            1,
            'ret'=>array('uid'=>intval($uid),
                         'authtype'=>1,
                         'token'=>$token,
                         'username'=>$username,
                         'cpass'=>$cpass

            )
        );
    }
    else
        return array(-3, 'retmsg'=>'create_user_error');
}


function newidentcode($mobilenum, $isreg=1)
{
    global $glargv;

    $sql = 'select * from user where `username`="' . $mobilenum . '"';
    $result = $glargv['mysqlcon']->query($sql);
    if($result->num_rows == 0 and $isreg == 0)
        return array(-3, 'retmsg'=>'mobilenum_not_reged');
    if($result->num_rows != 0 and $isreg == 1)
        return array(-4, 'retmsg'=>'mobilenum_had_reged');

    $identcode = randidentcode();
    $c = new \TopClient;
    $c->appkey = 23302173;
    $c->secretKey = 'f15cdd9e34f91efacd45768906db5bf1';
    $c->format = 'json';
    $req = new \AlibabaAliqinFcSmsNumSendRequest;
    // $req->setExtend("123456");
    $req->setSmsType("normal");
    $req->setSmsFreeSignName("聚众朋友桌");
    $req->setSmsParam('{"msg":"'. $identcode . '"}');
    $req->setRecNum($mobilenum);
    $req->setSmsTemplateCode("SMS_6010003");
    $resp = $c->execute($req);
    $retcode = -1;
    $retmsg = 'send_sms_error';
    if(property_exists($resp, 'result'))
    {
        if($resp->result->err_code == 0)
        {
            $retcode = 1;
            $glargv['rediscon']->set($mobilenum. ':identcode', $identcode, array('ex'=>300));
        }
    }
    else
    {
        $retmsg = 'sms_error_001';
        $retcode = -96;
        if(property_exists($resp, 'code'))
            $retcode = 0-$resp->code;
        if(property_exists($resp, 'sub_code'))
            $retmsg = 'sms_error_' . $resp->sub_code;
    }
    if($retcode == 1)
        return array(1, 'ret'=>array());
    else
        return array($retcode, 'retmsg'=>$retmsg);
}

//游戏需要的手机验证码
//直接写到游戏的redis-svr中， gametype=0，德州
//做短链接的reids
function gamemobileidentcode($mobilenum, $gametype=0)
{
    global $glconf;
    global $glargv;

    if(!isset($glconf['gameredissvr'][$gametype]))
        return array(-96, 'retmsg'=>'sms_error_gameredis_config');
    $gamerediscon= new \Redis();
    if(!$gamerediscon->connect(
        $glconf['gameredissvr'][$gametype]['host'],
        $glconf['gameredissvr'][$gametype]['port'],
        2))
        return array(-96, 'retmsg'=>'sms_error_gameredis_connect');
    $gamerediscon->select($glconf['gameredissvr'][$gametype]['dbnum']);

    $identcode = randidentcode();

    //新版阿里大于短信验证码发送

    Config::load();

    header('Content-Type: text/plain; charset=utf-8');
    $demo = new SmsDemo(
        "LTAIJrwD6txTECga",
        "pRziKCpzghKxcTg05hlsimIdmwRiji"
    );
    //echo "SmsDemo::sendSms\n";
    $resp = $demo->sendSms(
        "金鱼互动", // 短信签名
        "SMS_105345045", // 短信模板编号
        "$mobilenum", // 短信接收者
        Array(  // 短信模板中字段的值
            "code"=>"$identcode",
            //"product"=>"dsd"
        )
    );


    /*$c = new \TopClient;
    $c->appkey = 23302173;
    $c->secretKey = 'f15cdd9e34f91efacd45768906db5bf1';
    $c->format = 'json';
    $req = new \AlibabaAliqinFcSmsNumSendRequest;
    // $req->setExtend("123456");
    $req->setSmsType("normal");
    $req->setSmsFreeSignName("聚众朋友桌");
    $req->setSmsParam('{"msg":"'. $identcode . '"}');
    $req->setRecNum($mobilenum);
    $req->setSmsTemplateCode("SMS_6010003");
    $resp = $c->execute($req);*/
    $retcode = -1;
    $retmsg = 'send_sms_error';

    if($resp->Message == 'OK')
    {
        $retcode = 1;
        $gamerediscon->set($mobilenum. ':identcode', $identcode, array('ex'=>600));
    }else{
        $retmsg = 'sms_error_001';
        $retcode = -96;
        //if(property_exists($resp, 'code'))
            //$retcode = -96;
        //if(property_exists($resp, 'sub_code'))
            $retmsg = 'sms_error_' . $resp->Message;
    }
    $gamerediscon->close();
    if($retcode == 1)
        return array(1, 'ret'=>array());
    else
        return array($retcode, 'retmsg'=>$retmsg);
}

//现在只有手机注册用户能修改密码， 也就是这里的username==mobilenum
function resetpass($username, $mobilenum, $cpass)
{
    global $glargv;
    
    $sql = 'select * from user where `username`="' . $username . '"';
    $result = $glargv['mysqlcon']->query($sql);
    if($result->num_rows == 0)
        return array(-3, 'retmsg'=>'username_not_exist');
    $row = mysqli_fetch_assoc($result);
    if($username != $mobilenum)
        return array(-4, 'retmsg'=>'username_mobilenum_error');

    $sql = sprintf('update user set `saltpass`="%s" where `username`="%s"', md5($username . $cpass), $username);
    $result = $glargv['mysqlcon']->query($sql);
    if($result)
        return array(1, 'ret'=>array('retmsg'=>'resetpass_ok'));
    else
        return array(-5, 'retmsg'=>'resetpass_error');
}

function authuser($username, $cpass)
{
    global $glargv;
    
    $username = substr(safeascii($username), 0 , 32);
    if(!preg_match('/^[a-z]{1}[a-z0-9\-\.\@]{5,30}$/', $username) and !filter_var($username, FILTER_VALIDATE_EMAIL) and !preg_match("/^1[0-9]{10}$/", $username) and !substr($username, 0, 2) == '_g')
        return array(-2, 'retmsg'=>'error_username');
    $cpass = strtolower(substr($cpass, 0, 32));

    $sql = 'select * from user where `username`="' . $username . '"';
    $result = $glargv['mysqlcon']->query($sql);
    if($result->num_rows == 0)
        return array(-3, 'retmsg'=>'username_not_exist');
    $row = mysqli_fetch_assoc($result);
    if($row['saltpass'] != md5($username . $cpass))
        return array(-4, 'retmsg'=>'password_error');
    if($row['isblock'] != 0)
        return array(-7, 'retmsg'=>'user_isblock');
    $uid = $row['uid'];
    //如果是超级用户, 并且有newuid这个文件, 则用newuid去登录游戏
    if( SUPERUSERNAME and $username == SUPERUSERNAME  and file_exists('newuid.debug.php'))
    {
        $newuid = 0;
        //include 这个文件, 对newuid重新赋值
        include('newuid.debug.php');
        // var_dump($newuid);
        if($newuid>0)
        {            
            $uid = $newuid;
            $sql = 'select * from user where `uid`=' . $uid ;
            $result = $glargv['mysqlcon']->query($sql);
            if($result->num_rows == 0)
                return array(-3, 'retmsg'=>'debug_newuid_not_exist');
            $row = mysqli_fetch_assoc($result);
        }
    }
    //生成一个token 写到redis上
    $token = debugtoken($uid);
    $glargv['rediscon']->set($uid. ':token', $token, array('ex'=>120));
    return array(1, 'ret'=>array('uid'=>intval($uid), 'authtype'=>intval($row['authtype']), 'token'=>$token, 'regfrom'=>$row['regfrom']));
}

//$randmobileid, 手机客户端生成传上来的, 可能每次不一样,也可能就是表示硬件的唯一编码
function authguest($randmobileid)
{
    global $glargv;
    
    $randmobileid = safeascii($randmobileid);
    if(strlen($randmobileid) < 8 or strlen($randmobileid)> 32)
        return array(-1, 'retmsg'=>'error_format_randmobileid : ' . $randmobileid);
    $sql = 'select * from user where `guestmobileid`="' . $randmobileid . '"';
    $result = $glargv['mysqlcon']->query($sql);
    if($result->num_rows == 0)
        return array(0); //没有这个guest, 转到注册去
    //如果已经注册过游客了,就把这些数据返回给客户端,客户端拿来登录
    $row = mysqli_fetch_assoc($result);
    if(substr($row['username'], 0, 2) != '_g')
        return array(-2, 'retmsg'=>'randmobileid_not_guest');
    if($row['isblock'] != 0)
        return array(-7, 'retmsg'=>'user_isblock');
    //生成一个token 写到redis上
    $uid = $row['uid'];
    $token = debugtoken($uid);
    $glargv['rediscon']->set($uid. ':token', $token, array('ex'=>120));
    return array(1, 'ret'=>array('uid'=>intval($row['uid']), 'authtype'=>intval($row['authtype']), 'token'=>$token,
                                'username'=>$row['username'], 'cpass'=>$row['cpass'], 'regfrom'=>$row['regfrom']));
}

function regguest($uid, $randmobileid)
{
    global $glargv;

//if($regfrom !=1 and $regfrom!=5 and $regfrom!=6) return array(-4, 'retmsg'=>'reg_guest_error_android');
    $username = '_g' . $uid;
    $cpass = md5(setsession($uid));
    $sql = sprintf('insert into user (`uid`, `authtype`, `username`, `cpass`, `saltpass`, `guestmobileid`, `regmobileid`,  `createtime`) values (%d, %d, "%s", "%s", "%s", "%s", "%s", %d)',
        $uid, 0, $username, $cpass, md5($username . $cpass), $randmobileid, $randmobileid, time());
    $result = $glargv['mysqlcon']->query($sql);
    if($result)
    {
        $token = debugtoken($uid);
        $glargv['rediscon']->set($uid. ':token', $token, array('ex'=>120));
        return array(1, 'ret'=>array('uid'=>intval($uid),  'authtype'=>0, 'token'=>$token,
                                     'username'=>$username, 'cpass'=>$cpass, 'agencyrid'=>0,
                                     'recommrid'=>0));
    }
    else
        return array(-3, 'retmsg'=>'reg_guest_error');
}

//oauthid, 已经不用呢了，就直接用unionid
function auththird($refresh_token, $unionid)
{
    global $glargv;
    $sql = 'select * from user where `username`="' . $unionid . '"';
    $result = $glargv['mysqlcon']->query($sql);
    if($result->num_rows == 0) //没有查到unionid, 就只有使用 oauthid 去查询用户
    {
        return array(0);

    }

    //如果已经注册过角色了,就把这些数据返回给客户端,客户端拿来登录
    $row = mysqli_fetch_assoc($result);
    //得到 authtype, 然后去不同的第三方验证
    //@todo : 去验证 $refresh_token, 验证通过, 把这个refrestoke 写到 redis中(这里进行验证) (重新登录问题)
    //微信是: https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=APPID&grant_type=refresh_token&refresh_token=REFRESH_TOKEN
    // APPID :  wxd1789ff94e18ace7
    if($row['isblock'] != 0)
        return array(-7, 'retmsg'=>'user_isblock');
    $uid = $row['uid'];
    $token = debugtoken($uid); //截取一个字符串的头6位str6    , 然后str6 + substr(md5(str+ salt), 6)
    $glargv['rediscon']->set($uid. ':token', $token, array('ex'=>120));
    $glargv['rediscon']->set($uid. ':refresh_token', $refresh_token);

    //在这里判断用户注册后如果agencyrid 为0的话需要修改

    //已经注册的用户返回99
    return array(99, 'ret'=>array('uid'=>intval($row['uid']), 'authtype'=>intval($row['authtype']), 'token'=>$token,
                                  'username'=>$row['username'], 'cpass'=>$row['cpass'],
                                  'agencyrid'=>$row['agencyrid'],
                                  'recommrid'=>$row['recommrid'],'createtime'=>$row['createtime']));  //增加返回agencyrid recommrid 字段
}

//基本等于正常的 reguser
function regthird($uid, $username, $refresh_token, $authtype, $randmobileid, $recommrid, $agencyrid)
{
    global $glargv;
    //根据authtype去验证 refresh_token 是否成功(不做)
    //这里的 username , 有可能是oauthid, 也可能是unionid
    $authtype = intval($authtype);
    $cpass = md5(setsession($uid));
    $sql = sprintf('insert into user (`uid`, `authtype`, `username`, `cpass`, `saltpass`, `guestmobileid`, `regmobileid`, `createtime`, `recommrid`, `agencyrid`) values (%d, %d, "%s", "%s", "%s", "%s", "%s", "%s", "%s", "%s")',
        $uid, $authtype, $username, $cpass, md5($username . $cpass), '*' . $uid, $randmobileid,  time(), $recommrid, $agencyrid);
    $result = $glargv['mysqlcon']->query($sql);
    if($result)
    {
        //$row = mysqli_fetch_assoc($result);
        $token = debugtoken(setsession($uid));
        $glargv['rediscon']->set($uid. ':token', $token, array('ex'=>120));
        $glargv['rediscon']->set($uid. ':refresh_token', $refresh_token);
        return array(1, 'ret'=>array('uid'=>intval($uid),  'authtype'=>$authtype, 'token'=>$token,
                                    'username'=>$username, 'cpass'=>$cpass, 'agencyrid'=>$agencyrid, 'recommrid'=>$recommrid));
    }
    else
        return array(-3, 'retmsg'=>'reg_third_error');
}

//游客升级成正式帐号
//username  就是手机号
function upgradeguest($username, $cpass, $oldusername, $oldcpass)
{
    global $glargv;
    
    $oldusername = strtolower(substr(safeascii($oldusername), 0 , 32));
    if(substr($oldusername, 0, 2) != '_g')
        return array(-1, 'retmsg'=>'no_guest');

    if(strlen($username) < 6 or strlen($username)> 30)
        return array(-1, 'retmsg'=>'error_length_username');
    if(!preg_match('/^[a-z]{1}[a-z0-9\-\.\@]{5,30}$/', $username) and !filter_var($username, FILTER_VALIDATE_EMAIL) and !preg_match("/^1[0-9]{10}$/", $username))
        return array(-2, 'retmsg'=>'error_username');

    //先验证guest 的密码正确
    $sql = 'select * from user where `username`="' . $oldusername . '"';
    $result = $glargv['mysqlcon']->query($sql);
    if($result->num_rows == 0)
        return array(-4, 'retmsg'=>'guest_not_exist');
    $row = mysqli_fetch_assoc($result);
    if($row['saltpass'] != md5($oldusername . $oldcpass))
        return array(-5, 'retmsg'=>'guestpassword_error');

    $uid = intval($row['uid']);
    $randmobileid = '*' . $uid;

    //再查看新的用户知否已经注册
    $sql = 'select * from user where `username`="' . $username . '"';
    $result = $glargv['mysqlcon']->query($sql);
    if($result->num_rows != 0)
        return array(-6, 'retmsg'=>'username_had_reged');

    $sql = sprintf('update user set authtype=1, `username`="%s", `cpass`="%s",  `saltpass`="%s", `guestmobileid`="%s", `bindmobilenum`="%s" where uid=%d', 
        $username, 'cpass', md5($username . $cpass), $randmobileid, $username, $uid);
    $result = $glargv['mysqlcon']->query($sql);
    if($result)
        return array(1, 'ret'=>array('uid'=>intval($uid), 'username'=>$username, 'cpass'=>$cpass, 'authtype'=>1));
    else
        return array(-3, 'retmsg'=>'upgradeguest_error');
}

//游客升级成微信
//username  就是微信的uniond
function upgradeguest2wx($username, $oldusername, $oldcpass)
{
    global $glargv;
    
    $oldusername = strtolower(substr(safeascii($oldusername), 0 , 32));
    if(substr($oldusername, 0, 2) != '_g')
        return array(-1, 'retmsg'=>'no_guest');

    if(strlen($username) < 6 or strlen($username)> 32)
        return array(-1, 'retmsg'=>'error_length_username');
    //先验证guest 的密码正确
    $sql = 'select * from user where `username`="' . $oldusername . '"';
    $result = $glargv['mysqlcon']->query($sql);
    if($result->num_rows == 0)
        return array(-4, 'retmsg'=>'guest_not_exist');
    $row = mysqli_fetch_assoc($result);
    if($row['saltpass'] != md5($oldusername . $oldcpass))
        return array(-5, 'retmsg'=>'guestpassword_error');

    $uid = intval($row['uid']);
    $randmobileid = '*' . $uid;
    $cpass = md5(setsession($uid));

    //再查看新的用户知否已经注册
    $sql = 'select * from user where `username`="' . $username . '"';
    $result = $glargv['mysqlcon']->query($sql);
    if($result->num_rows != 0)
        return array(-6, 'retmsg'=>'weixin_had_reged');

    $sql = sprintf('update user set authtype=2, `username`="%s", `cpass`="%s",  `saltpass`="%s", `guestmobileid`="%s", `bindmobilenum`="%s" where uid=%d', 
        $username, $cpass, md5($username . $cpass), $randmobileid, $username, $uid);
    $result = $glargv['mysqlcon']->query($sql);
    if($result)
        return array(1, 'ret'=>array('uid'=>intval($uid), 'username'=>$username, 'cpass'=>$cpass, 'authtype'=>1));
    else
        return array(-3, 'retmsg'=>'upgradeguest2wx_error');
}

//游戏服的rolesvr来调用的, 直接从redis里面获得验证
function authtoken($uid, $token)
{
    global $glargv;
    
    $uid = intval($uid);
    if($glargv['rediscon']->get($uid.':token') === $token)
        return array(1);
    else
        return array(-1);
}

//token 要带入时间因字，uid，这样token才是唯一有效的
//10位时间， 9位rid， 后面13位签名,token长度总共32
//类似：1495540768000000002afeb435eb5e8c
/*function debugtoken($uid, $salt='jyhd')
{
    $t = time();
    return sprintf('%010d', $t) . sprintf('%09d', $uid) . substr(md5(sprintf('%010d', $t) . sprintf('%09d', $uid) . $salt), 0, 13);
}*/
function debugtoken($uid, $salt='p2l_salt')
{
    $tokentime = time();
    return md5($uid.$tokentime.$salt).$tokentime;
}
