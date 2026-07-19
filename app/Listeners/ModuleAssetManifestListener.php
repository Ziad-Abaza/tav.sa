<?php

namespace App\Listeners;

use App\Services\ModuleAssetManager;
use Corbital\ModuleManager\Facades\ModuleEvents;
use Illuminate\Support\Facades\Log;

/**
 * Module Asset Manifest Hooks
 *
 * Automatically manages module asset manifest when modules are activated/deactivated.
 * Hooks into the module-manager lifecycle events.
 */
class ModuleAssetManifestListener
{
    protected ModuleAssetManager $assetManager;

    public function __construct(ModuleAssetManager $assetManager)
    {
        $this->assetManager = $assetManager;
    }

    /**
     * Register hooks with the module event system
     */
    public function subscribe(): void
    {
        // Hook into after_activate to add module to manifest
        ModuleEvents::listen('module.after_activate', function ($hookData = null) {
            // Handle both array and raw data
            if (! is_array($hookData)) {
                $hookData = ['module_name' => $hookData];
            }
            $this->handleModuleActivated($hookData);

            return $hookData; // Return data for chain
        }, 5); // Lower priority to run after core activation

        // Hook into after_deactivate to remove module from manifest
        ModuleEvents::listen('module.after_deactivate', function ($hookData = null) {
            // Handle both array and raw data
            if (! is_array($hookData)) {
                $hookData = ['module_name' => $hookData];
            }
            $this->handleModuleDeactivated($hookData);

            return $hookData; // Return data for chain
        }, 5);

        // Hook into module_uploaded to auto-merge manifest on upload
        ModuleEvents::listen('module.module_uploaded', function ($hookData = null) {
            // Handle both array and raw data
            if (! is_array($hookData)) {
                $hookData = ['module_name' => $hookData];
            }
            $this->handleModuleUploaded($hookData);

            return $hookData; // Return data for chain
        }, 5);

        // Hook into after_remove to clean up manifest
        ModuleEvents::listen('module.after_remove', function ($hookData = null) {
            // Handle both array and raw data
            if (! is_array($hookData)) {
                $hookData = ['module_name' => $hookData];
            }
            $this->handleModuleRemoved($hookData);

            return $hookData; // Return data for chain
        }, 5);
    }

    /**
     * Handle module activation - add to manifest
     */
    protected function handleModuleActivated(?array $hookData = []): void
    {
        $moduleName = $hookData['module_name'] ?? null;

        if (! $moduleName) {
            return;
        }

        try {
            // Check if module has compiled assets
            if (! $this->assetManager->hasCompiledAssets($moduleName)) {
                Log::warning("Module {$moduleName} activated but has no compiled assets", [
                    'module' => $moduleName,
                    'hook' => 'after_activate',
                ]);

                return;
            }

            // Add module to manifest
            $this->assetManager->addModuleToManifest($moduleName);
        } catch (\Exception $e) {
            Log::error("Failed to add module assets to manifest: {$moduleName}", [
                'module' => $moduleName,
                'error' => $e->getMessage(),
                'hook' => 'after_activate',
            ]);
        }
    }

    /**
     * Handle module deactivation - remove from manifest
     */
    protected function handleModuleDeactivated(?array $hookData = []): void
    {
        $moduleName = $hookData['module_name'] ?? null;

        if (! $moduleName) {
            return;
        }

        try {
            $this->assetManager->removeModuleFromManifest($moduleName);
        } catch (\Exception $e) {
            Log::error("Failed to remove module assets from manifest: {$moduleName}", [
                'module' => $moduleName,
                'error' => $e->getMessage(),
                'hook' => 'after_deactivate',
            ]);
        }
    }

    /**
     * Handle module upload - auto-merge manifest if assets exist
     */
    protected function handleModuleUploaded(?array $hookData = []): void
    {
        $moduleName = $hookData['module_name'] ?? null;

        if (! $moduleName) {
            return;
        }

        try {
            // Check if module has pre-compiled assets
            if ($this->assetManager->hasCompiledAssets($moduleName)) {
                // Note: Don't add to manifest yet - wait for activation
                // This just validates that assets exist
            } else {
                Log::warning("Module uploaded without pre-compiled assets: {$moduleName}", [
                    'module' => $moduleName,
                    'hook' => 'module_uploaded',
                    'message' => 'Module may not work correctly without running npm build',
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error checking module assets on upload: {$moduleName}", [
                'module' => $moduleName,
                'error' => $e->getMessage(),
                'hook' => 'module_uploaded',
            ]);
        }
    }

    /**
     * Handle module removal - clean up manifest
     */
    protected function handleModuleRemoved(?array $hookData = []): void
    {
        $moduleName = $hookData['module_name'] ?? null;

        if (! $moduleName) {
            return;
        }

        try {
            $this->assetManager->removeModuleFromManifest($moduleName);
        } catch (\Exception $e) {
            Log::error("Failed to clean up module assets: {$moduleName}", [
                'module' => $moduleName,
                'error' => $e->getMessage(),
                'hook' => 'after_remove',
            ]);
        }
    }
}
