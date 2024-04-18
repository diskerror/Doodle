#!/usr/bin/env php82
<?php

//	The first parameter points to a file with terms.
//	For each term search (grep) the location pointed to by the second parameter.

if ($argc !== 4) {
	echo "Usage: $argv[0] <file of terms> <file of original terms> <file or directory to search> \n";
	exit;
}

include 'var_export.php';

$terms = file($argv[1], FILE_IGNORE_NEW_LINES);
$placeToSearch = $argv[3];
$trimResults = strlen($placeToSearch) + 1;

$totalFound = 0;

$tableTableMatches = [];

foreach ($terms as $term) {
	# 	term=${term//'_'/'\w{0,10}[_\s]{0,4}[\w\s]{0,10}'}
	# 	term=${term//'_'/'[_[:space:]]{0,4}'}
	#	term=${term//'_'/'[^,]{0,6}'}
	#	t_sub=${term//'[- ]'/'_'}
//	$t_sub = preg_replace('/_/', '_?', $term);
	$t_sub = preg_replace('/_/', '[-._ ,"]{0,10}', $term);
//	$t_sub = preg_replace('/_/', '.{0,10}', $term);

	$cmd = "egrep -iro --exclude='.*' '\\w*{$t_sub}\\w*' '$placeToSearch'";
	$found = [];
	exec($cmd, $found);

	if (count($found) > 0) {
		$ADCS_term = [];
		exec("egrep -ion '\\w*{$t_sub}\\w*' '$argv[2]'", $ADCS_term);
		$adcs_table = explode(':', $ADCS_term[0]);

		$totalFound++;
		$tally = [];
		foreach ($found as $f) {
			if (!isset($tally[$f])) {
				$tally[$f] = 1;
			} else {
				$tally[$f]++;
			}
		}

//		echo "ADCS term: $found[0]", PHP_EOL, 'Found:', PHP_EOL;

		foreach ($tally as $f => $c) {
            $aews_table_field= substr($f, $trimResults, -5);
//			echo '  ', substr($f, $trimResults), ' ', $c, PHP_EOL;
			echo "$ADCS_term[0]:", $aews_table_field, ":$c", PHP_EOL;

			$combo = $adcs_table[0] . "\t" . explode(':', $aews_table_field)[0];
			if (!isset($tableTableMatches[$combo])) {
				$tableTableMatches[$combo] = 1;
			} else {
				$tableTableMatches[$combo]++;
			}
		}

//		echo PHP_EOL;
	}
}

echo PHP_EOL;
foreach ($tableTableMatches as $k=>$v) {
    echo "$k\t$v", PHP_EOL;

}

echo "Total found: $totalFound", PHP_EOL;
