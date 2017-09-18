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
use Sensio\Bundle\GeneratorBundle\Command\GenerateControllerCommand;

class GenerateControllerCommandTest extends GenerateCommandTest
{
    protected $generator;

    /**
     * @dataProvider getInteractiveCommandData
     */
    public function testInteractiveCommand($options, $input, $expected)
    {
        list($controller, $routeFormat, $templateFormat, $actions) = $expected;

        $generator = $this->getGenerator();
        $generator
            ->expects($this->once())
            ->method('generate')
            ->with($this->getKernel(), $controller, $routeFormat, $templateFormat, $actions)
        ;

        $tester = new CommandTester($command = $this->getCommand($generator));
        $this->setInputs($tester, $command, $input);
        $tester->execute($options);
    }

    public function getInteractiveCommandData()
    {
        return array(
            array(array(), "Post\n", array('Post', 'annotation', 'twig', array())),
            array(array('--controller' => 'Post'), '', array('Post', 'annotation', 'twig', array())),

            array(array(), "Post\nyaml\nphp\n", array('Post', 'yaml', 'php', array())),

            array(array(), "Post\nyaml\nphp\nshowAction\n\n\ngetListAction\n/_getlist/{max}\nLists:post.html.php\n", array('Post', 'yaml', 'php', array(
                'showAction' => array(
                    'name' => 'showAction',
                    'route' => '/show',
                    'placeholders' => array(),
                    'template' => 'Post:show.html.php',
                ),
                'getListAction' => array(
                    'name' => 'getListAction',
                    'route' => '/_getlist/{max}',
                    'placeholders' => array('max'),
                    'template' => 'Lists:post.html.php',
                ),
            ))),

            array(array('--route-format' => 'yaml', '--template-format' => 'php', '--actions' => array('showAction:/{slug}:article.html.php')), 'Post', array('Post', 'yaml', 'php', array(
                'showAction' => array(
                    'name' => 'showAction',
                    'route' => '/{slug}',
                    'placeholders' => array('slug'),
                    'template' => 'article.html.php',
                ),
            ))),
        );
    }

    /**
     * @dataProvider getNonInteractiveCommandData
     */
    public function testNonInteractiveCommand($options, $expected)
    {
        list($controller, $routeFormat, $templateFormat, $actions) = $expected;

        $generator = $this->getGenerator();
        $generator
            ->expects($this->once())
            ->method('generate')
            ->with($this->getKernel(), $controller, $routeFormat, $templateFormat, $actions)
        ;

        $tester = new CommandTester($command = $this->getCommand($generator));
        $tester->execute($options, array('interactive' => false));
    }

    public function getNonInteractiveCommandData()
    {
        return array(
            array(array('--controller' => 'Post'), array('Post', 'annotation', 'twig', array())),
            array(array('--controller' => 'Post', '--route-format' => 'yaml', '--template-format' => 'php'), array('Post', 'yaml', 'php', array())),
            array(array('--controller' => 'Post', '--actions' => array('showAction getListAction:/_getlist/{max}:List:post.html.twig createAction:/admin/create')), array('Post', 'annotation', 'twig', array(
                'showAction' => array(
                    'name' => 'showAction',
                    'route' => '/show',
                    'placeholders' => array(),
                    'template' => 'default',
                ),
                'getListAction' => array(
                    'name' => 'getListAction',
                    'route' => '/_getlist/{max}',
                    'placeholders' => array('max'),
                    'template' => 'List:post.html.twig',
                ),
                'createAction' => array(
                    'name' => 'createAction',
                    'route' => '/admin/create',
                    'placeholders' => array(),
                    'template' => 'default',
                ),
            ))),
            array(array('--controller' => 'Post', '--route-format' => 'yaml', '--template-format' => 'php', '--actions' => array('showAction::')), array('Post', 'yaml', 'php', array(
                'showAction' => array(
                    'name' => 'showAction',
                    'route' => '/show',
                    'placeholders' => array(),
                    'template' => 'default',
                ),
            ))),
        );
    }

    protected function getCommand($generator)
    {
        $command = $this
            ->getMockBuilder('Sensio\Bundle\GeneratorBundle\Command\GenerateControllerCommand')
            ->setMethods(array('generateRouting'))
            ->getMock()
        ;

        $command->setContainer($this->getContainer());
        $command->setHelperSet($this->getHelperSet());
        $command->setGenerator($generator);

        return $command;
    }

    protected function getApplication($input = '')
    {
        $application = new Application();

        $command = new GenerateControllerCommand();
        $command->setContainer($this->getContainer());
        $command->setHelperSet($this->getHelperSet($input));
        $command->setGenerator($this->getGenerator());

        $application->add($command);

        return $application;
    }

    protected function getGenerator()
    {
        if (null == $this->generator) {
            $this->setGenerator();
        }

        return $this->generator;
    }

    protected function setGenerator()
    {
        // get a noop generator
        $this->generator = $this
            ->getMockBuilder('Sensio\Bundle\GeneratorBundle\Generator\ControllerGenerator')
            ->disableOriginalConstructor()
            ->setMethods(array('generate'))
            ->getMock()
        ;
    }
}
