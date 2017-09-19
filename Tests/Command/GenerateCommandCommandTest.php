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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Sensio\Bundle\GeneratorBundle\Command\GenerateCommandCommand;

class GenerateCommandCommandTest extends GenerateCommandTest
{
    protected $generator;

    /**
     * @dataProvider getInteractiveCommandData
     */
    public function testInteractiveCommand($options, $input, $expected)
    {
        $generator = $this->getGenerator();
        $generator
            ->expects($this->once())
            ->method('generate')
            ->with($this->getKernel(), $expected)
        ;

        $tester = new CommandTester($command = $this->getCommand($generator));
        $this->setInputs($tester, $command, $input);
        $tester->execute($options);
    }

    public function getInteractiveCommandData()
    {
        return array(
            array(
                array(),
                "app:foo-bar\n",
                'app:foo-bar',
            ),

            array(
                array('name' => 'app:foo-bar'),
                '',
                'app:foo-bar',
            ),
        );
    }

    /**
     * @dataProvider getNonInteractiveCommandData
     */
    public function testNonInteractiveCommand($options, $expected)
    {
        $generator = $this->getGenerator();
        $generator
            ->expects($this->once())
            ->method('generate')
            ->with($this->getKernel(), $expected)
        ;

        $tester = new CommandTester($command = $this->getCommand($generator));
        $tester->execute($options, array('interactive' => false));
    }

    public function getNonInteractiveCommandData()
    {
        return array(
            array(
                array('name' => 'app:my-command'),
                'app:my-command',
            ),
        );
    }

    protected function getCommand($generator)
    {
        $command = new GenerateCommandCommand();

        $command->setContainer($this->getContainer());
        $command->setHelperSet($this->getHelperSet());
        $command->setGenerator($generator);

        return $command;
    }

    protected function getApplication($input = '')
    {
        $application = new Application();

        $command = new GenerateCommandCommand();
        $command->setContainer($this->getContainer());
        $command->setHelperSet($this->getHelperSet($input));
        $command->setGenerator($this->getGenerator());

        $application->add($command);

        return $application;
    }

    protected function getGenerator()
    {
        if (null === $this->generator) {
            $this->setGenerator();
        }

        return $this->generator;
    }

    protected function setGenerator()
    {
        // get a noop generator
        $this->generator = $this
            ->getMockBuilder('Sensio\Bundle\GeneratorBundle\Generator\CommandGenerator')
            ->disableOriginalConstructor()
            ->setMethods(array('generate'))
            ->getMock()
        ;
    }
}
