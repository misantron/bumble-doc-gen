<?php

declare(strict_types=1);

namespace BumbleDocGen\Plugin\CorePlugin\BasePhpStubber;

use BumbleDocGen\Plugin\Event\Entity\OnCheckIsClassEntityCanBeLoad;
use BumbleDocGen\Plugin\Event\Render\OnGettingResourceLink;
use BumbleDocGen\Plugin\PluginInterface;

final class SymfonyComponentStubberPlugin implements PluginInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            OnGettingResourceLink::class => 'onGettingResourceLink',
            OnCheckIsClassEntityCanBeLoad::class => 'onCheckIsClassEntityCanBeLoad',
        ];
    }

    final public function onGettingResourceLink(OnGettingResourceLink $event): void
    {
        if (!$event->getResourceUrl()) {
            $resourceName = $event->getResourceName();
            if (!str_starts_with($resourceName, '\\')) {
                $resourceName = "\\{$resourceName}";
            }
            if (str_starts_with($resourceName, '\\Symfony\\Component\\')) {
                $resourceName = str_replace(['\\Symfony\\Component\\', '\\'], ['', '/'], $resourceName);

                $resourceNameParts = explode('/', $resourceName);

                $packageName = array_shift($resourceNameParts);
                $resourceName = implode('/', $resourceNameParts);

                $packageName = lcfirst($packageName);
                $packageName = preg_replace("/[A-Z]/",  "-$0", $packageName);
                $packageName = strtolower($packageName);

                $event->setResourceUrl("https://github.com/symfony/{$packageName}/blob/master/{$resourceName}.php");
            }
        }
    }

    final public function onCheckIsClassEntityCanBeLoad(OnCheckIsClassEntityCanBeLoad $event): void
    {
        if (
            str_starts_with($event->getEntity()->getName(), 'Symfony\\Component\\') ||
            str_starts_with($event->getEntity()->getName(), '\\Symfony\\Component\\')
        ) {
            $event->disableClassLoading();
        }
    }
}
