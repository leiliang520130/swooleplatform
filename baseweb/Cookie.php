<?php
namespace Swoole;

class Cookie
{
	public static $path = '/';
	public static $domain = null;
	public static $secure = false;
	public static $httponly = false;

	static function get($key,$default = null)
	{
		if(!isset($_COOKIE[$key])) return $default;
		else return $_COOKIE[$key];
	}

    static function set($key, $value, $expire = 0)
	{
	    $_SERVER['SWOOLERESP']->cookie($key,$value, $expire,Cookie::$path,Cookie::$domain,Cookie::$secure,Cookie::$httponly);
    }

    static function delete($key)
	{
		unset($_COOKIE[$key]);
		self::set($key, null);
	}
}
