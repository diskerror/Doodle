<?php

//	See app/App.php addOptions() for the full list of option members

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
