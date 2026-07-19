<?php

if (! function_exists('module_asset')) {
    /**
     * Get versioned module asset URL
     */
    function module_asset(string $module, string $path): string
    {
        static $manifest = null;

        if ($manifest === null) {
            $manifestPath = public_path('build/modules-manifest.json');
            $manifest = file_exists($manifestPath)
                ? json_decode(file_get_contents($manifestPath), true)
                : [];
        }

        $key = "modules/{$module}/{$path}";

        if (isset($manifest[$key]['file'])) {
            return $manifest[$key]['file'];
        }

        // Fallback: direct path (development mode)
        return "/modules/{$module}/".ltrim($path, '/');
    }
}

if (! function_exists('module_script')) {
    /**
     * Include module script tag with dependencies
     */
    function module_script(string $module, string $path): string
    {
        static $globalManifest = null;
        static $moduleManifests = [];

        if ($globalManifest === null) {
            $manifestPath = public_path('build/modules-manifest.json');
            $globalManifest = file_exists($manifestPath)
                ? json_decode(file_get_contents($manifestPath), true)
                : [];
        }

        // Load the module's local manifest for chunk resolution
        if (! isset($moduleManifests[$module])) {
            $moduleManifestPath = base_path("Modules/{$module}/public/build/manifest.json");
            $moduleManifests[$module] = file_exists($moduleManifestPath)
                ? json_decode(file_get_contents($moduleManifestPath), true)
                : [];
        }

        $key = "modules/{$module}/{$path}";
        $html = '';

        if (isset($globalManifest[$key])) {
            $entry = $globalManifest[$key];
            $moduleManifest = $moduleManifests[$module];

            // Load imported chunks first (dependencies like vue-runtime, grapesjs-core)
            if (isset($entry['imports']) && is_array($entry['imports'])) {
                foreach ($entry['imports'] as $import) {
                    // Look up the import in the module's manifest to get its actual file path
                    if (isset($moduleManifest[$import]['file'])) {
                        $importPath = "/modules/{$module}/".ltrim($moduleManifest[$import]['file'], '/');
                        $html .= sprintf('<link rel="modulepreload" href="%s">', $importPath)."\n";
                    }
                }
            }

            // Load associated CSS files
            if (isset($entry['css']) && is_array($entry['css'])) {
                foreach ($entry['css'] as $cssFile) {
                    $html .= sprintf('<link rel="stylesheet" href="%s">', $cssFile)."\n";
                }
            }

            // Load main entry script
            $html .= sprintf('<script src="%s" type="module"></script>', $entry['file']);
        } else {
            // Fallback: direct path (development mode)
            $url = "/modules/{$module}/".ltrim($path, '/');
            $html = sprintf('<script src="%s" type="module"></script>', $url);
        }

        return $html;
    }
}

if (! function_exists('module_style')) {
    /**
     * Include module stylesheet tag
     */
    function module_style(string $module, string $path): string
    {
        $url = module_asset($module, $path);

        return sprintf('<link rel="stylesheet" href="%s">', $url);
    }
}
