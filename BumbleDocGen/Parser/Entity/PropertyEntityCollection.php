<?php

declare(strict_types=1);

namespace BumbleDocGen\Parser\Entity;

use BumbleDocGen\ConfigurationInterface;
use BumbleDocGen\Parser\AttributeParser;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\Reflector;

final class PropertyEntityCollection extends BaseEntityCollection
{
    public static function createByReflectionClass(
        ConfigurationInterface $configuration,
        Reflector $reflector,
        ReflectionClass $reflectionClass,
        AttributeParser $attributeParser,
    ): PropertyEntityCollection {
        $propertyEntityCollection = new PropertyEntityCollection();
        foreach ($reflectionClass->getProperties() as $propertyReflection) {
            $propertyEntity = PropertyEntity::create(
                $configuration,
                $reflector,
                $reflectionClass,
                $propertyReflection,
                $attributeParser
            );
            if (
                $configuration->propertyEntityFilterCondition($propertyEntity)->canAddToCollection()
            ) {
                $propertyEntityCollection->add($propertyEntity);
            }
        }
        return $propertyEntityCollection;
    }

    public function add(PropertyEntity $propertyEntity, bool $reload = false): PropertyEntityCollection
    {
        $key = $propertyEntity->getName();
        if (!isset($this->entities[$key]) || $reload) {
            $this->entities[$key] = $propertyEntity;
        }
        return $this;
    }

    public function get(string $key): ?PropertyEntity
    {
        return $this->entities[$key] ?? null;
    }
}
