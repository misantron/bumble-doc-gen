<?php

declare(strict_types=1);

namespace BumbleDocGen;

use BumbleDocGen\Parser\ProjectParser;
use BumbleDocGen\Plugin\PluginEventDispatcher;
use BumbleDocGen\Render\Render;

/**
 * Class for generating documentation.
 */
final class DocGenerator
{
    /**
     * Generates documentation using configuration
     *
     * @param ConfigurationInterface $configuration
     * @return void
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public static function generateDocumentation(ConfigurationInterface $configuration): void
    {
        $start = microtime(true);
        $memory = memory_get_usage();

        $pluginEventDispatcher = new PluginEventDispatcher($configuration);
        $classEntityCollection = ProjectParser::create($configuration, $pluginEventDispatcher)->parse();
        (new Render($configuration, $classEntityCollection, $pluginEventDispatcher))->run();

        $logger = $configuration->getLogger();
        $time = microtime(true) - $start;
        $logger->notice("Time of execution: {$time} sec.");
        $memory = memory_get_usage() - $memory;
        $logger->notice('Memory:' . self::bitesToString($memory));
    }

    private static function bitesToString(int $bites): string
    {
        $i = 0;
        while (floor($bites / 1024) > 0) {
            $i++;
            $bites /= 1024;
        }
        $name = ['bites', 'KB', 'MB'];
        return round($bites, 2) . ' ' . $name[$i];
    }
}
