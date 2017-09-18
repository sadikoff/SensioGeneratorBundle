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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Sensio\Bundle\GeneratorBundle\Generator\DoctrineFormGenerator;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Generates a form type class for a given Doctrine entity.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Hugo Hamon <hugo.hamon@sensio.com>
 */
class GenerateDoctrineFormCommand extends GenerateDoctrineCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('doctrine:generate:form')
            ->setAliases(array('generate:doctrine:form'))
            ->setDescription('Generates a form type class based on a Doctrine entity')
            ->setDefinition(array(
                new InputArgument('entity', InputArgument::REQUIRED, 'The entity class name to initialize (shortcut notation)'),
            ))
            ->setHelp(<<<EOT
The <info>%command.name%</info> command generates a form class based on a Doctrine entity.

<info>php %command.full_name% Post</info>

Every generated file is based on a template. There are default templates but they can be overridden by placing custom templates in one of the following locations, by order of priority:

<info>APP_PATH/Resources/SensioGeneratorBundle/skeleton/form</info>

You can check https://github.com/sensio/SensioGeneratorBundle/tree/master/Resources/skeleton
in order to know the file structure of the skeleton
EOT
            )
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $entity = Validators::validateEntityName($input->getArgument('entity'));

        /** @var KernelInterface $kernel */
        $kernel = $this->getContainer()->get('kernel');
        $rc = new \ReflectionClass($kernel);

        $doctrine = $this->getContainer()->get('doctrine');

        $entity = str_replace('/', '\\', $entity);

        $entityClass = $doctrine->getAliasNamespace($rc->getNamespaceName()).'\\'.$entity;
        $metadata = $doctrine->getManager()->getClassMetadata($entityClass);
        /** @var DoctrineFormGenerator $generator */
        $generator = $this->getGenerator($kernel);

        $generator->generate($kernel, $entity, $metadata);

        $output->writeln(sprintf(
            'The new %s.php class file has been created under %s.',
            $generator->getClassName(),
            $generator->getClassPath()
        ));
    }

    protected function createGenerator()
    {
        return new DoctrineFormGenerator();
    }
}
