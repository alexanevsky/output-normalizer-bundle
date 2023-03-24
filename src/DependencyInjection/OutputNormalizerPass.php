<?php

namespace Alexanevsky\OutputNormalizerBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class OutputNormalizerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder
            ->getDefinition('alexanevsky.output_normalizer.object_normalizer')
            ->setPublic(true);

        $containerBuilder
            ->getDefinition('alexanevsky.output_normalizer.output_modifier')
            ->setPublic(true);
    }
}
