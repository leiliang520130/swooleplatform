<?PHP
function getfilemime($suffix)
{
	$filemimes=array(
	'jpg' => 'image/jpeg',
    'bmp' => 'image/bmp',
    'ico' => 'image/x-icon',
    'gif' => 'image/gif',
    'png' => 'image/png',
    'svg' => 'image/svg+xml',
    'js'  => 'application/javascript',
    'css' => 'text/css',
    'htm' => 'text/html',
    'html'=> 'text/html',
    'txt' => 'text/html',
    'xml' => 'text/xml',
    'ttf' => 'application/octet-stream',
    'woff' => 'application/octet-stream',
    );
   	if(isset($filemimes[$suffix]))
		return $filemimes[$suffix];
	else
		return false;
}

function swooleredirect($url)
{
    //这里有个很奇怪的现象, 如果不写isset(), 启动的时候,客户端就会不停的执行这里报错.
    //感觉会index/index方法会被调用
    // var_dump(22222222);
    if(isset($_SERVER['SWOOLERESP']))
    {
        $_SERVER['SWOOLERESP']->header('Location', $url);
        $_SERVER['SWOOLERESP']->status(302);
    }
}
