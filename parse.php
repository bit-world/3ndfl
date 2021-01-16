<?php
	require __DIR__ . '/vendor/autoload.php';
	
	define('MODE', !true);
	define('TEXT', !true);
	
	define('DEBUG', isset($_GET['q']));
	
	$parser = new PDF\Parser('fake/out-inc-state-2020.pdf');