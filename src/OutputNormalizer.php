<?php

namespace Alexanevsky\OutputNormalizerBundle;

use Alexanevsky\GetterSetterAccessorBundle\GetterSetterAccessor;
use Alexanevsky\OutputNormalizerBundle\Exception\InvalidOutputClassException;
use Alexanevsky\OutputNormalizerBundle\ObjectNormalizer\ObjectNormalizerInterface;
use Alexanevsky\OutputNormalizerBundle\Output\Attribute\EntityToId;
use Alexanevsky\OutputNormalizerBundle\Output\OutputInterface;
use Alexanevsky\OutputNormalizerBundle\OutputModifier\OutputModifierInterface;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use function Symfony\Component\String\u;

class OutputNormalizer
{
    /**
     * @param iterable<ObjectNormalizerInterface> $objectNormalizers
     * @param iterable<OutputModifierInterface> $outputModifiers
     */
    public function __construct(
        #[TaggedIterator('alexanevsky.output_normalizer.object_normalizer')]
        private iterable $objectNormalizers,

        #[TaggedIterator('alexanevsky.output_normalizer.output_modifier')]
        private iterable $outputModifiers,

        private GetterSetterAccessor $getterSetterAccessor
    ) {
    }

    /**
     * @param class-string<OutputInterface>|null $outputClass
     */
    public function normalize(mixed $data, ?string $outputClass = null): mixed
    {
        if (is_object($data)) {
            return $this->normalizeObject($data, $outputClass);
        } elseif (is_array($data)) {
            return $this->normalizeArray($data, $outputClass);
        }

        return $data;
    }

    /**
     * @param class-string<OutputInterface>|null $outputClass
     */
    private function normalizeObject(object $object, ?string $outputClass = null): mixed
    {
        if ($object instanceof Collection) {
            return $this->normalize($object->toArray(), $outputClass);
        } elseif ($object instanceof \Traversable) {
            return $this->normalize(iterator_to_array($object), $outputClass);
        }

        if ($outputClass) {
            $object = $this->mapObjectToOutput($object, $outputClass);
        }

        foreach ($this->objectNormalizers as $objectNormalizer) {
            if ($objectNormalizer->supports($object)) {
                return $objectNormalizer->normalize($object);
            }
        }

        if ($object instanceof \JsonSerializable) {
            return $object->jsonSerialize();
        } elseif ($object instanceof Collection) {
            return [];
        }

        return $this->normalizeObjectPropertiesToArray($object);
    }

    /**
     * @param class-string<OutputInterface>|null $outputClass
     */
    private function normalizeArray(array $array, ?string $outputClass = null): array
    {
        $output = [];

        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $key = u($key)->snake()->toString();
            }

            $output[$key] = $this->normalize($value, $outputClass);
        }

        if (!array_filter(array_keys($output), fn ($key) => !is_int($key))) {
            $output = array_values($output);
        }

        return $output;
    }

    private function normalizeObjectPropertiesToArray(object $object): array
    {
        $output = [];
        $objectAccessor = $this->getterSetterAccessor->createAccessor($object);

        foreach ($objectAccessor->getGetters() as $getter) {
            $key = u($getter->getName())->snake()->toString();
            $value = $getter->getValue();

            if ($getter->hasAttribute(EntityToId::class)) {
                /** @var EntityToId */
                $entityToIdAttr = $getter->getAttribute(EntityToId::class);
                $keySuffix = $entityToIdAttr->suffix;

                if (in_array('array', $getter->getTypes()) || in_array(Collection::class, $getter->getTypes())) {
                    $keySuffix ??= $entityToIdAttr->property . 's';
                    $value = array_map(
                        fn (object $valueItem) => $this->getterSetterAccessor->createAccessor($valueItem)->getValue($entityToIdAttr->property),
                        $value instanceof Collection ? $value->toArray() : $value
                    );
                } else {
                    $keySuffix ??= $entityToIdAttr->property;
                    $value = !$value ? null : $this->getterSetterAccessor->createAccessor($value)->getValue($entityToIdAttr->property);
                }

                if ($keySuffix) {
                    $key .= '_' . u($keySuffix)->snake()->toString();
                }
            }

            $output[$key] = $this->normalize($value);
        }

        return $output;
    }

    private function mapObjectToOutput(object $source, string $targetClass): OutputInterface
    {
        if (!is_a($targetClass, OutputInterface::class, true)) {
            throw new InvalidOutputClassException(
                sprintf(
                    'Can not map "%s" to "%s", allowed only instances of "%s"',
                    $source::class,
                    $targetClass,
                    OutputInterface::class
                )
            );
        }

        /** @var OutputInterface */
        $target = new $targetClass();

        $sourceAccessor = $this->getterSetterAccessor->createAccessor($source);
        $targetAccessor = $this->getterSetterAccessor->createAccessor($target);

        foreach ($targetAccessor->getSetters() as $targetSetter) {
            $name = $targetSetter->getName();

            if (!$sourceAccessor->hasGetter($name)) {
                continue;
            }

            $value = $sourceAccessor->getValue($name);

            if (is_object($value) && !$value instanceof OutputInterface) {
                foreach ($targetSetter->getTypes() as $type) {
                    if (is_a($type, OutputInterface::class, true)) {
                        $value = $this->mapObjectToOutput($value, $type);
                        break;
                    }
                }
            }

            $targetAccessor->setValue($name, $value);
        }

        foreach ($this->outputModifiers as $modifier) {
            if ($modifier->supports($target, $source)) {
                $modifier->modify($target, $source);
            }
        }

        return $target;
    }
}
