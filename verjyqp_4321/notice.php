<?PHP
$notice = array(
        'doudizhu'=>array(  //内网测试, 以后发布出去
                'subject'=>'test subject',
                'content'=> <<<EOF
          xx0.1.10.xxxx
EOF
, 
                'button'=>'ok',
        ),
);

/*
include('xtea.php');
$xxtea = new XxTea();
$xxteakey = 'cd_develop';

//对白名单做加密处理
foreach ($notice as $key => $value) {
    foreach(array('presentwhitelist', 'mttchannelwhitelist', 'twosngwhitelist', 'logowhitelist') as $i => $wl)
    {
        if(isset($value[$wl]))
        {
            var_dump($value);
            var_dump($wl);
            $notice[$key][$wl.'str'] = $xxtea->encrypt(json_encode($value[$wl], JSON_UNESCAPED_UNICODE), $xxteakey, true);
            // unset($notice[$key][$wl]);
        }
    }
}

var_dump($notice);
*/
