<?php

/**
 * @return array<int, string>
 */
function allVueFiles(): array
{
    $root = realpath(__DIR__.'/../..');
    $files = [];

    foreach (frontendFiles() as $file) {
        if ($file->getExtension() === 'vue') {
            $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }

    sort($files);

    return $files;
}

const COLOUR_PATTERNS = ['raw palette colour', 'white/black utility', 'hex colour'];

const FORBIDDEN_PATTERNS = [
    'raw palette colour' => '/\b(?:bg|text|border|ring|divide|from|to|via|fill|stroke|outline|placeholder|accent|caret|shadow)-(?:gray|slate|zinc|neutral|stone|indigo|blue|red|green|yellow|amber|emerald|purple|orange|rose|teal|cyan|sky|violet|pink|lime|fuchsia)-\d{2,3}\b/',
    'dark: variant' => '/\bdark:/',
    'white/black utility' => '/\b(?:text|ring)-(?:white|black)\b|\bborder(?:-[trblxyse])?-(?:white|black)\b|\bbg-white\b|\bbg-black\b(?!\/)/',
    'hex colour' => '/#[0-9a-fA-F]{3,8}\b/',
    'physical direction' => '/\b(?:ml|mr|pl|pr|left|right)-(?:\d|auto)|\btext-(?:left|right)\b|\b(?:border|rounded)-[lr](?:-|\b)/',
    'removed v4 utility' => '/\b(?:ring|bg|border)-opacity-\d+|\bflex-shrink-|\boutline-none\b/',
];

it('keeps migrated files on theme tokens', function (string $file) {
    $path = __DIR__.'/../../'.$file;
    expect(file_exists($path))->toBeTrue("{$file} does not exist");

    $violations = [];
    foreach (file($path) as $number => $line) {
        $colourExempt = str_contains($line, 'allow-colour');

        foreach (FORBIDDEN_PATTERNS as $label => $pattern) {
            if ($colourExempt && in_array($label, COLOUR_PATTERNS, true)) {
                continue;
            }

            if (preg_match($pattern, $line, $match)) {
                $violations[] = sprintf('%s:%d %s (%s)', $file, $number + 1, $label, $match[0]);
            }
        }
    }

    expect($violations)->toBe([]);
})->with(allVueFiles());
