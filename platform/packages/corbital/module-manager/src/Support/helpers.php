<?php

if (! function_exists('module_path')) {
    /**
     * Get the path to a module directory.
     *
     * @param  string  $name  Module name
     * @param  string  $path  Path within the module
     * @return string
     */
    function module_path($name, $path = '')
    {
        $modulePath = rtrim(config('modules.directory', app_path('Modules')), '/').'/'.$name;

        return $path ? $modulePath.'/'.ltrim($path, '/') : $modulePath;
    }
}

if (! function_exists('module_asset')) {
    /**
     * Get versioned module asset URL from manifest (with backward compatibility)
     *
     * @param  string  $name  Module name
     * @param  string  $path  Asset path relative to module
     * @return string Full URL to the versioned asset
     */
    function module_asset($name, $path)
    {
        static $manifest = null;

        // Load manifest once
        if ($manifest === null) {
            $manifestPath = public_path('build/modules-manifest.json');
            $manifest = file_exists($manifestPath)
                ? json_decode(file_get_contents($manifestPath), true)
                : [];
        }

        // Try manifest-based asset loading (for standalone compiled modules)
        $key = "modules/{$name}/{$path}";
        if (isset($manifest[$key]['file'])) {
            return $manifest[$key]['file'];
        }

        // Fallback 1: Check if module has standalone build directory
        $standaloneAsset = "/Modules/{$name}/public/build/".ltrim($path, '/');
        if (file_exists(public_path($standaloneAsset))) {
            return $standaloneAsset;
        }

        // Fallback 2: Old system - public/modules/{module}/{path}
        // This maintains backward compatibility with existing modules
        $assetPath = 'modules/'.strtolower($name).'/'.ltrim($path, '/');

        return asset($assetPath);
    }
}

if (! function_exists('module_config')) {
    /**
     * Get a module configuration value.
     *
     * @param  string  $name  Module name
     * @param  string  $key  Configuration key (dot notation supported)
     * @param  mixed  $default  Default value if key not found
     * @return mixed
     */
    function module_config($name, $key, $default = null)
    {
        $configKey = strtolower($name).'.'.$key;

        return config($configKey, $default);
    }
}

if (! function_exists('module_view')) {
    /**
     * Get the evaluated view contents for a module.
     *
     * @param  string  $name  Module name
     * @param  string  $view  View name within the module
     * @param  array  $data  View data
     * @param  array  $mergeData  Additional view data to merge
     * @return \Illuminate\View\View
     */
    function module_view($name, $view, $data = [], $mergeData = [])
    {
        $viewName = strtolower($name).'::'.$view;

        return view($viewName, $data, $mergeData);
    }
}

if (! function_exists('module_lang')) {
    /**
     * Get a module translation string.
     *
     * @param  string  $name  Module name
     * @param  string  $key  Translation key
     * @param  array  $replace  Replace parameters
     * @param  string|null  $locale  Locale to use
     * @return string
     */
    function module_lang($name, $key, $replace = [], $locale = null)
    {
        $langKey = strtolower($name).'::'.$key;

        return trans($langKey, $replace, $locale);
    }
}

if (! function_exists('module_exists')) {
    /**
     * Check if a module exists.
     *
     * @param  string  $name  Module name
     * @return bool
     */
    function module_exists($name)
    {
        return app('module.manager')->has($name);
    }
}

if (! function_exists('module_enabled')) {
    /**
     * Check if a module is enabled.
     *
     * @param  string  $name  Module name
     * @return bool
     */
    function module_enabled($name)
    {
        return app('module.manager')->isActive($name);
    }
}

if (! function_exists('module_disabled')) {
    /**
     * Check if a module is disabled.
     *
     * @param  string  $name  Module name
     * @return bool
     */
    function module_disabled($name)
    {
        return ! app('module.manager')->isActive($name);
    }
}
