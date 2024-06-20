<?php

use GetOptionKit\OptionResult;
use Library\app\Commands;

class FileForensics extends Commands
{
	public static $options = [
		[
			"spec" => "o|output:",
			"desc" => "Output to file (defaults to STDOUT).",
			"type" => "File",
		],
	];

	protected static $pathsToIgnore = [
		'/.idea',
		'/.git',
		'/.DS_Store',
		'/README.md',
		'/Readme.txt',
		'/readme.txt',
		'/LICENSE.TXT',
		'/version.txt',
		'/AssemblyInfo.cs',
		'/Program.cs',
		'/app.config',
		'/App.config',
		'/Resources.resx',
		'/Settings.Designer.cs',
		'/Settings.settings',
		'/Resources.Designer.cs',
		'/_._',
		'/packages.config',
		'/.signature.p7s',
		'/packages/Microsoft.',
		'/packages/MSTest.',
		'/packages/System.',
		'/Autofac.',
		'/Castle.Core',
		'/Crc32.NET.',
		'/DotNetty.',
		'/EnterpriseLibrary.TransientFaultHandling',
		'/Mono.Security.' .
		'/MSTest.Test',
		'/MSTest.TestAdapter.',
		'/MSTest.TestFramework.',
		'/NETStandard.Library.',
		'/Newtonsoft.Json.',
		'/PCLCrypto.',
		'/PInvoke.',
		'/Polly.',
		'/Swashbuckle.',
		'/Twilio.',
		'/Validation.',
		'/WindowsAzure.ServiceBus.',
		'/WindowsAzure.Storage.',
	];

	protected static $fp;

	public static function init(OptionResult $opts): void
	{
		parent::init($opts);
		ini_set('memory_limit', -1);

		if (self::$opts->has('output')) {
			self::$fp = fopen(self::$opts->output, 'w');
		} else {
			self::$fp = STDOUT;
		}
	}

	public static function Compare(): int
	{

	}

	public static function SizesAndTypes(): int
	{

	}
}
