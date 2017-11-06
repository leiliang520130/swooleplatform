<?PHP
//所有相关的include
//包括自定义的导入方法
if(file_exists('setting.debug.php'))
    include('setting.debug.php');
else
{
    define('DEBUG', 0);
    define('DEBUGSESSION', 0);
    define('SUPERUSERNAME', '');
}

include('globalconf.php');
include('globalargv.php');
//先引入配置文件,utils需要使用相关配置文件
include('libs/utils.php');
include('libs/opdata.php');
include('libs/topsdk/TopSdk.php');
require_once('libs/dysms/vendor/autoload.php');

//svrsvnver.php 文件在源代码中是没有的,是在发布的时候export出来生成的
if(file_exists('svrsvnver.php'))
    include('svrsvnver.php');
else
    $svrsvnver = 0;
$glargv['svrver']= $glargv['svrmainver'] . $svrsvnver;

//使用自定义函数导入
includedir('base');// 如导入 platform/base/basewssvr.php
includedir('baseweb');
includedirsuff('router', 'router');
includedir('module');

function includedir($dirname, $prefix='')
{
    $includefile = scandir($dirname);//升序排列
    foreach ($includefile as $k=>$v)
    {
        if($prefix ==='')
        {
            if(substr($v, -4) == '.php')
                include($dirname . '/' . $v);
        }
        else
        {
            //取到前缀 AND 的php文件
            if( (substr($v, 0, strlen($prefix)) == $prefix) and (substr($v, -4) == '.php'))
                include($dirname . '/' . $v);
        }
    }
}

//include某目录下某个后缀的所有文件
function includedirsuff($dirname, $suffix='')
{
    $includefile = scandir($dirname);
    foreach ($includefile as $k=>$v)
    {
        //取到后缀为 (如router)的php文件
        if( substr($v, -(strlen($suffix)+4)) === $suffix . '.php')
            //slog('----------------------------------------------'.$dirname . '/' . $v);
            include($dirname . '/' . $v);
    }
}
