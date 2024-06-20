<?php

use Library\Application\Commands;

class CombineGraphs extends Commands
{
	private static $map = ['Microsoft.Azure.Devices' => 'MAD'];

	/**
	 * This reads and processes GraphViz dot files within a specified directory to generate a graph visualization
	 * in the DOT language. The function reads dot files containing information about classes, interfaces,
	 * namespaces, or structs, processes the content to match node names with labels, and generates an output
	 * DOT file for visualization.
	 *
	 * @return int
	 */
	public static function main(): int
	{
		$searchDir = self::$opts->arguments[1]->arg;
		//	$outName   = 'Inherit Graph';
		$outName = 'Call Graph';

		$dotFileNames = [];
		//	exec('find -E ' . escapeshellarg($searchDir) . ' -regex \'.*/(dir_|inherit_graph_).*.dot\'', $dotFileNames);
		exec('find -E ' . escapeshellarg($searchDir) . ' -regex \'.*/(class|interface|namespace|struct).*.dot\'',
			 $dotFileNames);

		//	sort($dotFileNames, SORT_NATURAL);

		$outFile = [];
		$outFile['digraph'] = 'digraph "' . $outName . '"';
		$outFile['brak'] = '{';
		$outFile['IN'] = ' // INTERACTIVE_SVG=YES';
		$outFile['LAT'] = ' // LATEX_PDF_SIZE';
		$outFile['bgcolor'] = '  bgcolor="transparent";';
		$outFile['edge'] = '  edge [fontname="Helvetica",fontsize="10",labelfontname="Helvetica",labelfontsize="10"];';
		$outFile['node'] = '  node [fontname="Helvetica",fontsize="10",shape=record];';
		$outFile['rankdir'] = '  rankdir="LR";';
		//	$outFile['concentrate'] = '  concentrate=true;';
		$outFile['line'] = '';

		foreach ($dotFileNames as $dotFileName) {
			$file = file_get_contents($dotFileName);
			$file = strtr($file, $map);
			$lines = explode("\n", $file);

			//	first make node names match the labels
			foreach ($lines as $line) {
				if (preg_match('/^  Node\d+ \[/', $line) === 1) {
					$parts = [];
					preg_match('/(Node\d+).*label="(.+?)"/', $line, $parts);
					$file = preg_replace('/\b' . $parts[1] . '\b/', preg_replace('/\W+/', '_', $parts[2]), $file);
				}
			}

			//	Now get the new set of lines.
			$lines = explode("\n", $file);

			//  skip the first 2 lines
			$lines = array_slice($lines, 2);

			//  now copy everything else except the closing bracket
			foreach ($lines as $line) {
				if (preg_match('#^\s*(?://|bgcolor|edge|node|rankdir)#', $line) == 1) {
					continue;
				}

				if ($line === '}') {
					break;
				}

				$keyx = [];
				preg_match('/^\s+(\w+)(?:\s+->\s+(\w+)|)\s+\[.*$/', $line, $keyx);
				array_shift($keyx);
				switch (count($keyx)) {
					case 1:
						$key = $keyx[0];
						break;

					case 2:
						sort($keyx);
						$key = implode('', $keyx);
						break;

					default:
						echo count($keyx), ' ';
						echo $line, "\n";
				}

				$outFile[$key] = $line;
			}

			$outFile[$dotFileName] = '';
		}

		//  inplode and write to file with the closing bracket
		file_put_contents($argv[1] . '/' . $outName . '.dot', implode("\n", $outFile) . "}\n");

	}

	private function uniqueName($str)
	{
		return preg_replace('/\W+/', '_', base64_encode(hash('sha512', $str, true)));
	}

}
