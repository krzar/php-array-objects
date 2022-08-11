<?php

namespace KrZar\PhpArrayObjects;

use Closure;
use JetBrains\PhpStorm\Pure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

abstract class ArrayObject
{
    private const PROPERTIES_TO_IGNORE = ['arrayMap', 'namesMap'];

    protected array $arrayMap = [];
    protected array $namesMap = [];
    protected array $typesMap = [];

    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function create(array $data): static
    {
        $class = get_called_class();

        return (new $class($data))->generate();
    }

    public function generate(): static
    {
        $reflectionClass = new ReflectionClass($this);
        $properties = $reflectionClass->getProperties();

        foreach ($properties as $property) {
            $this->assignProperty($property);
        }

        return $this;
    }

    protected function typesMap(): array
    {
        return [];
    }

    private function assignProperty(ReflectionProperty $property)
    {
        $name = $property->getName();

        if ($type = $this->getCorrectType($property)) {
            if ($this->isArrayObject($type)) {
                $this->assignCustom($name, $type);
            } else {
                $this->assignBuildIn($name);
            }
        }
    }

    private function assignBuildIn(string $name)
    {
        if ($className = $this->getArrayMapClass($name)) {
            $value = Generator::generateMultiple($className, $this->getValueByName($name));
        } else {
            $value = $this->getValueByName($name);
        }

        $value = $this->fixValueByType($name, $value);
        $this->{$name} = $value;
    }

    private function assignCustom(string $name, ReflectionNamedType $type)
    {
        $className = $type->getName();
        $value = $this->getValueByName($name);

        if ($value instanceof ArrayObject) {
            $this->{$name} = $value;
        } else {
            $this->{$name} = Generator::generate($className, $value);
        }
    }

    private function isPropertyToAssign(string $name): bool
    {
        return !in_array($name, self::PROPERTIES_TO_IGNORE) && $this->isDataSet($name);
    }

    private function isDataSet(string $name): bool
    {
        return isset($this->data[$this->getCorrectItemName($name)]);
    }

    private function getValueByName(string $name): mixed
    {
        return $this->data[$this->getCorrectItemName($name)];
    }

    private function getCorrectItemName(string $name): string
    {
        return $this->namesMap[$name] ?? $name;
    }

    private function getArrayMapClass(string $name): ?string
    {
        return $this->arrayMap[$name] ?? null;
    }

    private function getCorrectType(ReflectionProperty $property): ?ReflectionNamedType
    {
        $name = $property->getName();

        if ($this->isPropertyToAssign($name)) {
            $type = $property->getType();

            if ($type instanceof ReflectionUnionType) {
                return $this->getCurrentTypeFromUnion($type, $this->getValueByName($name));
            }

            return $type;
        }

        return null;
    }

    #[Pure] private function getCurrentTypeFromUnion(
        ReflectionUnionType $unionType,
        mixed               $dataItem
    ): ?ReflectionNamedType
    {
        if (is_array($dataItem)) {
            foreach ($unionType->getTypes() as $type) {
                if (!$type->isBuiltin()) {
                    return $type;
                }
            }
        } else {
            foreach ($unionType->getTypes() as $type) {
                if ($type->isBuiltin()) {
                    return $type;
                }
            }
        }

        return null;
    }

    #[Pure] private function typeToMap(string $name): Closure|string|null
    {
        $typesMap = array_merge($this->typesMap, $this->typesMap());

        return $typesMap[$name] ?? null;
    }

    private function fixValueByType(string $name, mixed $value)
    {
        $type = $this->typeToMap($name);

        if ($type) {
            if ($type instanceof Closure) {
                $value = $type($value, $this->data);
            } else {
                settype($value, $type);
            }
        }

        return $value;
    }

    private function isArrayObject(ReflectionNamedType $type): bool
    {
        $className = $type->getName();

        if (class_exists($className)) {
            return new $className([]) instanceof ArrayObject;
        }

        return false;
    }
}
