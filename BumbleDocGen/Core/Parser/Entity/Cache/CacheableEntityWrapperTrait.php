<?php

declare(strict_types=1);

namespace BumbleDocGen\Core\Parser\Entity\Cache;

use BumbleDocGen\Core\Cache\EntityCacheItemPool;
use BumbleDocGen\Core\Configuration\Exception\InvalidConfigurationParameterException;
use DI\Attribute\Inject;
use Psr\Cache\InvalidArgumentException;

trait CacheableEntityWrapperTrait
{
    #[Inject] private EntityCacheItemPool $entityCacheItemPool;
    #[Inject] private EntityCacheStorageHelper $entityCacheStorageHelper;

    abstract public function entityCacheIsOutdated(): bool;

    abstract public function getCachedEntityDependencies(): array;

    abstract protected function getEntityCacheValue(string $key): mixed;

    abstract protected function hasEntityCacheValue(string $key): bool;

    abstract protected function addEntityValueToCache(string $key, mixed $value, int $cacheExpiresAfter): void;

    /**
     * @throws InvalidConfigurationParameterException
     * @throws InvalidArgumentException
     */
    final protected function getWrappedMethodResult(
        string $methodName,
        array  $funcArgs,
        string $getCacheKeyGeneratorClassName,
        string $cacheNamespace,
        int    $cacheExpiresAfter
    )
    {
        $cacheKey = $getCacheKeyGeneratorClassName::generateKey(
            $cacheNamespace,
            $this,
            $funcArgs
        );

        if ($this->hasEntityCacheValue($cacheKey) && !$this->entityCacheIsOutdated()) {
            $methodReturnValue = $this->getEntityCacheValue($cacheKey);
        } else {
            $methodReturnValue = call_user_func_array(['parent', $methodName], $funcArgs);
            if (count($this->getCachedEntityDependencies()) > 1) {
                $this->addEntityValueToCache($cacheKey, $methodReturnValue, $cacheExpiresAfter);
            }
        }
        return $methodReturnValue;
    }
}
