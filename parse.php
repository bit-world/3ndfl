<?php
	require __DIR__ . '/vendor/autoload.php';
	
	
	define('MODE', !true);
	define('TEXT', !true);
	
	define('DEBUG', isset($_GET['q']));
	
	//$parser = new PDF\Parser('Output.pdf');
	$parser = new PDF\Parser('out-inc-state-2020.pdf');