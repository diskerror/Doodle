<?php

namespace Forensics;

use Application\TaskMaster;

/**
 * Parse medical records markdown and extract chronological timeline.
 *
 * Extracts dates, vitals, aortic measurements, ejection fraction,
 * medications, and providers from UCLA medical records.
 */
class MedicalTimelineTask extends TaskMaster
{
    protected static array $taskOptions = [
        ['spec' => 'i|input:', 'desc' => 'Input markdown file'],
        ['spec' => 'o|output:', 'desc' => 'Output JSON file (default: stdout)'],
        ['spec' => 'format:', 'desc' => 'Output format: json, csv, table (default: json)', 'defaultValue' => 'json'],
        ['spec' => 'd|debug', 'desc' => 'Enable debug output'],
    ];

    private array $timeline = [];
    private bool $debug = false;

    /**
     * Main action - parse medical records and output timeline.
     */
    public function mainAction(...$args): void
    {
        $inputFile = $this->getOption('input');
        $outputFile = $this->getOption('output');
        $format = $this->getOption('format', 'json');
        $this->debug = (bool)$this->getOption('debug', false);

        if (!$inputFile) {
            $this->fail("Input file required. Use -i or --input");
            $this->helpAction();
            return;
        }

        if (!file_exists($inputFile)) {
            $this->fail("Input file not found: {$inputFile}");
            return;
        }

        $this->info("Parsing medical records: {$inputFile}");
        $this->parseFile($inputFile);

        // Sort by date
        usort($this->timeline, fn($a, $b) => strcmp($a['date'], $b['date']));

        $this->info("Extracted " . count($this->timeline) . " timeline entries");

        // Output
        $output = match ($format) {
            'csv' => $this->formatCsv(),
            'table' => $this->formatTable(),
            default => $this->formatJson(),
        };

        if ($outputFile) {
            file_put_contents($outputFile, $output);
            $this->success("Output written to: {$outputFile}");
        } else {
            echo $output;
        }
    }

    /**
     * Parse the markdown file page by page.
     */
    private function parseFile(string $path): void
    {
        $content = file_get_contents($path);
        $pages = preg_split('/^## Page \d+$/m', $content);

        foreach ($pages as $pageNum => $pageContent) {
            if (empty(trim($pageContent))) {
                continue;
            }

            $this->parsePage($pageContent, $pageNum);
        }
    }

    /**
     * Parse a single page for document entries.
     */
    private function parsePage(string $content, int $pageNum): void
    {
        // Split by document separators (---)
        $docs = preg_split('/^---$/m', $content);

        foreach ($docs as $doc) {
            $entry = $this->parseDocument($doc);
            if ($entry && !empty($entry['date'])) {
                $this->timeline[] = $entry;
            }
        }
    }

    /**
     * Parse a single document and extract key information.
     */
    private function parseDocument(string $doc): ?array
    {
        $entry = [
            'date' => null,
            'type' => null,
            'provider' => null,
            'findings' => [],
        ];

        // Extract document type and provider from headers
        if (preg_match('/^(Consults|Op Note|H&P|Progress Notes|Procedure Note|Discharge Summary|Lab Results|Echocardiogram)\s+(by|signed by)\s+([^,]+(?:,\s*[^,]+)?)/m', $doc, $matches)) {
            $entry['type'] = $this->normalizeType($matches[1]);
            $entry['provider'] = trim($matches[3]);
        }

        // Extract primary date (multiple possible formats)
        $date = $this->extractDate($doc);
        if (!$date) {
            return null;
        }
        $entry['date'] = $date;

        // Extract findings
        $findings = [];

        // Vitals
        if ($vitals = $this->extractVitals($doc)) {
            $findings['vitals'] = $vitals;
        }

        // Aortic measurements
        if ($aortic = $this->extractAorticMeasurements($doc)) {
            $findings['aortic_measurements'] = $aortic;
        }

        // Ejection fraction
        if ($ef = $this->extractEjectionFraction($doc)) {
            $findings['ejection_fraction'] = $ef;
        }

        // Medications
        if ($meds = $this->extractMedications($doc)) {
            $findings['medications'] = $meds;
        }

        // Summary notes (extract key sentences)
        if ($notes = $this->extractNotes($doc)) {
            $findings['notes'] = $notes;
        }

        $entry['findings'] = $findings;

        if ($this->debug) {
            $findingTypes = !empty($findings) ? json_encode(array_keys($findings)) : '[]';
            $this->info("Found entry: {$entry['date']} - {$entry['type']} - {$findingTypes}");
        }

        return $entry;
    }

    /**
     * Extract primary date from document.
     */
    private function extractDate(string $doc): ?string
    {
        // Try various date patterns in order of preference
        $patterns = [
            // Date of Service: 11/18/19 0001
            '/Date of Service:\s*(\d{1,2}\/\d{1,2}\/\d{2,4})/',
            // Filed: 02/23/17 0655
            '/Filed:\s*(\d{1,2}\/\d{1,2}\/\d{2,4})/',
            // Creation Time: 02/14/17 1749
            '/Creation Time:\s*(\d{1,2}\/\d{1,2}\/\d{2,4})/',
            // at 02/14/17 1749 (in header)
            '/\s+at\s+(\d{1,2}\/\d{1,2}\/\d{2,4})/',
            // DATE OF OPERATION: 11/18/2019
            '/DATE OF OPERATION:\s*(\d{1,2}\/\d{1,2}\/\d{4})/',
            // Standalone date in header: 11/18/2019
            '/^(\d{1,2}\/\d{1,2}\/\d{4})$/m',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $doc, $matches)) {
                return $this->normalizeDate($matches[1]);
            }
        }

        return null;
    }

    /**
     * Normalize date to YYYY-MM-DD format.
     */
    private function normalizeDate(string $date): string
    {
        // Parse MM/DD/YY or MM/DD/YYYY
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $date, $parts)) {
            $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
            $day = str_pad($parts[2], 2, '0', STR_PAD_LEFT);
            $year = $parts[3];
            
            // Convert 2-digit year to 4-digit (assume 20xx)
            if (strlen($year) === 2) {
                $year = '20' . $year;
            }

            return "{$year}-{$month}-{$day}";
        }

        return $date;
    }

    /**
     * Normalize document type.
     */
    private function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'consults' => 'consult',
            'op note' => 'op_note',
            'h&p' => 'history_physical',
            'progress notes' => 'progress',
            'procedure note' => 'procedure',
            'discharge summary' => 'discharge',
            'lab results' => 'lab',
            'echocardiogram' => 'echo',
            default => strtolower(str_replace(' ', '_', $type)),
        };
    }

    /**
     * Extract vitals from pipe-delimited format.
     */
    private function extractVitals(string $doc): ?array
    {
        // VS: BP 155/68 mmHg | Pulse 61 | Temp(Src) 36.1 °C (97 °F) (Oral) | Resp 16 | Ht 6' 1" (1.854 m) | Wt 187 lb 6.4 oz (85.004 kg) | BMI 24.73 kg/m2 | SpO2 96%
        if (!preg_match('/VS:\s*(.+?)(?:\n|$)/s', $doc, $matches)) {
            return null;
        }

        $vitalsLine = $matches[1];
        $vitals = [];

        // Blood pressure
        if (preg_match('/BP\s+(\d+\/\d+)\s*mmHg/', $vitalsLine, $m)) {
            $vitals['blood_pressure'] = $m[1];
        }

        // Pulse
        if (preg_match('/Pulse\s+(\d+)/', $vitalsLine, $m)) {
            $vitals['pulse'] = (int)$m[1];
        }

        // Temperature
        if (preg_match('/Temp(?:\(Src\))?\s+([\d.]+)\s*°C/', $vitalsLine, $m)) {
            $vitals['temp_c'] = (float)$m[1];
        }

        // Respiratory rate
        if (preg_match('/Resp\s+(\d+)/', $vitalsLine, $m)) {
            $vitals['respiratory_rate'] = (int)$m[1];
        }

        // Height
        if (preg_match('/Ht\s+(\d+\'\s*\d+")/', $vitalsLine, $m)) {
            $vitals['height'] = $m[1];
        }

        // Weight
        if (preg_match('/Wt\s+([\d.]+)\s*lb/', $vitalsLine, $m)) {
            $vitals['weight_lb'] = (float)$m[1];
        }

        // BMI
        if (preg_match('/BMI\s+([\d.]+)/', $vitalsLine, $m)) {
            $vitals['bmi'] = (float)$m[1];
        }

        // SpO2
        if (preg_match('/SpO2\s+(\d+)%/', $vitalsLine, $m)) {
            $vitals['spo2'] = (int)$m[1];
        }

        return !empty($vitals) ? $vitals : null;
    }

    /**
     * Extract aortic measurements (critical for Marfan monitoring).
     */
    private function extractAorticMeasurements(string $doc): ?array
    {
        $measurements = [];

        // Sinus of Valsalva
        if (preg_match('/sinus(?:es)? of Valsalva\s+([\d.]+)\s*cm/i', $doc, $m)) {
            $measurements['sinus_of_valsalva_cm'] = (float)$m[1];
        }

        // ST junction
        if (preg_match('/ST junction\s+([\d.]+)\s*cm/i', $doc, $m)) {
            $measurements['st_junction_cm'] = (float)$m[1];
        }

        // Ascending aorta
        if (preg_match('/ascending aorta\s+([\d.]+)\s*cm/i', $doc, $m)) {
            $measurements['ascending_aorta_cm'] = (float)$m[1];
        }

        // Aortic root (alternative phrasing)
        if (preg_match('/aortic root\s+\(?([\d.]+)\s*cm\)?/i', $doc, $m)) {
            if (!isset($measurements['sinus_of_valsalva_cm'])) {
                $measurements['aortic_root_cm'] = (float)$m[1];
            }
        }

        // Aortic sinuses measuring
        if (preg_match('/aortic sinuses measuring\s+([\d.]+)\s*cm/i', $doc, $m)) {
            if (!isset($measurements['sinus_of_valsalva_cm'])) {
                $measurements['sinus_of_valsalva_cm'] = (float)$m[1];
            }
        }

        return !empty($measurements) ? $measurements : null;
    }

    /**
     * Extract ejection fraction.
     */
    private function extractEjectionFraction(string $doc): ?string
    {
        $patterns = [
            '/ejection fraction\s+(?:is\s+)?(?:approximately\s+)?(\d+(?:-\d+)?%)/i',
            '/(?:LV)?EF\s+(\d+(?:-\d+)?%)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $doc, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Extract medications list.
     */
    private function extractMedications(string $doc): ?array
    {
        if (!preg_match('/Medications?:\s*([^\n]+(?:\n(?![\w\s]*:)[^\n]+)*)/i', $doc, $matches)) {
            return null;
        }

        $medLine = $matches[1];
        
        // Split by common delimiters (comma, semicolon)
        $meds = preg_split('/[,;]\s*/', $medLine);
        $meds = array_map('trim', $meds);
        $meds = array_filter($meds);

        return !empty($meds) ? array_values($meds) : null;
    }

    /**
     * Extract key notes/summary text.
     */
    private function extractNotes(string $doc): ?string
    {
        $notes = [];

        // Look for key sections
        $sections = [
            'IMPRESSIONS?:',
            'CONCLUSIONS?:',
            'ASSESSMENT:',
            'PLAN:',
            'RECOMMENDATIONS?:',
        ];

        foreach ($sections as $section) {
            if (preg_match("/{$section}\s*\n((?:.+\n?)+?)(?=\n[A-Z][A-Z\s]*:|$)/i", $doc, $matches)) {
                $text = trim($matches[1]);
                // Clean up numbered lists, keep first 3 items max
                $lines = explode("\n", $text);
                $lines = array_slice($lines, 0, 5);
                $notes[] = implode(' ', array_map('trim', $lines));
            }
        }

        if (!empty($notes)) {
            return implode(' | ', $notes);
        }

        return null;
    }

    /**
     * Format output as JSON.
     */
    private function formatJson(): string
    {
        return json_encode($this->timeline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /**
     * Format output as CSV.
     */
    private function formatCsv(): string
    {
        $csv = "Date,Type,Provider,Findings\n";
        
        foreach ($this->timeline as $entry) {
            $findings = json_encode($entry['findings']);
            $csv .= sprintf(
                "%s,%s,%s,\"%s\"\n",
                $entry['date'],
                $entry['type'] ?? '',
                $entry['provider'] ?? '',
                str_replace('"', '""', $findings)
            );
        }

        return $csv;
    }

    /**
     * Format output as simple table.
     */
    private function formatTable(): string
    {
        $output = sprintf("%-12s %-15s %-30s %s\n", 'Date', 'Type', 'Provider', 'Key Findings');
        $output .= str_repeat('-', 100) . "\n";

        foreach ($this->timeline as $entry) {
            $findings = [];
            if (!empty($entry['findings']['aortic_measurements'])) {
                $findings[] = 'Aortic';
            }
            if (!empty($entry['findings']['vitals'])) {
                $findings[] = 'Vitals';
            }
            if (!empty($entry['findings']['ejection_fraction'])) {
                $findings[] = 'EF';
            }
            if (!empty($entry['findings']['medications'])) {
                $findings[] = 'Meds';
            }

            $output .= sprintf(
                "%-12s %-15s %-30s %s\n",
                $entry['date'],
                $entry['type'] ?? '',
                substr($entry['provider'] ?? '', 0, 30),
                implode(', ', $findings)
            );
        }

        return $output;
    }
}
