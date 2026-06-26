<?php

/**
 * UIHook companion plugin for IliasTraxEventBridge.
 *
 * This plugin is intentionally lightweight. It exposes course-level UI entry
 * points and delegates storage/filtering to the main EventHook plugin.
 */
class ilIliasTraxEventBridgeCourseUIPlugin extends ilUserInterfaceHookPlugin
{
    public const PLUGIN_NAME = 'IliasTraxEventBridgeCourseUI';
    public const MAIN_PLUGIN_NAME = 'IliasTraxEventBridge';

    public function getPluginName(): string
    {
        return self::PLUGIN_NAME;
    }

    public function isMainPluginAvailable(): bool
    {
        return is_dir($this->getMainPluginPath());
    }

    public function getMainPluginPath(): string
    {
        return dirname(__DIR__, 4)
            . '/EventHandling/EventHook/'
            . self::MAIN_PLUGIN_NAME;
    }
}
