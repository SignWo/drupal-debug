<?php

if (!function_exists('opcache_invalidate')) {
    function opcache_invalidate(string $script, bool $force = false): bool
    {
        return true;
    }
}

if (!defined('XDEBUG_CC_DEAD_CODE')) {
    define('XDEBUG_CC_DEAD_CODE', 1);
}

if (!defined('XDEBUG_CC_UNUSED')) {
    define('XDEBUG_CC_UNUSED', 2);
}

require sprintf('%s/../../vendor/autoload.php', __DIR__);

// We don't want to put it in composer autoload because when the DrupalKernel
// discovers services providers, it actually does a "class_exists". It is easier
// to write the WatchContainerDefinitionsTest if it's not autoloaded.
// However, we still want PHPStan to analyze this file.
require sprintf('%s/../../tests/Integration/Action/WatchContainerDefinitions/fixtures/ServiceProviderTemplate.php', __DIR__);

// Those template classes must not be loaded in actions integration tests.
// However, we still want PHPStan to analyze these files.
require sprintf('%s/../../tests/Integration/Action/DisableDynamicPageCache/fixtures/ControllerTemplate.php', __DIR__);
require sprintf('%s/../../tests/Integration/Action/DisableInternalPageCache/fixtures/ControllerTemplate.php', __DIR__);
require sprintf('%s/../../tests/Integration/Action/DisableRenderCache/fixtures/ControllerTemplate.php', __DIR__);
