<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sensio\Bundle\GeneratorBundle\Generator;

/**
 * Generates a Command inside a bundle.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class CommandGenerator extends Generator
{
    public function generate($name)
    {
        $commandDir = $this->getKernelRootDir().'/Command';
        self::mkdir($commandDir);

        $commandClassName = $this->classify($name).'Command';
        $commandFile = $commandDir.'/'.$commandClassName.'.php';
        if ($this->getFilesystem()->exists($commandFile)) {
            throw new \RuntimeException(sprintf('Command "%s" already exists', $name));
        }

        $parameters = [
            'class_name' => $commandClassName,
            'name'       => $name,
        ];

        $this->renderFile('command/Command.php.twig', $commandFile, $parameters);
    }

    /**
     * Transforms the given string to a new string valid as a PHP class name
     * ('app:my-project' -> 'AppMyProject', 'app:namespace:name' -> 'AppNamespaceName').
     *
     * @param string $string
     *
     * @return string The string transformed to be a valid PHP class name
     */
    public function classify($string)
    {
        return str_replace(' ', '', ucwords(strtr($string, '_-:', '   ')));
    }
}
