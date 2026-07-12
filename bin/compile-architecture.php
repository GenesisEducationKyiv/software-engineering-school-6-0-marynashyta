<?php

/**
 * compile-architecture.php — compile per-service architecture docs + source trees into a
 * single platform landscape document.
 *
 * For each service (and the optional shared source tree) declared in the manifest it:
 *   - verifies the source tree and the ARCHITECTURE.md exist,
 *   - detects which layers are present under src/ and counts PHP classes per layer,
 *   - lifts the "## 1. Overview" section out of the service's ARCHITECTURE.md,
 * then writes docs/LANDSCAPE.md with: a Mermaid landscape diagram (from the declared edges),
 * a service registry table, and a per-service summary linking back to full docs.
 *
 * Plain PHP, no dependencies. Usage:
 *   php bin/compile-architecture.php [--manifest=path] [--check] [--allow-missing]
 *
 * Exit codes: 0 = OK; 1 = missing sources/docs or a bad edge (drift). Use --check in CI to
 * validate without writing, and --allow-missing to warn instead of fail (e.g. polyrepo CI
 * where not every service is checked out).
 */

declare(strict_types=1);

const LAYER_NAMES = ['Domain', 'Application', 'Infrastructure', 'Presentation', 'Shared'];

/**
 * @param list<string> $argv
 * @return array{manifest:string,check:bool,allowMissing:bool}
 */
function parseArgs(array $argv): array
{
    $opts = ['manifest' => getcwd() . '/architecture.php', 'check' => false, 'allowMissing' => false];
    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--manifest=')) {
            $opts['manifest'] = substr($arg, 11);
        } elseif ($arg === '--check') {
            $opts['check'] = true;
        } elseif ($arg === '--allow-missing') {
            $opts['allowMissing'] = true;
        } else {
            fwrite(STDERR, "Unknown option: {$arg}\n");
            exit(2);
        }
    }
    return $opts;
}

function nodeId(string $name): string
{
    return preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'node';
}

function str(mixed $v): string
{
    return is_scalar($v) ? (string) $v : '';
}

/**
 * @return list<array<int|string,mixed>>
 */
function arrOfArrays(mixed $v): array
{
    if (!is_array($v)) {
        return [];
    }
    $out = [];
    foreach ($v as $item) {
        if (is_array($item)) {
            $out[] = $item;
        }
    }
    return $out;
}

/**
 * @param array<int|string,mixed> $e
 * @return Edge
 */
function toEdge(array $e): array
{
    return [
        'from'  => str($e['from'] ?? ''),
        'to'    => str($e['to'] ?? ''),
        'via'   => str($e['via'] ?? ''),
        'style' => str($e['style'] ?? ''),
    ];
}

/**
 * Detect layers present under a source tree and count *.php files in each.
 * @return array<string,int>
 */
function scanLayers(string $srcPath): array
{
    $found = [];
    foreach (LAYER_NAMES as $layer) {
        $dir = $srcPath . DIRECTORY_SEPARATOR . $layer;
        if (!is_dir($dir)) {
            continue;
        }
        $count = 0;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'php') {
                $count++;
            }
        }
        $found[$layer] = $count;
    }
    return $found;
}

/**
 * Lift a numbered "## N. <heading matching $headingPattern>" section (up to the next
 * "## ") from an ARCHITECTURE.md. $headingPattern is a regex fragment matched against the
 * heading text, e.g. 'Overview' or 'System context\s*\(C4 L1\)'.
 */
function extractSection(string $docFile, string $headingPattern): string
{
    if (!is_file($docFile)) {
        return '';
    }
    $md = (string) file_get_contents($docFile);
    if (!preg_match('/^##\s+\d+\.\s*' . $headingPattern . '\s*$(.*?)(?=^##\s+|\z)/ims', $md, $m)) {
        return '';
    }

    $lines = array_filter(
        array_map('rtrim', explode("\n", trim($m[1]))),
        static fn (string $l): bool => !str_starts_with(ltrim($l), '>')
    );
    return trim(implode("\n", $lines));
}

/** Lift the "## 1. Overview" section from an ARCHITECTURE.md. */
function extractOverview(string $docFile): string
{
    return extractSection($docFile, 'Overview');
}

// Labelled heading patterns for the C4 diagram sections every service ARCHITECTURE.md
// follows (System context / Containers / Components). Matched loosely so a doc that
// varies the exact wording (e.g. the root domain map, which has no C4 sections at all)
// simply yields nothing for that entry rather than erroring.
const C4_SECTIONS = [
    'System Context (C4 L1)' => 'System context\s*\(C4 L1\)',
    'Containers (C4 L2)'     => 'Containers\s*\(C4 L2\)',
    'Components (C4 L3)'     => 'Components.*\(C4 L3\)',
];

/** @return array<string,string> label => lifted section content, only for sections found */
function extractC4Diagrams(string $docFile): array
{
    $diagrams = [];
    foreach (C4_SECTIONS as $label => $pattern) {
        $content = extractSection($docFile, $pattern);
        if ($content !== '') {
            $diagrams[$label] = $content;
        }
    }
    return $diagrams;
}

/**
 * @param array<int|string,mixed> $unit
 * @return Node
 */
function inspectUnit(array $unit, bool $isShared = false): array
{
    $root = rtrim(str($unit['path'] ?? ''), '/');
    $srcPath = $root . '/' . ltrim(str($unit['src'] ?? 'src'), '/');
    $docFile = $root . '/' . ltrim(str($unit['docs'] ?? 'docs/architecture/ARCHITECTURE.md'), '/');

    $problems = [];
    if (!is_dir($root)) {
        $problems[] = "path not found: {$root}";
    } elseif (!is_dir($srcPath)) {
        $problems[] = "src not found: {$srcPath}";
    }
    if (!is_file($docFile)) {
        $problems[] = "architecture doc not found: {$docFile}";
    }

    return [
        'name'      => str($unit['name'] ?? ''),
        'owner'     => str($unit['owner'] ?? '—'),
        'namespace' => str($unit['namespace'] ?? '—'),
        'root'      => $root,
        'src'       => is_dir($srcPath) ? $srcPath : null,
        'doc'       => is_file($docFile) ? $docFile : null,
        'layers'    => is_dir($srcPath) ? scanLayers($srcPath) : [],
        'overview'  => extractOverview($docFile),
        'diagrams'  => is_file($docFile) ? extractC4Diagrams($docFile) : [],
        'problems'  => $problems,
        'group'     => isset($unit['group']) ? str($unit['group']) : null,
        'isShared'  => $isShared,
        'isExternal' => false,
    ];
}

/**
 * An external system (a third-party API, a message broker, an SMTP relay — anything the
 * platform depends on but doesn't own the code or docs for). No src/docs to verify; it exists
 * on the landscape purely so edges can point at it and so it renders distinctly from a
 * service the platform actually owns.
 * @param array<int|string,mixed> $ext
 * @return Node
 */
function inspectExternal(array $ext): array
{
    return [
        'name'       => str($ext['name'] ?? ''),
        'owner'      => '—',
        'namespace'  => '—',
        'root'       => '',
        'src'        => null,
        'doc'        => null,
        'layers'     => [],
        'overview'   => '',
        'diagrams'   => [],
        'problems'   => [],
        'group'      => isset($ext['group']) ? str($ext['group']) : null,
        'isShared'   => false,
        'isExternal' => true,
    ];
}

/**
 * Render one node with a shape that hints at its kind: cylinder for the shared/contracts
 * tree (a library, not a running process), stadium for a deployable service, hexagon for an
 * external system the platform doesn't own.
 * @param Node $n
 */
function renderNodeShape(array $n): string
{
    $label = $n['owner'] !== '—' ? $n['name'] . '<br/><small>' . $n['owner'] . '</small>' : $n['name'];
    $id = nodeId($n['name']);

    if ($n['isExternal']) {
        return sprintf('%s{{"%s"}}', $id, $label);
    }

    return $n['isShared']
        ? sprintf('%s[("%s")]', $id, $label)
        : sprintf('%s(["%s"])', $id, $label);
}

/**
 * @param Node[] $nodes
 * @param Edge[] $edges
 */
function renderMermaid(array $nodes, array $edges): string
{
    $themeVariables = "{'background':'#0d1117','primaryColor':'#161b22','primaryTextColor':'#e6edf3',"
        . "'primaryBorderColor':'#58a6ff','lineColor':'#8b949e','secondaryColor':'#161b22',"
        . "'tertiaryColor':'#0f1420','tertiaryTextColor':'#c9d1d9','tertiaryBorderColor':'#30363d',"
        . "'fontFamily':'Arial'}";
    $lines = [
        '```mermaid',
        "%%{init: {'theme':'base', 'themeVariables': {$themeVariables}}}%%",
        'flowchart LR',
    ];

    $lines[] = '    classDef service fill:#1f6feb,stroke:#58a6ff,color:#fff,stroke-width:1px;';
    $lines[] = '    classDef shared fill:#3fb950,stroke:#2ea043,color:#fff,stroke-width:1px;';
    $lines[] = '    classDef external fill:#6e7681,stroke:#8b949e,color:#fff,stroke-width:1px;';
    $lines[] = '';

    $grouped = [];
    $ungrouped = [];
    foreach ($nodes as $n) {
        if ($n['group'] !== null) {
            $grouped[$n['group']][] = $n;
        } else {
            $ungrouped[] = $n;
        }
    }

    foreach ($grouped as $groupName => $groupNodes) {
        $lines[] = sprintf('    subgraph %s["%s"]', nodeId($groupName), $groupName);
        foreach ($groupNodes as $n) {
            $lines[] = '        ' . renderNodeShape($n);
        }
        $lines[] = '    end';
        $lines[] = sprintf(
            '    style %s fill:#0f1420,stroke:#f0883e,stroke-width:2px,color:#e6edf3',
            nodeId($groupName)
        );
    }
    foreach ($ungrouped as $n) {
        $lines[] = '    ' . renderNodeShape($n);
    }

    foreach ($edges as $e) {
        // solid for sync calls; dashed for async events and shared-library usage. The label
        // is quoted since `via` text routinely contains parentheses/commas, which Mermaid
        // can't parse in an unquoted edge label.
        $arrow = $e['style'] === 'sync' ? '-->' : '-.->';
        $lines[] = sprintf('    %s %s|"%s"| %s', nodeId($e['from']), $arrow, $e['via'], nodeId($e['to']));
    }

    $lines[] = '';
    foreach ($nodes as $n) {
        $cls = $n['isExternal'] ? 'external' : ($n['isShared'] ? 'shared' : 'service');
        $lines[] = sprintf('    class %s %s;', nodeId($n['name']), $cls);
    }

    $lines[] = '```';
    return implode("\n", $lines);
}

/** Best-effort relative path from $fromDir to $to, so generated links stay portable. */
function relativePath(string $to, string $fromDir): string
{
    $to = str_replace('\\', '/', $to);
    $fromDir = str_replace('\\', '/', $fromDir);
    $canon = static function (string $p): array {
        $out = [];
        foreach (explode('/', trim($p, '/')) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($out);
            } else {
                $out[] = $seg;
            }
        }
        return $out;
    };
    $from = $canon($fromDir);
    $dest = $canon($to);
    while ($from !== [] && $dest !== [] && $from[0] === $dest[0]) {
        array_shift($from);
        array_shift($dest);
    }
    return str_repeat('../', count($from)) . implode('/', $dest);
}

/**
 * @param array<string,mixed> $manifest
 * @param Node[] $units
 * @param Node[] $externals
 * @param Edge[] $edges
 */
function renderDocument(array $manifest, array $units, array $externals, array $edges, string $outDir): string
{
    $platform = str($manifest['platform'] ?? 'Platform');
    $now = date('Y-m-d H:i');

    $out = [];
    $out[] = "# {$platform} — architecture landscape";
    $out[] = '';
    $out[] = "> Generated by `bin/compile-architecture.php` on {$now}. Do not edit by hand —";
    $out[] = "> change the services or the platform manifest and recompile.";
    $out[] = '';

    $out[] = '## System landscape';
    $out[] = '';
    $out[] = renderMermaid([...$units, ...$externals], $edges);
    $out[] = '';
    $out[] = 'Solid = synchronous call · dashed = asynchronous/event or shared library. Grey hexagons';
    $out[] = 'are external systems the platform depends on but doesn\'t own (no source/docs to link to).';
    $out[] = '';

    $out[] = '## Services';
    $out[] = '';
    $out[] = '| Service | Owner | Namespace | Layers present | Docs |';
    $out[] = '|---|---|---|---|---|';
    foreach ($units as $u) {
        $layerParts = [];
        foreach ($u['layers'] as $l => $c) {
            $layerParts[] = "{$l} ({$c})";
        }
        $layers = $layerParts === [] ? '—' : implode(', ', $layerParts);
        $rel = $u['doc'] !== null ? relativePath($u['doc'], $outDir) : null;
        $docLink = $rel !== null ? "[ARCHITECTURE.md]({$rel})" : '**missing**';
        $out[] = "| {$u['name']} | {$u['owner']} | `{$u['namespace']}` | {$layers} | {$docLink} |";
    }
    $out[] = '';

    $out[] = '## Service summaries';
    $out[] = '';
    foreach ($units as $u) {
        $out[] = "### {$u['name']}";
        $out[] = '';
        if ($u['overview'] !== '') {
            $out[] = $u['overview'];
        } elseif ($u['doc'] !== null) {
            $out[] = "_No `## 1. Overview` section found in the service doc._";
        } else {
            $out[] = "_No architecture document found for this service._";
        }
        $out[] = '';

        if ($u['diagrams'] !== []) {
            foreach ($u['diagrams'] as $label => $content) {
                $out[] = "#### {$label}";
                $out[] = '';
                $out[] = $content;
                $out[] = '';
            }
        } elseif ($u['doc'] !== null) {
            $out[] = "_No C4 diagrams (System context / Containers / Components) found in the service doc._";
            $out[] = '';
        }

        if ($u['doc'] !== null) {
            $rel = relativePath($u['doc'], $outDir);
            $out[] = "Full documentation: [{$rel}]({$rel})";
            $out[] = '';
        }
    }

    return implode("\n", $out) . "\n";
}

$opts = parseArgs($argv);

if (!is_file($opts['manifest'])) {
    fwrite(STDERR, "Manifest not found: {$opts['manifest']}\n");
    exit(2);
}

/** @var array<string,mixed> $manifest */
$manifest = require $opts['manifest'];

$units = [];
$shared = $manifest['shared'] ?? null;
if (is_array($shared) && $shared !== []) {
    $units[] = inspectUnit($shared, isShared: true);
}
foreach (arrOfArrays($manifest['services'] ?? null) as $service) {
    $units[] = inspectUnit($service);
}

$externals = [];
foreach (arrOfArrays($manifest['externals'] ?? null) as $external) {
    $externals[] = inspectExternal($external);
}

$allNodes = [...$units, ...$externals];
$names = array_map(static fn (array $u): string => $u['name'], $allNodes);
$problems = [];
foreach ($units as $u) {
    foreach ($u['problems'] as $p) {
        $problems[] = "[{$u['name']}] {$p}";
    }
}
$edges = array_map('toEdge', arrOfArrays($manifest['edges'] ?? null));
foreach ($edges as $e) {
    foreach (['from', 'to'] as $end) {
        if (!in_array($e[$end], $names, true)) {
            $problems[] = "edge references unknown node '{$e[$end]}' (in {$e['from']} -> {$e['to']})";
        }
    }
}

fwrite(
    STDOUT,
    'Inspected ' . count($units) . ' unit(s) and ' . count($externals) . ' external(s): '
        . implode(', ', $names) . "\n"
);

if ($problems !== []) {
    fwrite(STDERR, "\nProblems detected:\n");
    foreach ($problems as $p) {
        fwrite(STDERR, "  - {$p}\n");
    }
    if (!$opts['allowMissing']) {
        fwrite(STDERR, "\nAborting (use --allow-missing to warn instead of fail).\n");
        exit(1);
    }
    fwrite(STDERR, "\nContinuing despite problems (--allow-missing).\n");
}

$outPathDefault = dirname($opts['manifest']) . '/docs/LANDSCAPE.md';
$outPathPreview = isset($manifest['output']) ? str($manifest['output']) : $outPathDefault;
$document = renderDocument($manifest, $units, $externals, $edges, dirname($outPathPreview));

if ($opts['check']) {
    fwrite(STDOUT, $problems === []
        ? "Check mode: manifest valid, document not written.\n"
        : "Check mode: manifest has problems (see above), document not written.\n");
    exit($problems === [] ? 0 : 1);
}

$outPath = $outPathPreview;
if (!is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0o775, true);
}
file_put_contents($outPath, $document);
fwrite(STDOUT, "Wrote {$outPath} (" . strlen($document) . " bytes).\n");
exit(0);
