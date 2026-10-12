<?php

const REQUIRED_TOKENS = [
    'bg', 'surface', 'field', 'text', 'muted', 'faint', 'placeholder', 'line', 'line-strong', 'line-2',
    'hover-wash', 'disabled-wash', 'gold-on', 'accent-text', 'accent-fill', 'accent-wash',
    'accent-wash-hover', 'accent-border',
    'status-danger', 'status-danger-wash', 'status-danger-border',
    'status-warn', 'status-warn-wash', 'status-warn-border',
    'status-success', 'status-success-wash', 'status-success-border',
    'status-info', 'status-info-wash', 'status-info-border',
    'ring-accent', 'ring-danger', 'ring-success',
    'overlay-pill', 'overlay-chip', 'overlay-chip-hover', 'overlay-chip-ink',
    'pop-shadow', 'lift-shadow',
    'gold', 'gold-deep', 'ink', 'paper', 'sand', 'danger',
];

/**
 * @return array<string, string>
 */
function themeBlockTokens(string $css, string $selector): array
{
    $pattern = '/(?:^|\n)'.preg_quote($selector, '/').'\s*\{(.*?)\n\}/s';
    preg_match($pattern, $css, $block);
    preg_match_all('/--([a-z0-9-]+):\s*([^;]+);/', $block[1] ?? '', $matches, PREG_SET_ORDER);

    $tokens = [];
    foreach ($matches as $match) {
        $tokens[$match[1]] = trim($match[2]);
    }

    return $tokens;
}

/**
 * @return array{0: float, 1: float, 2: float, 3: float}
 */
function themeParseColor(string $value): array
{
    if (preg_match('/^#([0-9a-f]{6})$/i', $value, $m)) {
        return [hexdec(substr($m[1], 0, 2)), hexdec(substr($m[1], 2, 2)), hexdec(substr($m[1], 4, 2)), 1.0];
    }

    if (preg_match('/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*(?:,\s*([\d.]+)\s*)?\)$/', $value, $m)) {
        return [(float) $m[1], (float) $m[2], (float) $m[3], isset($m[4]) ? (float) $m[4] : 1.0];
    }

    throw new InvalidArgumentException("Unparseable colour: {$value}");
}

/**
 * @param  array{0: float, 1: float, 2: float, 3: float}  $foreground
 * @param  array{0: float, 1: float, 2: float, 3: float}  $background
 * @return array{0: float, 1: float, 2: float, 3: float}
 */
function themeBlend(array $foreground, array $background): array
{
    $alpha = $foreground[3];

    return [
        $alpha * $foreground[0] + (1 - $alpha) * $background[0],
        $alpha * $foreground[1] + (1 - $alpha) * $background[1],
        $alpha * $foreground[2] + (1 - $alpha) * $background[2],
        1.0,
    ];
}

/**
 * @param  array{0: float, 1: float, 2: float, 3: float}  $colour
 */
function themeLuminance(array $colour): float
{
    $channels = array_map(function (float $channel): float {
        $c = $channel / 255;

        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }, array_slice($colour, 0, 3));

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

/**
 * @param  array<string, string>  $tokens
 */
function themeContrast(array $tokens, string $foreground, string $background, string $over = 'bg'): float
{
    $base = themeParseColor($tokens[$over]);
    $bg = themeBlend(themeParseColor($tokens[$background]), $base);
    $fg = themeBlend(themeParseColor($tokens[$foreground]), $bg);
    $lighter = max(themeLuminance($fg), themeLuminance($bg));
    $darker = min(themeLuminance($fg), themeLuminance($bg));

    return ($lighter + 0.05) / ($darker + 0.05);
}

dataset('themes', [
    'light' => [':root'],
    'dark' => ["html[data-theme='dark']"],
]);

it('defines every documented token in both themes', function (string $selector) {
    $css = file_get_contents(uiSource().'/theme.css');
    $tokens = themeBlockTokens($css, ':root');

    if ($selector !== ':root') {
        $tokens = array_merge($tokens, themeBlockTokens($css, $selector));
        expect(themeBlockTokens($css, $selector))->not->toBeEmpty();
    }

    foreach (REQUIRED_TOKENS as $name) {
        expect($tokens)->toHaveKey($name);
    }
})->with('themes');

it('keeps text colours at 4.5:1 or better on their surfaces', function (string $selector) {
    $css = file_get_contents(uiSource().'/theme.css');
    $tokens = array_merge(themeBlockTokens($css, ':root'), $selector === ':root' ? [] : themeBlockTokens($css, $selector));

    $pairs = [
        ['text', 'bg'], ['text', 'surface'], ['text', 'field'],
        ['muted', 'bg'], ['muted', 'surface'], ['muted', 'field'],
        ['faint', 'bg'], ['faint', 'surface'],
        ['accent-text', 'surface'], ['accent-text', 'bg'], ['accent-text', 'accent-wash', 'surface'],
        ['ink', 'gold'], ['ink', 'gold-deep'], ['ink', 'accent-fill'],
        ['overlay-chip-ink', 'overlay-chip', 'surface'],
    ];

    foreach (['danger', 'warn', 'success', 'info'] as $status) {
        $pairs[] = ["status-{$status}", 'surface'];
        $pairs[] = ["status-{$status}", "status-{$status}-wash", 'surface'];
    }

    foreach ($pairs as $pair) {
        $ratio = themeContrast($tokens, $pair[0], $pair[1], $pair[2] ?? 'bg');
        expect($ratio)->toBeGreaterThanOrEqual(4.5, "{$pair[0]} on {$pair[1]} is {$ratio}");
    }
})->with('themes');
