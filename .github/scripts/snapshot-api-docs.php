<?php

/**
 * Downloads the published PrivatBank Autoclient API documentation (a published Google Doc)
 * and writes it as normalized plain text, so that two snapshots can be compared with git diff.
 *
 * Usage: php snapshot-api-docs.php <url-or-local-html-file> <output-file>
 *
 * Exits with a non-zero code (and leaves the output file untouched) when the page cannot be
 * downloaded or looks wrong, so a temporary outage never shows up as "the docs were deleted".
 */
const MIN_LENGTH = 10_000;

/** Lines that change on every request or are not part of the documentation itself. */
const NOISE_PATTERNS = [
    '/^Сторінку опубліковано за допомогою Google Документів$/u',
    '/^Повідомити про порушення/u',
    '/^Оновлюється автоматично кожні \d+ хв$/u',
    '/^Published using Google Docs$/u',
    '/^Report abuse/u',
    '/^Updated automatically every \d+ minutes$/u',
];

const BLOCK_TAGS = ['p', 'div', 'br', 'li', 'tr', 'table', 'ul', 'ol', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'pre', 'blockquote', 'hr'];

function fail(string $message): never
{
    fwrite(STDERR, "Error: {$message}\n");
    exit(1);
}

function download(string $source): string
{
    if (! preg_match('#^https?://#', $source)) {
        return @file_get_contents($source) ?: fail("cannot read {$source}");
    }

    $context = stream_context_create(['http' => [
        'timeout' => 30,
        'header' => "User-Agent: privatbank-autoclient-docs-watcher\r\n",
        'ignore_errors' => true,
    ]]);

    $html = @file_get_contents($source, false, $context);
    $status = isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m) ? (int) $m[1] : 0;

    if ($html === false || $status !== 200) {
        fail("download failed (HTTP {$status})");
    }

    return $html;
}

function toText(string $html): string
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    foreach (['script', 'style', 'noscript'] as $tag) {
        foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    // Published Google Docs keep the document body in <div id="contents">.
    $root = $dom->getElementById('contents') ?? $dom->getElementsByTagName('body')->item(0) ?? $dom;

    // Put a line break after every block element and a space after table cells,
    // so paragraphs and cells do not glue together.
    foreach (BLOCK_TAGS as $tag) {
        foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $node) {
            $node->appendChild($dom->createTextNode("\n"));
        }
    }

    foreach (['td', 'th'] as $tag) {
        foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $node) {
            $node->appendChild($dom->createTextNode(' '));
        }
    }

    return $root->textContent;
}

function normalize(string $text): string
{
    $text = str_replace(["\u{00A0}", "\u{200B}", "\r"], [' ', '', ''], $text);

    $lines = [];

    foreach (explode("\n", $text) as $line) {
        $line = trim(preg_replace('/[ \t]+/u', ' ', $line) ?? $line);

        foreach (NOISE_PATTERNS as $pattern) {
            if (preg_match($pattern, $line)) {
                continue 2;
            }
        }

        // At most one empty line in a row.
        if ($line === '' && ($lines === [] || end($lines) === '')) {
            continue;
        }

        $lines[] = $line;
    }

    return trim(implode("\n", $lines))."\n";
}

[$script, $source, $output] = $argv + [null, null, null];

if ($source === null || $output === null) {
    fail("usage: php {$script} <url-or-file> <output-file>");
}

$text = normalize(toText(download($source)));

if (mb_strlen($text) < MIN_LENGTH) {
    fail('the page is too short ('.mb_strlen($text).' characters), probably not the documentation');
}

if (! str_contains($text, 'statements/balance')) {
    fail('the page does not mention statements/balance, probably not the documentation');
}

if (! is_dir(dirname($output))) {
    mkdir(dirname($output), recursive: true);
}

file_put_contents($output, $text);

echo 'Saved '.mb_strlen($text)." characters to {$output}\n";
