<?php

namespace Cli;

/**
 * CLI Command to Parse Vite Manifest and Pre-cache Production React Asset Paths (`php cli.php build:react`).
 * 
 * @package Cli
 */
class BuildReact extends Command
{
    /**
     * Executes the React asset cache compilation workflow.
     * 
     * @param array $args CLI arguments list.
     * @return int
     */
    public function run(array $args): int
    {
        $this->banner('LilaPHP React Asset Cache Compiler');

        $manifestPath = __DIR__ . '/../../frontend/js/.vite/manifest.json';
        $outputFile = __DIR__ . '/../cache/build_cache.php';

        if (!file_exists($manifestPath)) {
            $this->error("Vite manifest not found at: {$manifestPath}");
            $this->warning("Run `npm run build` or `php cli.php docker prod` first.");
            return 1;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (!$manifest) {
            $this->error("Failed to parse Vite manifest.json");
            return 1;
        }

        $scripts = [];
        $styles = [];

        $entry = $manifest['frontend/src/main.jsx'] ?? null;
        if ($entry) {
            if (!empty($entry['file'])) {
                $scripts[] = '<script type="module" src="/frontend/js/.vite/' . htmlspecialchars($entry['file']) . '"></script>';
            }
            if (!empty($entry['css'])) {
                foreach ($entry['css'] as $cssFile) {
                    $styles[] = '<link rel="stylesheet" href="/frontend/js/.vite/' . htmlspecialchars($cssFile) . '">';
                }
            }
        }

        $content = "<?php\n\n/**\n * Auto-generated Vite React asset manifest cache.\n * Compiled: " . date('Y-m-d H:i:s') . "\n */\n\nreturn " . var_export([
            'scripts' => $scripts,
            'styles' => $styles,
        ], true) . ";\n";

        if (file_put_contents($outputFile, $content) !== false) {
            $this->success("Successfully generated production asset cache: _core/cache/build_cache.php");
            return 0;
        }

        $this->error("Failed to write build cache to: {$outputFile}");
        return 1;
    }
}
