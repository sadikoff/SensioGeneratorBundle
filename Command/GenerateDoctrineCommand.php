<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sensio\Bundle\GeneratorBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;

abstract class GenerateDoctrineCommand extends GeneratorCommand
{
    public function isEnabled()
    {
        return class_exists('Doctrine\\Bundle\\DoctrineBundle\\DoctrineBundle');
    }
}
