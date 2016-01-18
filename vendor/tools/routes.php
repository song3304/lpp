<?php

$router->resource('manual', 'ManualController');
$router->addAnyActionRoutes([
	'tools',
	'placeholder',
	'qr',
	'tools'

$router->get('artisans', 'ArtisansController@index');
$router->group(['middleware' => 'local'], function($router){
	$router->addAnyActionRoutes([
		'artisans',
	]);
});