<?php
require __DIR__ . "/wssyncclient.php";
$host = '127.0.0.1';
$prot = 9088;

$client = new WsSyncClient($host, $prot);
$client->connect();

//$req = array('cmd'=>'gamemobileidentcode', 'par'=>array('mobilenum'=>18575501659, 'gametype'=>0));,
//$req = array('cmd'=>'gamemobileidentcode', 'par'=>array('mobilenum'=>17358523776, 'gametype'=>0));
//$req = array('cmd'=>'gamemobileidentcode', 'par'=>array('mobilenum'=>17098331257, 'gametype'=>0));

//$req = array('cmd'=>'newidentcode', 'par'=>array('mobilenum'=>18575501659, 'isreg'=>1));
//$req = array('cmd'=>'regmobile', 'par'=>array('mobilenum'=>18575501659, 'cpass'=>'d8578edf8458ce06fbc5bb76a58c5ca4', 'randmobileid'=>'1234567894'));
// $req = array('cmd'=>'resetpass', 'par'=>array('username'=>18161299036, 'mobilenum'=>18161299036, 'cpass'=>'99999', 'identcode'=>'123456', 'regfrom'=>123));
//$req = array('cmd'=>'reguser', 'par'=>array('username'=>'15000000000', 'cpass'=>'88888888', 'randmobileid'=>'15000000000'));
// $req = array('cmd'=>'authguest', 'par'=>array('randmobileid'=>'876587658734', 'platform'=>2, 'regfrom'=>3));
$req = array('cmd'=>'auththird', 'par'=>array('authtype'=>2, 'refresh_token'=>'cccccc', 'randmobileid'=>'123456789' ,'unionid'=>'', 'agencyrid'=>'10001'));
// $req = array('cmd'=>'authuser', 'par'=>array('username'=>'union2', 'cpass'=>'9d42b0ab6622cbf8f7a5ca1ba2c854e8'));
// $req = array('cmd'=>'upgradeguest', 'par'=>array('mobilenum'=>'18576501658', 'cpass'=>'123456ge', 'identcode'=>'501658', 'oldusername'=>'_g13536', 'oldcpass'=>'8f4096e036cfd862d0ec626aca3c0177')); ////游客升级为正式手机帐号
// $req = array('cmd'=>'authtoken', 'par'=>array('randmobileid'=>'1234567894'));

$client->send(json_encode($req));
$tmp = $client->recv();
var_dump($tmp);
