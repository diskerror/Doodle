<?php

return [

	'version' => '0.1',

	//	Name of the user's configuration file.
	'userConfigName' => '.thoth.php',

	'process' => [
		'name'    => 'thoth',
		'path'    => '/var/run/',
		'procDir' => '/proc/'    //	location of actual PID
	],

	'options'=>[
		['v|verbose'],
		['n']
	]

];
