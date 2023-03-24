<?php

namespace Alexanevsky\OutputNormalizerBundle\Output\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class EntityToId
{
    /**
     * @param string            $property   Property name of entity identifier.
     *                                      The normalizer will try to get this property during normalizing.
     * @param string|false|null $suffix     Suffix to append to default property name.
     *                                      For example, property "user" will be normalized to "userId".
     *                                      If given value is string, it will be appended.
     *                                      If given value is null, the identifier property name will be appended ("s" will append if value is array or collection).
     *                                      If given value is false, nothing will be appended.
     */
    public function __construct(
        public string $property = 'id',
        public string|false|null $suffix = null
    ) {
    }
}
