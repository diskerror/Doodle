<?php

use Service\StdIo;

/**
 * Tokenize code.
 */
class TokenizeTask extends TaskMaster
{
	public const MISSING_TOKENS = [
		'start_p_block'  => '(',
		'end_p_block'    => ')',
		'start_c_block'  => '{',
		'end_c_block'    => '}',
		'start_s_block'  => '[',
		'end_s_block'    => ']',
		'list_separator' => ',',
		'com_terminator' => ';',
		'conditional_q'  => '?',
		'conditional_c'  => ':',
		'concatenate'    => '.',
		'assign'         => '=',
		'logical_not'    => '!',
	];


	/**
	 * Dump all tokens in named file to screen.
	 *
	 * @param string $fName
	 *
	 * @return void
	 */
	public function getAllAction(string $fName)
	{
		$rawTokens = token_get_all(file_get_contents($fName));
//		StdIo::jsonOut($rawTokens);

		foreach ($rawTokens as $rt) {
			if (is_array($rt)) {
				$text = preg_replace(["/\n+/", "/\r+/", "/\t+/"], ['<NL>', '<CR>', '<TAB>'], $rt[1]);
				fprintf(STDOUT, "%3d  %-28s «%s»\n", $rt[2], token_name($rt[0]), $text);
			}
			else {
				$token = array_search($rt, self::MISSING_TOKENS);
				fprintf(STDOUT, "%3s  %-28s «%s»\n", '', $token, $rt);
			}
		}
	}
}
