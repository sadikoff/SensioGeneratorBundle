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

use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCrudCommand;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateDoctrineCrudCommandTest extends GenerateCommandTest
{
    /**
     * @dataProvider getInteractiveCommandData
     */
    public function testInteractiveCommand($options, $input, $expected)
    {
        list($entity, $format, $prefix, $withWrite) = $expected;

        $generator = $this->getGenerator();
        $generator
            ->expects($this->once())
            ->method('generate')
            ->with($entity, $format, $prefix, $withWrite);

        $formGenerator = $this->getFormGenerator();
        if ($withWrite) {
            $formGenerator
                ->expects($this->once())
                ->method('generate')
                ->with($entity);
        }

        $tester = new CommandTester($this->getCommand($generator, $formGenerator));
        $this->setInputs($tester, $input);
        $tester->execute($options);
    }

    public function getInteractiveCommandData()
    {
        return [
            [[], "Blog/Post\n", ['Blog\\Post', 'annotation', 'blog_post', false]],
            [[], "Blog/Post\ny\nyml\nfoobar\n", ['Blog\\Post', 'yaml', 'foobar', true]],
            [[], "Blog/Post\ny\nyml\n/foobar\n", ['Blog\\Post', 'yaml', 'foobar', true]],
            [['entity' => 'Blog/Post'], "\ny\nyml\nfoobar\n", ['Blog\\Post', 'yaml', 'foobar', true]],
            [['entity' => 'Blog/Post'], '', ['Blog\\Post', 'annotation', 'blog_post', false]],
            [
                ['entity' => 'Blog/Post', '--format' => 'yml', '--route-prefix' => 'foo', '--with-write' => true],
                '',
                ['Blog\\Post', 'yaml', 'foo', true]
            ],
        ];
    }

    /**
     * @dataProvider getNonInteractiveCommandData
     */
    public function testNonInteractiveCommand($options, $expected)
    {
        list($entity, $format, $prefix, $withWrite) = $expected;

        $generator = $this->getGenerator();
        $generator
            ->expects($this->once())
            ->method('generate')
            ->with($entity, $format, $prefix, $withWrite);

        $formGenerator = $this->getFormGenerator();
        if ($withWrite) {
            $formGenerator
                ->expects($this->once())
                ->method('generate')
                ->with($entity);
        }

        $tester = new CommandTester($this->getCommand($generator, $formGenerator));
        $tester->execute($options, ['interactive' => false]);
    }

    public function getNonInteractiveCommandData()
    {
        return [
            [['entity' => 'Blog/Post'], ['Blog\\Post', 'annotation', 'blog_post', false]],
            [
                ['entity' => 'Blog/Post', '--format' => 'yml', '--route-prefix' => 'foo', '--with-write' => true],
                ['Blog\\Post', 'yaml', 'foo', true]
            ],
        ];
    }

    public function testCreateCrudWithAnnotationInNonAnnotationProject()
    {
        $rootDir = $this->getKernelRootDir();

        $routing = <<<DATA
homepage:
    path: /
    defaults: { _controller: 'App\Controller\DefaultController::index' }
DATA;

        @mkdir($rootDir.'/../config', 0777, true);
        file_put_contents($rootDir.'/../config/routes.yaml', $routing);

        $options = [];
        $input = "Blog/Post\ny\nannotation\n/foobar\n";
        $expected = ['Blog\\Post', 'annotation', 'foobar', true];

        list($entity, $format, $prefix, $withWrite) = $expected;

        $generator = $this->getGenerator();
        $generator
            ->expects($this->once())
            ->method('generate')
            ->with($entity, $format, $prefix, $withWrite);

        $formGenerator = $this->getFormGenerator();
        $formGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($entity);

        $tester = new CommandTester($this->getCommand($generator, $formGenerator));
        $this->setInputs($tester, $input);

        $tester->execute($options);

        $this->assertContains('blog_post:', file_get_contents($rootDir.'/../config/routes.yaml'));
    }

    public function testCreateCrudWithAnnotationInAnnotationProject()
    {
        $rootDir = $this->getKernelRootDir();

        $routing = <<<DATA
controllers:
    resource: "../src/Controller/"
    type:     annotation
DATA;

        @mkdir($rootDir.'/../config', 0777, true);
        file_put_contents($rootDir.'/../config/routes.yaml', $routing);

        $options = [];
        $input = "Blog/Post\ny\nyaml\n/foobar\n";
        $expected = ['Blog\\Post', 'yaml', 'foobar', true];

        list($entity, $format, $prefix, $withWrite) = $expected;

        $generator = $this->getGenerator();
        $generator
            ->expects($this->once())
            ->method('generate')
            ->with($entity, $format, $prefix, $withWrite);

        $formGenerator = $this->getFormGenerator();
        $formGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($entity);

        $tester = new CommandTester($command = $this->getCommand($generator, $formGenerator));
        $this->setInputs($tester, $input);
        $tester->execute($options);

        $this->assertEquals($routing, file_get_contents($rootDir.'/../config/routes.yaml'));
    }

    public function testAddACrudWithOneAlreadyDefined()
    {
        $rootDir = $this->getKernelRootDir();

        $routing = <<<DATA
acme_blog:
    resource: "../src/Controller/OtherController.php"
    type:     annotation
DATA;

        @mkdir($rootDir.'/../config', 0777, true);
        file_put_contents($rootDir.'/../config/routes.yaml', $routing);

        $options = [];
        $input = "Blog/Post\ny\nannotation\n/foobar\n";
        $expected = ['Blog\\Post', 'annotation', 'foobar', true];

        list($entity, $format, $prefix, $withWrite) = $expected;

        $generator = $this->getGenerator();
        $generator
            ->expects($this->once())
            ->method('generate')
            ->with($entity, $format, $prefix, $withWrite);

        $formGenerator = $this->getFormGenerator();
        $formGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($entity);

        $tester = new CommandTester($this->getCommand($generator, $formGenerator));
        $this->setInputs($tester, $input);
        $tester->execute($options);
        $expected = '../src/Controller/Blog/PostController.php';

        $this->assertContains($expected, file_get_contents($rootDir.'/../config/routes.yaml'));
    }

    protected function getCommand($generator, $formGenerator)
    {
        $command = new GenerateDoctrineCrudCommand($generator, $formGenerator);
        $command->setHelperSet($this->getHelperSet());

        return $command;
    }

    protected function getDoctrineMetadata()
    {
        return $this
            ->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadataInfo')
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function getGenerator()
    {
        // get a noop generator
        $mock = $this
            ->getMockBuilder('Sensio\Bundle\GeneratorBundle\Generator\DoctrineCrudGenerator')
            ->disableOriginalConstructor()
            ->setMethods(['generate', 'getKernelRootDir'])
            ->getMock();

        $mock->registry = $this->getRegistry();

        $mock
            ->expects($this->any())
            ->method('getKernelRootDir')
            ->will($this->returnValue($this->getKernelRootDir()));

        return $mock;
    }

    protected function getFormGenerator()
    {
        $mock = $this
            ->getMockBuilder('Sensio\Bundle\GeneratorBundle\Generator\DoctrineFormGenerator')
            ->disableOriginalConstructor()
            ->setMethods(['generate', 'getKernelRootDir'])
            ->getMock();

        $mock->registry = $this->getRegistry();

        $mock
            ->expects($this->any())
            ->method('getKernelRootDir')
            ->will($this->returnValue($this->getKernelRootDir()));

        return $mock;
    }

    protected function getRegistry()
    {
        $cache = $this->getMockBuilder('Doctrine\Common\Persistence\Mapping\Driver\MappingDriver')->getMock();
        $cache
            ->expects($this->any())
            ->method('getAllClassNames')
            ->will($this->returnValue(['App\Entity\Post']));

        $configuration = $this->getMockBuilder('Doctrine\ORM\Configuration')->getMock();
        $configuration
            ->expects($this->any())
            ->method('getMetadataDriverImpl')
            ->will($this->returnValue($cache));

        $configuration
            ->expects($this->any())
            ->method('getEntityNamespaces')
            ->will($this->returnValue(['App' => 'App\Entity']));

        $classMetaData = $this->getDoctrineMetadata();

        $manager = $this->getMockBuilder('Doctrine\ORM\EntityManagerInterface')->getMock();
        $manager
            ->expects($this->any())
            ->method('getConfiguration')
            ->will($this->returnValue($configuration));

        $manager
            ->expects($this->any())
            ->method('getClassMetadata')
            ->will($this->returnValue($classMetaData));

        $registry = $this->getMockBuilder('Symfony\Bridge\Doctrine\RegistryInterface')->getMock();
        $registry
            ->expects($this->any())
            ->method('getAliasNamespace')
            ->will($this->returnValue('App\Entity\Blog\Post'));

        $registry
            ->expects($this->any())
            ->method('getManager')
            ->will($this->returnValue($manager));

        return $registry;
    }
}
