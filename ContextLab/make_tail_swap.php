<?php
// Build the "tail-swapped" variant of a faux-context JSON: keep messages
// [0..cutIndex] identical, replace everything after with a single digest
// message. Used for the cache-boundary tail-swap experiment.
//
// Usage: php make_tail_swap.php <input.json> <cutIndex> <output.json>

[$script, $input, $cutIndex, $output] = $argv;
$cutIndex = (int)$cutIndex;

$data = json_decode(file_get_contents($input), true);
$messages = $data['messages'];

$prefix = array_slice($messages, 0, $cutIndex + 1);
$tail = array_slice($messages, $cutIndex + 1);

$tailChars = 0;
foreach ($tail as $m) { $tailChars += strlen($m['content']); }

$digest = sprintf(
    '[DIGEST replacing %d messages / ~%d chars of cold conversation history: '
    . 'the discussion covered several technical topics not repeated verbatim here.]',
    count($tail),
    $tailChars
);

$swapped = array_merge($prefix, [
    ['role' => 'user', 'content' => $digest],
]);

file_put_contents($output, json_encode([
    'system' => $data['system'] ?? null,
    'messages' => $swapped,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo "prefix_messages=" . count($prefix) . " tail_replaced=" . count($tail)
    . " tail_chars=" . $tailChars . " -> {$output}\n";
