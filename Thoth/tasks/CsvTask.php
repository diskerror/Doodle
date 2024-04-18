<?php

use Logic\Csv;
use Logic\Mint;
use Service\StdIo;

/**
 * Handling of CSV files.
 */
class CsvTask extends TaskMaster
{
	/**
	 * Accepts CSV file name and outputs JSON formatted output.
	 * A second numeric parameter tells this function how many lines to skip
	 *    before reading the header row.
	 *
	 * @param string $fName
	 * @param int    $hRowNum
	 *
	 * @return void
	 */
	public function toJsonAction(string $fName, int $hRowNum = 0)
	{
//		mb_internal_encoding('UTF-8');
//		ini_set('memory_limit', 100000000000);

		StdIo::jsonOut(Csv::toArray($fName, $hRowNum));
	}

	/**
	 * Converts Venmo CSV file to Mint CSV file suitable for importing into Quicken (08/2022).
	 *
	 * @param string $fName
	 *
	 * @return void
	 */
	public function venmoToMintAction(string $fName)
	{
		$data = Csv::toArray($fName, 2);

		//	remove last two
		array_pop($data);
		array_pop($data);

		/*
		 * From conversation on the web, a working Mint CSV file.
		Date,Description,Original Description,Amount,Transaction Type,Category,Account Name,Labels,Notes
		1/23/2015,Bookstore Invoice,Bookstore Invoice,12.34,debit,Office Supplies,,,
		1/23/2015,Gas Company,Gas Company,60.00,debit,Utilities,,,August bill
		1/23/2015,Hydro,Hydro,234.83,debit,Utilities,,,
		1/24/2015,Microsoft,Microsoft,1220.00,credit,Payroll,,,Payroll
		*/

		$xlated = [];
		foreach ($data as $d) {
			try {
				$cat = Mint::VENMO_FIXER[$d['Note']];
			}
			catch (Throwable $t) {
				try {
					$cat = Mint::CAT_ID[$d['Note']];
				}
				catch (Throwable $t) {
					$cat = 20;
				}
			}
			$xlated[] = [
				'Date'                 => preg_replace('/^(\\d{4})-(\\d\\d)-(\\d\\d).*/', '$2/$3/$1', $d['Datetime']),
				'Description'          => $d['To'],
				'Original Description' => $d['To'],
				'Amount'               => round(preg_replace('/[$ -]/', '', $d['Amount (total)']), 2), // positive, no symbol
				'Transaction Type'     => Mint::TYPE_ID[$d['Type']],
				'Category'             => $cat,
				'Account Name'         => 'Venmo',
				'Labels'               => '',
				'Notes'                => $d['Note'],
			];
		}

		$fp = fopen($fName . '_.csv', 'wb');
		fputcsv($fp, array_keys($xlated[0]));
		foreach ($xlated as $x) {
			fputcsv($fp, $x);
		}

		fclose($fp);
	}

	/**
	 * Converts PayPal CSV file to Mint CSV file suitable for importing into Quicken (08/2022).
	 *
	 * @param string $fName
	 *
	 * @return void
	 */
	public function paypalToMintAction(string $fName)
	{
		$data = Csv::toArray($fName);

		$xlated = [];
		foreach ($data as $d) {
			if (/*$d['Type'] === 'General Currency Conversion' || */$d['Balance Impact'] === 'Memo') {
				continue;
			}

			$xlated[] = [
				'Date'                 => $d['Date'],
				'Description'          => 'T '.$d['Name'],
				'Original Description' => '',
				'Amount'               => $d['Gross'],
				'Transaction Type'     => strtolower($d['Balance Impact']),    //	credit or debit
				'Category'             => 'PayPal:'.$d['Type'],
				'Account Name'         => '',
				'Labels'               => '',
				'Notes'                => $d['Item Title'],
			];
		}

		$fp = fopen($fName . '_.csv', 'wb');
		fputcsv($fp, array_keys($xlated[0]));
		foreach ($xlated as $x) {
			fputcsv($fp, $x);
		}

		fclose($fp);
	}
}
