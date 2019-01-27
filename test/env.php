<?php
if( !defined('_XPMAPP_ROOT') ) define( '_XPMAPP_ROOT' , getenv("_XPMAPP_ROOT") );
if( !defined('DS') ) define( 'DS' , DIRECTORY_SEPARATOR );

$_SERVER['HTTPS'] = getenv('HTTPS');
$_SERVER['HTTP_HOST'] = getenv('HOST');
	

$autoload = realpath(getenv('TROOT') . "/_lp/autoload.php");
require_once($autoload);
