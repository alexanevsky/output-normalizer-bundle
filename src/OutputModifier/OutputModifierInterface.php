<?php

namespace Alexanevsky\OutputNormalizerBundle\OutputModifier;

use Alexanevsky\OutputNormalizerBundle\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('alexanevsky.output_normalizer.output_modifier')]
interface OutputModifierInterface
{
    public function supports(object $output, object $source): bool;

    public function modify(OutputInterface $output, object $source): void;
}
