<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sensio\Bundle\GeneratorBundle\Tests\Command;

use Sensio\Bundle\GeneratorBundle\Tests\TestCase;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Filesystem\Filesystem;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Symfony\Component\DependencyInjection\Container;

abstract class GenerateCommandTest extends TestCase
{
    protected $kernel;

    protected function tearDown()
    {
        if (null !== $this->kernel) {
            $fs = new Filesystem();
            $fs->remove(dirname($this->kernel->getRootDir()));
        }
    }

    protected function getHelperSet()
    {
        return new HelperSet(array(new FormatterHelper(), new QuestionHelper()));
    }

    protected function setInputs($tester, $command, $input)
    {
        $input .= str_repeat("\n", 10);
        if (method_exists($tester, 'setInputs')) {
            $tester->setInputs(explode("\n", $input));
        } else {
            $stream = fopen('php://memory', 'r+', false);
            fwrite($stream, $input);
            rewind($stream);

            $command->getHelperSet()->get('question')->setInputStream($stream);
        }
    }

    protected function getKernel()
    {
        if (null !== $this->kernel) {
            return $this->kernel;
        }

        $tmpDir = sys_get_temp_dir().'/sf'.mt_rand(111111, 999999).'/src';
        @mkdir($tmpDir, 0777, true);

        $this->kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\KernelInterface')->getMock();
        $this->kernel
            ->expects($this->any())
            ->method('getRootDir')
            ->will($this->returnValue($tmpDir))
        ;

        return $this->kernel;
    }

    protected function getContainer()
    {
        $kernel = $this->getKernel();

        $filesystem = $this->getMockBuilder('Symfony\Component\Filesystem\Filesystem')->getMock();
        $filesystem
            ->expects($this->any())
            ->method('isAbsolutePath')
            ->will($this->returnValue(true))
        ;

        $container = new Container();
        $container->set('kernel', $kernel);
        $container->set('filesystem', $filesystem);

        $container->setParameter('kernel.root_dir', $kernel->getRootDir());

        return $container;
    }
}
