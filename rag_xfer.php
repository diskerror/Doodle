#!env php
<?php

/**
 * rag_xfer.php — SQLite data transfer skeleton
 *
 * Reads searched rows from one SQLite database and maps them to another.
 * Uses Library\SQLite3 wrapper from lib/ — no app framework needed.
 */

require __DIR__ . '/vendor/autoload.php';

use Library\SQLite3;

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Convert a UTC timestamp string to local time.
 *
 * Accepts ISO-8601 ("2024-06-01T14:30:00Z") or space-separated
 * ("2024-06-01 14:30:00") input and returns "YYYY-MM-DD HH:MM:SS" in the
 * system local timezone, with DST applied automatically.
 */
function utc_to_local(string $utc): string
{
	$dt = new DateTime($utc, new DateTimeZone('UTC'));
	$dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
	return $dt->format('Y-m-d H:i:s');
}

// ─── Configuration ───────────────────────────────────────────────────────────

// Source database (where searched rows live)
$sourceDbPath = '/Volumes/WDBlack2/raggerBU/memories.db';

// Destination database (where mapped rows will be inserted)
$destDbPath = '/Volumes/WDBlack2/.ragger/memories.db';


// ─── Main ────────────────────────────────────────────────────────────────────

// Default to empty map — uncomment and fill in above when ready
$fieldMap = $fieldMap ?? [];

try {
	// Open source database (read-only)
	$source = new SQLite3($sourceDbPath, SQLITE3_OPEN_READONLY);
	$source->enableExceptions(true);

	// Open destination database (read-write)
	$dest = new SQLite3($destDbPath, SQLITE3_OPEN_READWRITE);
	$dest->enableExceptions(true);

	// ─── Step 1: Query source database ─────────────────────────────────────
	$resl = $source->query("SELECT text, tags, timestamp FROM memories WHERE category = 'session-summary' ORDER BY timestamp ASC");

	// ─── Step 2: Map and insert into destination ───────────────────────────
	while ($row = $resl->fetchArray(SQLITE3_NUM)) {
		$dest->query("INSERT INTO summaries (text, level, status, tags, timestamp) VALUES ('" . SQLite3::escapeString($row[0]) . "', 'session', 'import', '" . SQLite3::escapeString($row[1]) . "', '" . utc_to_local($row[2]) . "')");
	}

	echo "Done.\n";

}
catch (Exception $e) {
	\Library\StdIo::outln("Error: " . $e->getMessage());
	\Library\StdIo::jsonOut($e->getTrace());
	exit(1);
}
