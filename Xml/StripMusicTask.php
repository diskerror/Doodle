<?php

namespace Xml;

use Application\TaskMaster;
use DOMDocument;
use ErrorException;
use Library\StdIo;
use XMLReader;
use ZipArchive;

class StripMusicTask extends TaskMaster
{
    protected static array $taskOptions = [
        ['spec' => '|pretty', 'desc' => 'Pretty print for humans.', 'defaultValue' => false],
    ];

    /** XML attributes to strip (visual rendering hints only). */
    private const array STRIP_ATTRS = [
        'color',
        'default-x',
        'default-y',
        'font-family',
        'font-size',
        'font-weight',
        'font-style',
        'justify',
        'valign',
        'enclosure',
        'print-object',
    ];

    /** Top-level elements to remove entirely (layout, not music). */
    private const array STRIP_ELEMENTS = [
        'page-layout',
        'system-layout',
        'staff-layout',
        'appearance',
        'credit',
        'music-font',
        'word-font',
        'lyric-font',
        'lyric-language',
        'print',
    ];

    /** Current divisions (duration units per quarter note), tracked across measures. */
    private int $currentDivisions = 1;

    /** System-wide text directions that should be deduplicated into the system track. */
    private const array SYSTEM_DIRECTION_PATTERNS = [
        '/^a\s*tempo$/i',
        '/^rit\.?\s/i',
        '/^rit\.?$/i',
        '/^ritard/i',
        '/^rall/i',
        '/^accel/i',
        '/^rubato$/i',
        '/^poco\s/i',
        '/^più\s/i',
        '/^piu\s/i',
        '/^meno\s/i',
        '/^tempo\s/i',
        '/^tempo$/i',
        '/^animé/i',
        '/^animato/i',
        '/^tranquille/i',
        '/^calando/i',
        '/^morendo/i',
        '/^perdendosi/i',
        '/^serrez/i',
        '/^cédez/i',
        '/^retenu/i',
        '/^encore\s/i',
        '/^en\s+(animant|retenant|serrant|cédant)/i',
    ];

    /** Alter values → accidental symbols for readable pitch names. */
    private const array ACCIDENTALS = [
        '2'  => 'x',
        '1'  => '#',
        '0'  => '',
        '-1' => 'b',
        '-2' => 'bb',
    ];

    /**
     * mainAction
     *
     * Strip or convert MusicXML files.
     *
     * `xml.php strip-music <input> [<output>] [--pretty]`
     *
     * Input: .musicxml, .xml, or .mxl (compressed).
     * Output format determined by extension:
     *   .musicxml/.xml → stripped XML
     *   .json → compact JSON (one measure per line)
     *
     * If no output is given, overwrites input as stripped XML.
     * Use --pretty for fully indented output.
     *
     * @return void
     */
    public function mainAction(...$args): void
    {
        if (count($args) < 1) {
            $this->helpAction();
            return;
        }

        $inputPath  = $args[0];
        $outputPath = $args[1] ?? $inputPath;
        $pretty     = $this->getOption('pretty', false);

        if (!file_exists($inputPath)) {
            $this->fail("File not found: {$inputPath}");
            return;
        }

        StdIo::outln("Reading:  {$inputPath}");

        $doc = $this->loadMusicXml($inputPath);
        $root = $doc->documentElement;

        $ext = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));

        if ($ext === 'json') {
            $data = $this->buildJson($root);
            $this->writeJson($data, $outputPath, $pretty);
            $fmt = $pretty ? 'JSON (pretty)' : 'JSON (compact)';
        } else {
            $this->stripTree($root);
            $this->writeXml($doc, $outputPath, $pretty);
            $fmt = $pretty ? 'XML (pretty)' : 'XML (compact)';
        }

        $inputSize  = filesize($inputPath);
        $outputSize = filesize($outputPath);
        $reduction  = $inputSize > 0 ? 100 * (1 - $outputSize / $inputSize) : 0;
        $sign       = $reduction >= 0 ? '-' : '+';
        $pct        = abs($reduction);

        StdIo::outln("Writing:  {$outputPath}  [{$fmt}]");
        StdIo::outln(sprintf(
            "Size:     %s → %s bytes  (%s%.0f%%)",
            number_format($inputSize),
            number_format($outputSize),
            $sign,
            $pct
        ));
    }

    // -----------------------------------------------------------------------
    // Input loading
    // -----------------------------------------------------------------------

    private function loadMusicXml(string $path): DOMDocument
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'mxl') {
            return $this->loadMxl($path);
        }

        $doc = new DOMDocument();
        $doc->load($path);
        return $doc;
    }

    private function loadMxl(string $path): DOMDocument
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new ErrorException("Cannot open MXL file: {$path}");
        }

        // Find the root file from META-INF/container.xml
        $containerXml = $zip->getFromName('META-INF/container.xml');
        $rootFilePath = null;

        if ($containerXml !== false) {
            $container = new DOMDocument();
            $container->loadXML($containerXml);
            $rootfiles = $container->getElementsByTagName('rootfile');
            if ($rootfiles->length > 0) {
                $rootFilePath = $rootfiles->item(0)->getAttribute('full-path');
            }
        }

        // Fallback: first .xml or .musicxml that isn't in META-INF
        if ($rootFilePath === null) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_starts_with($name, 'META-INF')) continue;
                if (str_ends_with($name, '.xml') || str_ends_with($name, '.musicxml')) {
                    $rootFilePath = $name;
                    break;
                }
            }
        }

        if ($rootFilePath === null) {
            $zip->close();
            throw new ErrorException("No MusicXML file found in MXL archive: {$path}");
        }

        $xmlContent = $zip->getFromName($rootFilePath);
        $zip->close();

        $doc = new DOMDocument();
        $doc->loadXML($xmlContent);
        return $doc;
    }

    // -----------------------------------------------------------------------
    // XML stripping
    // -----------------------------------------------------------------------

    private function stripTree(\DOMElement $el): void
    {
        // Remove visual attributes
        foreach (self::STRIP_ATTRS as $attr) {
            if ($el->hasAttribute($attr)) {
                $el->removeAttribute($attr);
            }
        }

        // Collect children to remove, then remove after iteration
        $toRemove = [];
        foreach ($el->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) continue;
            if (in_array($child->localName, self::STRIP_ELEMENTS, true)) {
                $toRemove[] = $child;
            } else {
                $this->stripTree($child);
            }
        }

        foreach ($toRemove as $child) {
            $el->parentNode !== null
                ? $el->removeChild($child)
                : null;
        }
    }

    private function writeXml(DOMDocument $doc, string $path, bool $pretty): void
    {
        $doc->formatOutput = $pretty;
        $doc->save($path);
    }

    // -----------------------------------------------------------------------
    // JSON conversion
    // -----------------------------------------------------------------------

    private function buildJson(\DOMElement $root): array
    {
        $score = [
            '_schema' => 'system[]{number, tempo?, directions[]?}, '
                . 'parts[]{id, name, measures[]{number, key_fifths?, time?, transpose{diatonic,chromatic,octave?}?, '
                . 'dynamics[]{type, beat?}?, wedges[]{type, number?, beat?}?, '
                . 'pedals[]{type, line?, beat?}?, directions[]?, '
                . 'notes[]{voice, staff, pitch|unpitched|\'rest\', type, dots?, beam[]?, chord?, tie?, '
                . 'grace?, artic[]?, ornament[]?, slur?, tuplet?}}}',
        ];

        // Title
        $workEl = $this->findDirectChild($root, 'work');
        $score['title'] = ($workEl !== null ? $this->domText($workEl, 'work-title') : null)
            ?? $this->domText($root, 'movement-title');

        // Composer and arranger
        $score['composer'] = null;
        $score['arranger'] = null;
        foreach ($root->getElementsByTagName('creator') as $creator) {
            $role = $creator->getAttribute('type');
            if ($role === 'composer') $score['composer'] = trim($creator->textContent);
            if ($role === 'arranger') $score['arranger'] = trim($creator->textContent);
        }

        // Build part-name lookup from <part-list>
        $partNames = [];
        foreach ($root->getElementsByTagName('score-part') as $scorePart) {
            $id   = $scorePart->getAttribute('id');
            $name = '';
            foreach ($scorePart->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE && $child->localName === 'part-name') {
                    $name = trim($child->textContent);
                    break;
                }
            }
            $partNames[$id] = $name;
        }

        // Process each <part>
        $parts = [];
        foreach ($root->getElementsByTagName('part') as $partEl) {
            $partId   = $partEl->getAttribute('id');
            $partName = $partNames[$partId] ?? $partId;

            $this->currentDivisions = 1; // Reset per part

            $part = [
                'id'       => $partId,
                'name'     => $partName,
                'measures' => [],
            ];

            foreach ($partEl->childNodes as $measureNode) {
                if ($measureNode->nodeType !== XML_ELEMENT_NODE) continue;
                if ($measureNode->localName !== 'measure') continue;

                $m = $this->buildMeasure($measureNode);
                $part['measures'][] = $m;
            }

            $parts[] = $part;
        }

        // --- Extract system-wide data (tempo, common tempo marks) ---
        $system = $this->extractSystemData($parts);

        $score['system'] = $system;
        $score['parts'] = $parts;
        return $score;
    }

    /**
     * Extract system-wide tempo and common tempo/expression directions from parts.
     * Removes extracted items from individual parts to avoid duplication.
     *
     * @param array &$parts Parts array (modified in-place to remove extracted items)
     * @return array System measures (only those with data)
     */
    private function extractSystemData(array &$parts): array
    {
        if (empty($parts)) return [];

        // Determine measure count from the first part
        $measureCount = count($parts[0]['measures']);
        $system = [];

        for ($mi = 0; $mi < $measureCount; $mi++) {
            $sysMeasure = [];
            $measureNum = $parts[0]['measures'][$mi]['number'] ?? ($mi + 1);
            $sysMeasure['number'] = $measureNum;

            // Extract tempo from whichever part has it (take first occurrence)
            foreach ($parts as &$part) {
                if (isset($part['measures'][$mi]['tempo'])) {
                    $sysMeasure['tempo'] = $part['measures'][$mi]['tempo'];
                    unset($part['measures'][$mi]['tempo']);
                    break;
                }
            }
            unset($part);

            // Collect system-wide directions: find directions that match system patterns
            // and appear on at least one part. Remove from all parts that have them.
            $systemDirs = [];
            $dirsByPart = []; // [partIdx => [dirIdx => dirText]]

            foreach ($parts as $pi => &$part) {
                if (!isset($part['measures'][$mi]['directions'])) continue;
                foreach ($part['measures'][$mi]['directions'] as $di => $dir) {
                    $text = is_string($dir) ? $dir : ($dir['text'] ?? '');
                    $beat = is_array($dir) ? ($dir['beat'] ?? null) : null;
                    if ($this->isSystemDirection($text)) {
                        $dirsByPart[$pi][$di] = ['text' => $text, 'beat' => $beat];
                    }
                }
            }
            unset($part);

            // Deduplicate: collect unique system directions by text+beat
            $seen = [];
            foreach ($dirsByPart as $dirs) {
                foreach ($dirs as $d) {
                    $key = $d['text'] . '|' . ($d['beat'] ?? '');
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        if ($d['beat'] !== null) {
                            $systemDirs[] = ['text' => $d['text'], 'beat' => $d['beat']];
                        } else {
                            $systemDirs[] = $d['text'];
                        }
                    }
                }
            }

            // Remove extracted directions from individual parts
            if (!empty($dirsByPart)) {
                foreach ($dirsByPart as $pi => $dirIndices) {
                    $remaining = [];
                    foreach ($parts[$pi]['measures'][$mi]['directions'] as $di => $dir) {
                        if (!isset($dirIndices[$di])) {
                            $remaining[] = $dir;
                        }
                    }
                    if (empty($remaining)) {
                        unset($parts[$pi]['measures'][$mi]['directions']);
                    } else {
                        $parts[$pi]['measures'][$mi]['directions'] = array_values($remaining);
                    }
                }
            }

            if (!empty($systemDirs)) {
                $sysMeasure['directions'] = $systemDirs;
            }

            // Only include measures that have system data
            if (count($sysMeasure) > 1) { // more than just 'number'
                $system[] = $sysMeasure;
            }
        }

        return $system;
    }

    /**
     * Check if a direction text matches common system-wide patterns.
     */
    private function isSystemDirection(string $text): bool
    {
        $text = trim($text);
        if ($text === '') return false;
        foreach (self::SYSTEM_DIRECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) return true;
        }
        return false;
    }

    private function buildMeasure(\DOMElement $measureEl): array
    {
        $m = ['number' => (int)$measureEl->getAttribute('number')];

        // Key signature
        $keyEl = $this->findDescendant($measureEl, 'key');
        if ($keyEl !== null) {
            $fifths = $this->domText($keyEl, 'fifths');
            if ($fifths !== null) {
                $m['key_fifths'] = (int)$fifths;
            }
        }

        // Time signature
        $timeEl = $this->findDescendant($measureEl, 'time');
        if ($timeEl !== null) {
            $beats    = $this->domText($timeEl, 'beats');
            $beatType = $this->domText($timeEl, 'beat-type');
            if ($beats !== null && $beatType !== null) {
                $m['time'] = "{$beats}/{$beatType}";
            }
        }

        // Tempo (from <metronome>)
        foreach ($this->findAll($measureEl, 'metronome') as $metro) {
            $perMinute = $this->domText($metro, 'per-minute');
            if ($perMinute !== null) {
                $m['tempo'] = (int)(float)$perMinute;
                break;
            }
        }

        // Attributes block: divisions, transposition
        $attrEl = $this->findDescendant($measureEl, 'attributes');
        if ($attrEl !== null) {
            $divText = $this->domText($attrEl, 'divisions');
            if ($divText !== null) {
                $this->currentDivisions = (int)$divText;
            }

            // Transposition (for transposing instruments / instrument changes)
            $transposeEl = $this->findDirectChild($attrEl, 'transpose');
            if ($transposeEl !== null) {
                $diatonic = (int)($this->domText($transposeEl, 'diatonic') ?? '0');
                $chromatic = (int)($this->domText($transposeEl, 'chromatic') ?? '0');
                $octChange = (int)($this->domText($transposeEl, 'octave-change') ?? '0');
                if ($diatonic !== 0 || $chromatic !== 0 || $octChange !== 0) {
                    $t = ['diatonic' => $diatonic, 'chromatic' => $chromatic];
                    if ($octChange !== 0) $t['octave'] = $octChange;
                    $m['transpose'] = $t;
                }
            }
        }

        // Walk children in document order to track beat positions for directions.
        // Cumulative duration (in division units) tells us where each direction falls.
        $cumulativeDur = 0;
        $dynamics   = [];
        $wedges     = [];
        $pedals     = [];
        $directions = [];
        $notes      = [];

        foreach ($measureEl->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) continue;
            $tag = $child->localName;

            if ($tag === 'note') {
                $isChord = ($this->findDirectChild($child, 'chord') !== null);
                $dur     = (int)($this->domText($child, 'duration') ?? '0');

                $notes[] = $this->buildNote($child);

                // Chord notes share the same onset; only non-chord notes advance time
                if (!$isChord) {
                    $cumulativeDur += $dur;
                }
            } elseif ($tag === 'forward') {
                $cumulativeDur += (int)($this->domText($child, 'duration') ?? '0');
            } elseif ($tag === 'backup') {
                $cumulativeDur -= (int)($this->domText($child, 'duration') ?? '0');
            } elseif ($tag === 'direction') {
                $beat = $this->durationToBeat($cumulativeDur);

                // Check for <offset> which shifts the direction from its document position
                $offsetText = $this->domText($child, 'offset');
                if ($offsetText !== null) {
                    $beat = $this->durationToBeat($cumulativeDur + (int)$offsetText);
                }

                // Dynamics
                foreach ($this->findAll($child, 'dynamics') as $dynContainer) {
                    foreach ($dynContainer->childNodes as $dynChild) {
                        if ($dynChild->nodeType === XML_ELEMENT_NODE) {
                            $d = ['type' => $dynChild->localName];
                            if ($beat > 1.0) $d['beat'] = $beat;
                            $dynamics[] = $d;
                        }
                    }
                }

                // Wedges (hairpins)
                foreach ($this->findAll($child, 'wedge') as $wedgeEl) {
                    $w = ['type' => $wedgeEl->getAttribute('type')];
                    $num = $wedgeEl->getAttribute('number');
                    if ($num !== '') $w['number'] = (int)$num;
                    if ($beat > 1.0) $w['beat'] = $beat;
                    $wedges[] = $w;
                }

                // Pedals
                foreach ($this->findAll($child, 'pedal') as $pedalEl) {
                    $p = ['type' => $pedalEl->getAttribute('type')];
                    $line = $pedalEl->getAttribute('line');
                    if ($line !== '') $p['line'] = ($line === 'yes');
                    if ($beat > 1.0) $p['beat'] = $beat;
                    $pedals[] = $p;
                }

                // Text directions
                foreach ($this->findAll($child, 'words') as $wordsEl) {
                    $txt = trim($wordsEl->textContent);
                    if ($txt !== '') {
                        if ($beat > 1.0) {
                            $directions[] = ['text' => $txt, 'beat' => $beat];
                        } else {
                            $directions[] = $txt;
                        }
                    }
                }
            }
        }

        if ($dynamics)   $m['dynamics']   = $dynamics;
        if ($wedges)     $m['wedges']     = $wedges;
        if ($pedals)     $m['pedals']     = $pedals;
        if ($directions) $m['directions'] = $directions;
        if ($notes)      $m['notes']      = $notes;

        return $m;
    }

    private function buildNote(\DOMElement $noteEl): array
    {
        $n = [];

        // Voice and staff
        $voice = $this->domText($noteEl, 'voice');
        $staff = $this->domText($noteEl, 'staff');
        if ($voice !== null) $n['voice'] = (int)$voice;
        if ($staff !== null) $n['staff'] = (int)$staff;

        // Pitch, unpitched (percussion), or rest
        $restEl      = $this->findDirectChild($noteEl, 'rest');
        $pitchEl     = $this->findDirectChild($noteEl, 'pitch');
        $unpitchedEl = $this->findDirectChild($noteEl, 'unpitched');
        if ($restEl !== null) {
            $n['pitch'] = 'rest';
        } elseif ($pitchEl !== null) {
            $n['pitch'] = $this->pitchName($pitchEl);
        } elseif ($unpitchedEl !== null) {
            $n['pitch'] = $this->unpitchedName($unpitchedEl);
        }

        // Duration type and dots
        $type = $this->domText($noteEl, 'type');
        if ($type !== null) $n['type'] = $type;

        $dots = 0;
        foreach ($noteEl->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->localName === 'dot') $dots++;
        }
        if ($dots > 0) $n['dots'] = $dots;

        // Chord
        if ($this->findDirectChild($noteEl, 'chord') !== null) {
            $n['chord'] = true;
        }

        // Beaming
        $beams = [];
        foreach ($noteEl->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->localName === 'beam') {
                $txt = trim($child->textContent);
                if ($txt !== '') $beams[] = $txt;
            }
        }
        if ($beams) $n['beam'] = $beams;

        // Grace note
        if ($this->findDirectChild($noteEl, 'grace') !== null) {
            $n['grace'] = true;
        }

        // Tie — collect all <tie> types; mid-chain notes (both start+stop) omit the field
        $tieTypes = [];
        foreach ($noteEl->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->localName === 'tie') {
                $t = $child->getAttribute('type');
                if ($t !== '') $tieTypes[] = $t;
            }
        }
        if (count($tieTypes) === 1) {
            $n['tie'] = $tieTypes[0];
        }

        // Articulations
        $artics = [];
        foreach ($this->findAll($noteEl, 'articulations') as $articContainer) {
            foreach ($articContainer->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $artics[] = $child->localName;
                }
            }
        }
        if ($artics) $n['artic'] = $artics;

        // Ornaments
        $ornaments = [];
        foreach ($this->findAll($noteEl, 'ornaments') as $ornContainer) {
            foreach ($ornContainer->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $ornaments[] = $child->localName;
                }
            }
        }
        if ($ornaments) $n['ornament'] = $ornaments;

        // Slur
        foreach ($this->findAll($noteEl, 'slur') as $slurEl) {
            $slurType = $slurEl->getAttribute('type');
            if ($slurType !== '') {
                $n['slur'] = $slurType;
                break;
            }
        }

        // Tuplet
        foreach ($this->findAll($noteEl, 'tuplet') as $tupletEl) {
            if ($tupletEl->getAttribute('type') === 'start') {
                $actual = $this->domText($noteEl, './/actual-notes');
                $normal = $this->domText($noteEl, './/normal-notes');
                // DOMDocument doesn't support .// in simple lookups; use findAll
                foreach ($this->findAll($noteEl, 'actual-notes') as $an) {
                    $actual = trim($an->textContent);
                    break;
                }
                foreach ($this->findAll($noteEl, 'normal-notes') as $nn) {
                    $normal = trim($nn->textContent);
                    break;
                }
                if ($actual !== null && $normal !== null) {
                    $n['tuplet'] = "{$actual}:{$normal}";
                }
                break;
            }
        }

        return $n;
    }

    // -----------------------------------------------------------------------
    // JSON output
    // -----------------------------------------------------------------------

    private function writeJson(array $data, string $path, bool $pretty): void
    {
        if ($pretty) {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($path, $json . "\n");
            return;
        }

        // One-measure-per-line per part: valid JSON, compact, sequentially readable.
        $flags = JSON_UNESCAPED_UNICODE;
        $fp    = fopen($path, 'w');

        // Write header (everything except parts)
        $header = array_filter($data, fn($k) => $k !== 'parts', ARRAY_FILTER_USE_KEY);
        $headerJson = json_encode($header, $flags);
        // Remove closing brace, we'll append parts
        fwrite($fp, substr($headerJson, 0, -1));

        fwrite($fp, ',"parts":[' . "\n");

        $parts = $data['parts'];
        foreach ($parts as $pi => $part) {
            // Write part header
            $partHeader = ['id' => $part['id'], 'name' => $part['name']];
            fwrite($fp, json_encode($partHeader, $flags));
            // Remove closing brace, splice in measures
            // Actually, let's build it properly:
            fseek($fp, -1, SEEK_CUR); // back up over the }

            fwrite($fp, ',"measures":[' . "\n");

            $measures = $part['measures'];
            $lastM    = count($measures) - 1;
            foreach ($measures as $mi => $m) {
                $line = json_encode($m, $flags);
                fwrite($fp, $line);
                if ($mi < $lastM) fwrite($fp, ',');
                fwrite($fp, "\n");
            }

            fwrite($fp, ']}');
            if ($pi < count($parts) - 1) fwrite($fp, ',');
            fwrite($fp, "\n");
        }

        fwrite($fp, "]}\n");
        fclose($fp);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Convert a cumulative duration (in division units) to a 1-based beat number.
     * Returns a float rounded to 2 decimal places (e.g., 1.0, 2.5, 3.33).
     */
    private function durationToBeat(int $durationUnits): float
    {
        if ($this->currentDivisions <= 0) return 1.0;
        // divisions = units per quarter note = units per beat (in x/4 time)
        return round(($durationUnits / $this->currentDivisions) + 1, 2);
    }

    private function pitchName(\DOMElement $pitchEl): string
    {
        $step   = $this->domText($pitchEl, 'step') ?? '';
        $alter  = $this->domText($pitchEl, 'alter') ?? '0';
        $octave = $this->domText($pitchEl, 'octave') ?? '';

        try {
            $alterKey = (string)(int)round((float)$alter);
        } catch (\Throwable) {
            $alterKey = '0';
        }

        $acc = self::ACCIDENTALS[$alterKey] ?? '';
        return "{$step}{$acc}{$octave}";
    }

    /**
     * Build a display-pitch string for an unpitched percussion note.
     * MusicXML uses <display-step> and <display-octave> instead of <step>/<octave>.
     */
    private function unpitchedName(\DOMElement $el): string
    {
        $step   = $this->domText($el, 'display-step') ?? '';
        $octave = $this->domText($el, 'display-octave') ?? '';
        return "{$step}{$octave}";
    }

    /**
     * Get text content of a child element by tag name.
     */
    private function domText(\DOMElement $parent, string $tagName): ?string
    {
        // Simple single-level lookup
        foreach ($parent->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->localName === $tagName) {
                $text = trim($child->textContent);
                return $text !== '' ? $text : null;
            }
        }
        return null;
    }

    /**
     * Find first direct child element by local name.
     */
    private function findDirectChild(\DOMElement $parent, string $name): ?\DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->localName === $name) {
                return $child;
            }
        }
        return null;
    }

    /**
     * Find all direct child elements by local name.
     * @return \DOMElement[]
     */
    private function findDirectChildren(\DOMElement $parent, string $name): array
    {
        $result = [];
        foreach ($parent->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->localName === $name) {
                $result[] = $child;
            }
        }
        return $result;
    }

    /**
     * Find first descendant element by local name.
     */
    private function findDescendant(\DOMElement $parent, string $name): ?\DOMElement
    {
        $elements = $parent->getElementsByTagName($name);
        return $elements->length > 0 ? $elements->item(0) : null;
    }

    /**
     * Find all descendant elements by local name.
     * @return \DOMElement[]
     */
    private function findAll(\DOMElement $parent, string $name): array
    {
        $result   = [];
        $elements = $parent->getElementsByTagName($name);
        for ($i = 0; $i < $elements->length; $i++) {
            $result[] = $elements->item($i);
        }
        return $result;
    }
}
