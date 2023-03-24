<?php

namespace Alexanevsky\OutputNormalizerBundle\ObjectNormalizer;

class DateTimeNormalizer implements ObjectNormalizerInterface
{
    public function supports(object $object): bool
    {
        return $object instanceof \DateTimeInterface;
    }

    /**
     * @param \DateTimeInterface $object
     */
    public function normalize(object $object): string
    {
        return $object->format('c');
    }
}
