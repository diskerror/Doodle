<?php

use GetOptionKit\OptionResult;
use Application\TaskMaster;

class FileTask extends TaskMaster
{
//	public OptionCollection $specs = [
//		[
//			"spec" => "o|output:",
//			"desc" => "Output to file (defaults to STDOUT).",
//			"type" => "File",
//		],
//	];

	protected $pathsToIgnore = [
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

	protected $fp;

//	public function __construct(OptionResult $inputParams)
//	{
//		parent::__construct($inputParams);
//		ini_set('memory_limit', -1);
//
//		if ($this->inputParams->has('output')) {
//			$this->fp = fopen($this->inputParams->output, 'w');
//		} else {
//			$this->fp = STDOUT;
//		}
//	}

	public static function Compare(): int
	{

	}

	public static function SizesAndTypes(): int
	{

	}
}
