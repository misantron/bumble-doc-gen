<?php

declare(strict_types=1);

namespace BumbleDocGen\Parser\Entity\Cache;

use BumbleDocGen\ConfigurationInterface;
use BumbleDocGen\LanguageHandler\Php\Parser\Entity\ClassEntity;
use BumbleDocGen\LanguageHandler\Php\Parser\Entity\ClassEntityCollection;
use BumbleDocGen\LanguageHandler\Php\Parser\Entity\ConstantEntity;
use BumbleDocGen\LanguageHandler\Php\Parser\Entity\MethodEntity;
use BumbleDocGen\LanguageHandler\Php\Parser\Entity\PropertyEntity;
use Nette\PhpGenerator\Parameter;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\Reflector;

final class CacheableEntityWrapperFactory
{
    private static function createForEntity(string $className, string $wrapperName): string
    {
        static $entityWrapperClassNames = [];

        if (!isset($entityWrapperClassNames[$wrapperName])) {
            $namespaceName = 'BumbleDocGen\\Parser\\Entity\\Cache';

            $namespace = new \Nette\PhpGenerator\PhpNamespace($namespaceName);
            $class = $namespace->addClass($wrapperName);
            $class->setExtends($className);
            $class->addTrait(CacheableEntityWrapperTrait::class);
            $class->addImplement(CacheableEntityWrapperInterface::class);

            $reflectionClass = new \ReflectionClass($className);
            foreach ($reflectionClass->getMethods() as $method) {
                if (!$method->isFinal() && !$method->isStatic()) {
                    $cacheableMethodAttr = $method->getAttributes(CacheableMethod::class)[0] ?? null;
                    if ($cacheableMethodAttr) {
                        $newMethod = $class->addMethod($method->getName())
                            ->setStatic($method->isStatic())
                            ->setVariadic($method->isVariadic())
                            ->setReturnType((string)$method->getReturnType());

                        $parameters = [];
                        foreach ($method->getParameters() as $parameter) {
                            $parameter = new Parameter($parameter->getName());
                            $parameter->setDefaultValue($parameter->getDefaultValue());
                            $parameters[] = $parameter;
                        }
                        $newMethod->setParameters($parameters);

                        $cacheNamespace = "{$wrapperName}_{$method->getName()}";

                        $cacheableMethodAttrObj = $cacheableMethodAttr->newInstance();
                        $expiresAfter = time() + $cacheableMethodAttrObj->getCacheSeconds();
                        $newMethod->setBody('
                            $funcArgs = func_get_args();
                            $cacheKey = \\' . $cacheableMethodAttrObj->getCacheKeyGeneratorClass() . '::generateKey(
                                \'' . $cacheNamespace . '\',
                                $this,
                                $funcArgs
                            );
                            
                            $internalDataKey = "__data__";
                            
                            $result = $this->getCacheValue($cacheKey);
                            if(!is_array($result) || !array_key_exists($internalDataKey, $result) || $this->entityCacheIsOutdated()) {
                                $methodReturnValue = parent::' . $method->getName() . '(...$funcArgs);
                                $result = [
                                    $internalDataKey => $methodReturnValue,
                                    "__expires_after__" => ' . $expiresAfter . '
                                ];
                                $this->addValueToCache($cacheKey, $result);
                            }
                            return $result[$internalDataKey];
                        ');
                    }
                }
            }

            eval((string)$namespace);
            $entityWrapperClassNames[$wrapperName] = "{$namespaceName}\\$wrapperName";
        }
        return $entityWrapperClassNames[$wrapperName];
    }

    public static function createPropertyEntity(
        ClassEntity $classEntity,
        string      $propertyName,
        string      $declaringClassName,
        string      $implementingClassName,
        bool        $reloadCache = false
    ): PropertyEntity
    {
        $wrapperClassName = self::createForEntity(PropertyEntity::class, 'PropertyEntityWrapper');
        return $wrapperClassName::create(
            $classEntity,
            $propertyName,
            $declaringClassName,
            $implementingClassName,
            $reloadCache
        );
    }

    public static function createConstantEntity(
        ClassEntity $classEntity,
        string      $constantName,
        string      $declaringClassName,
        string      $implementingClassName,
        bool        $reloadCache = false
    ): ConstantEntity
    {
        $wrapperClassName = self::createForEntity(ConstantEntity::class, 'ConstantEntityWrapper');
        return $wrapperClassName::create(
            $classEntity,
            $constantName,
            $declaringClassName,
            $implementingClassName,
            $reloadCache
        );
    }

    public static function createMethodEntity(
        ClassEntity $classEntity,
        string      $methodName,
        string      $declaringClassName,
        string      $implementingClassName,
        bool        $reloadCache = false
    ): MethodEntity
    {
        $wrapperClassName = self::createForEntity(MethodEntity::class, 'MethodEntityWrapper');
        return $wrapperClassName::create(
            $classEntity,
            $methodName,
            $declaringClassName,
            $implementingClassName,
            $reloadCache
        );
    }

    public static function createClassEntity(
        ConfigurationInterface $configuration,
        Reflector              $reflector,
        ClassEntityCollection  $classEntityCollection,
        string                 $className,
        ?string                $relativeFileName = null,
        bool                   $reloadCache = false
    ): ClassEntity
    {
        $wrapperClassName = self::createForEntity(ClassEntity::class, 'ClassEntityWrapper');
        return $wrapperClassName::create(
            $configuration,
            $reflector,
            $classEntityCollection,
            $className,
            $relativeFileName,
            $reloadCache
        );
    }

    public static function createClassEntityByReflection(
        ConfigurationInterface $configuration,
        Reflector              $reflector,
        ReflectionClass        $reflectionClass,
        ClassEntityCollection  $classEntityCollection,
        bool                   $reloadCache = false
    ): ClassEntity
    {
        $wrapperClassName = self::createForEntity(ClassEntity::class, 'ClassEntityWrapper');
        return $wrapperClassName::createByReflection(
            $configuration,
            $reflector,
            $reflectionClass,
            $classEntityCollection,
            $reloadCache
        );
    }

    public static function createSubClassEntity(
        string                 $subClassEntity,
        ConfigurationInterface $configuration,
        Reflector              $reflector,
        ClassEntityCollection  $classEntityCollection,
        string                 $className,
        ?string                $relativeFileName,
        bool                   $reloadCache = false
    ): ClassEntity
    {
        $classNameParts = explode('\\', $subClassEntity);
        $subClassEntityName = end($classNameParts);
        $wrapperClassName = self::createForEntity($subClassEntity, "{$subClassEntityName}Wrapper");
        return $wrapperClassName::create(
            $configuration,
            $reflector,
            $classEntityCollection,
            $className,
            $relativeFileName,
            $reloadCache
        );
    }

    public static function createSubClassEntityByReflection(
        string                 $subClassEntity,
        ConfigurationInterface $configuration,
        Reflector              $reflector,
        ReflectionClass        $reflectionClass,
        ClassEntityCollection  $classEntityCollection,
        bool                   $reloadCache = false
    ): ClassEntity
    {
        $classNameParts = explode('\\', $subClassEntity);
        $className = end($classNameParts);
        $wrapperClassName = self::createForEntity($subClassEntity, "{$className}Wrapper");
        return $wrapperClassName::createByReflection(
            $configuration,
            $reflector,
            $reflectionClass,
            $classEntityCollection,
            $reloadCache
        );
    }
}
