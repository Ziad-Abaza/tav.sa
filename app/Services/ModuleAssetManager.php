<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Module Asset Manager
 *
 * Manages module asset compilation and manifest merging.
 * Each module builds independently with its own manifest.json
 * This service merges all active module manifests into a single file.
 */
class ModuleAssetManager
{
    protected string $rootManifestPath;

    protected string $modulesPath;

    protected string $modulesStatusFile;

    public function __construct()
    {
        $this->rootManifestPath = public_path('build/modules-manifest.json');
        $this->modulesPath = base_path('Modules');
        $this->modulesStatusFile = base_path('modules_statuses.json');
    }

    /**
     * Rebuild complete module manifest from all enabled modules
     */
    public function rebuildManifest(): void
    {
        $enabledModules = $this->getEnabledModules();
        $mergedManifest = [];

        foreach ($enabledModules as $moduleName) {
            $manifest = $this->getModuleManifest($moduleName);

            if (empty($manifest)) {
                continue;
            }

            foreach ($manifest as $key => $entry) {
                $mergedManifest["modules/{$moduleName}/{$key}"] = $this->prefixPaths($moduleName, $entry);
            }
        }

        $this->saveManifest($mergedManifest);

        Log::info('Module manifest rebuilt: '.count($enabledModules).' active modules');
    }

    /**
     * Add single module to manifest (incremental)
     */
    public function addModuleToManifest(string $moduleName): bool
    {
        $currentManifest = $this->loadManifest();
        $moduleManifest = $this->getModuleManifest($moduleName);

        if (empty($moduleManifest)) {
            Log::warning("No manifest found for module: {$moduleName}");

            return false;
        }

        foreach ($moduleManifest as $key => $entry) {
            $currentManifest["modules/{$moduleName}/{$key}"] = $this->prefixPaths($moduleName, $entry);
        }

        $this->saveManifest($currentManifest);

        Log::info("Added module to manifest: {$moduleName}");

        return true;
    }

    /**
     * Remove module from manifest
     */
    public function removeModuleFromManifest(string $moduleName): void
    {
        $currentManifest = $this->loadManifest();
        $prefix = "modules/{$moduleName}/";

        $currentManifest = array_filter(
            $currentManifest,
            fn ($key) => ! str_starts_with($key, $prefix),
            ARRAY_FILTER_USE_KEY
        );

        $this->saveManifest($currentManifest);

        Log::info("Removed module from manifest: {$moduleName}");
    }

    /**
     * Get module manifest.json content
     */
    protected function getModuleManifest(string $moduleName): array
    {
        $manifestPath = "{$this->modulesPath}/{$moduleName}/public/build/manifest.json";

        if (! File::exists($manifestPath)) {
            return [];
        }

        try {
            $content = File::get($manifestPath);

            return json_decode($content, true) ?? [];
        } catch (\Exception $e) {
            Log::error("Failed to read manifest for module {$moduleName}: ".$e->getMessage());

            return [];
        }
    }

    /**
     * Prefix all asset paths with module directory
     * Uses /modules/ (lowercase) to match the public/modules/ symlink path
     */
    protected function prefixPaths(string $moduleName, array $entry): array
    {
        // Use lowercase 'modules' to match public/modules/{ModuleName} symlink
        $basePath = "/modules/{$moduleName}";

        $prefixed = [
            'file' => "{$basePath}/{$entry['file']}",
            'src' => $entry['src'] ?? '',
            'isEntry' => $entry['isEntry'] ?? false,
        ];

        // Prefix CSS files
        if (isset($entry['css']) && is_array($entry['css'])) {
            $prefixed['css'] = array_map(
                fn ($css) => "{$basePath}/{$css}",
                $entry['css']
            );
        }

        // Prefix dynamicImports
        if (isset($entry['dynamicImports']) && is_array($entry['dynamicImports'])) {
            $prefixed['dynamicImports'] = $entry['dynamicImports'];
        }

        // Prefix imports
        if (isset($entry['imports']) && is_array($entry['imports'])) {
            $prefixed['imports'] = $entry['imports'];
        }

        return $prefixed;
    }

    /**
     * Get list of enabled modules from modules_statuses.json
     */
    protected function getEnabledModules(): array
    {
        if (! File::exists($this->modulesStatusFile)) {
            return [];
        }

        try {
            $statuses = json_decode(File::get($this->modulesStatusFile), true);

            return array_keys(array_filter($statuses, fn ($enabled) => $enabled === true));
        } catch (\Exception $e) {
            Log::error('Failed to read modules_statuses.json: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Load current merged manifest
     */
    protected function loadManifest(): array
    {
        if (! File::exists($this->rootManifestPath)) {
            return [];
        }

        try {
            return json_decode(File::get($this->rootManifestPath), true) ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to read modules manifest: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Save merged manifest
     */
    protected function saveManifest(array $manifest): void
    {
        File::ensureDirectoryExists(dirname($this->rootManifestPath));
        File::put(
            $this->rootManifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Check if module has compiled assets
     */
    public function hasCompiledAssets(string $moduleName): bool
    {
        $manifestPath = "{$this->modulesPath}/{$moduleName}/public/build/manifest.json";
        $buildPath = "{$this->modulesPath}/{$moduleName}/public/build";

        return File::exists($manifestPath) && File::isDirectory($buildPath);
    }

    /**
     * Get module asset statistics
     */
    public function getModuleAssetStats(string $moduleName): array
    {
        $manifest = $this->getModuleManifest($moduleName);

        if (empty($manifest)) {
            return [
                'has_assets' => false,
                'entry_count' => 0,
                'css_count' => 0,
            ];
        }

        $cssCount = 0;
        foreach ($manifest as $entry) {
            if (isset($entry['css']) && is_array($entry['css'])) {
                $cssCount += count($entry['css']);
            }
        }

        return [
            'has_assets' => true,
            'entry_count' => count($manifest),
            'css_count' => $cssCount,
        ];
    }
}
