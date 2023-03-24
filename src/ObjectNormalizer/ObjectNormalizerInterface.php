<?php

namespace Alexanevsky\OutputNormalizerBundle\ObjectNormalizer;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('alexanevsky.output_normalizer.object_normalizer')]
interface ObjectNormalizerInterface
{
    public function supports(object $object): bool;

    /**
     * @return mixed
     */
    public function normalize(object $object);
}
