<?PHP
//wsgate2core 链接接收到的所有命令路由
//基本上都是转发

class W2CRouter
{
    public static function __callStatic($fname, $args)
    {
        if(in_array($fname, array('reguser', 'newidentcode', 'gamemobileidentcode', 'regmobile', 'resetpass',  'authuser', 'authguest', 'auththird', 'upgradeguest', 'upgradeguest2wx', 'restpass')))
            return array('sendufd', $args[0]);
        else
        {
            slog('w2ccon runcmd: ->' . $fname . '<- not exist.');
            return array('nothing', 'cmd: ->' . $fname . '<- not exist.');
        }
    }

    public static function connect($req)
    {
        slog('w2ccon runcmd: connect coresvr ok.');
        return array('nothing', 'cmd: connect coresvr ok.');
    }
}