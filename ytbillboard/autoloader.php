<?php

spl_autoload_register(function ($class) {
	$path = "ytbillboard\\";
	$pathLen = strlen($path);
	if(substr($class, 0, $pathLen) == $path) {
		require(dirname(__FILE__) . DIRECTORY_SEPARATOR . substr($class, $pathLen) . ".php");
	}
});