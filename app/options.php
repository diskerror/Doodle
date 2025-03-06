<?php

//  Each associative array contains the keys: spec, desc, type, default, and inc.
//  Unset members will be set to null.

return [
	[
		"spec" => "h|help",
		"desc" => "Show this help.",
	],
	[
		"spec" => "v|verbose",
		"desc" => "Verbose output.",
		"incremental" => true,
		'defaultValue' => 0,
	],
	[
		'spec' => 'q|quiet',
		'desc' => 'Minimal output.',
		'defaultValue' => 0,
	],
];
