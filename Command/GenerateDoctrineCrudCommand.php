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

use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Sensio\Bundle\GeneratorBundle\Generator\DoctrineCrudGenerator;
use Sensio\Bundle\GeneratorBundle\Generator\DoctrineFormGenerator;
use Sensio\Bundle\GeneratorBundle\Manipulator\RoutingManipulator;

/**
 * Generates a CRUD for a Doctrine entity.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class GenerateDoctrineCrudCommand extends GenerateDoctrineCommand
{
    private $formGenerator;

    protected static $defaultName = 'doctrine:generate:crud';

    public function __construct(Generator $generator, DoctrineFormGenerator $formGenerator)
    {
        parent::__construct($generator);

        $this->formGenerator = $formGenerator;
    }

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setAliases(['generate:doctrine:crud'])
            ->setDescription('Generates a CRUD based on a Doctrine entity')
            ->addArgument('entity', InputArgument::OPTIONAL, 'The entity class name to initialize (shortcut notation)')
            ->addOption('route-prefix', null, InputOption::VALUE_REQUIRED, 'The route prefix')
            ->addOption(
                'with-write',
                null,
                InputOption::VALUE_NONE,
                'Whether or not to generate create, new and delete actions'
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_REQUIRED,
                'The format used for configuration files (php, xml, yml, or annotation)',
                'annotation'
            )
            ->addOption(
                'overwrite',
                null,
                InputOption::VALUE_NONE,
                'Overwrite any existing controller or form class when generating the CRUD contents'
            )
            ->setHelp(
                <<<EOT
The <info>%command.name%</info> command generates a CRUD based on a Doctrine entity.

The default command only generates the list and show actions.

<info>php %command.full_name% Post --route-prefix=post_admin</info>

Using the --with-write option allows to generate the new, edit and delete actions.

<info>php %command.full_name% Post --route-prefix=post_admin --with-write</info>

Every generated file is based on a template. There are default templates but they can be overridden by placing custom templates in one of the following locations, by order of priority:

<info>APP_PATH/Resources/SensioGeneratorBundle/skeleton/crud</info>

You can check https://github.com/sensio/SensioGeneratorBundle/tree/master/Resources/skeleton
in order to know the file structure of the skeleton
EOT
            );
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        if ($input->isInteractive()) {
            $question = new ConfirmationQuestion(
                $questionHelper->getQuestion('Do you confirm generation', 'yes', '?'),
                true
            );
            if (!$questionHelper->ask($input, $output, $question)) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }

        $entity = Validators::validateEntityName($input->getArgument('entity'));
        $entity = str_replace('/', '\\', $entity);

        $format = Validators::validateFormat($input->getOption('format'));
        $prefix = $this->getRoutePrefix($input, $entity);
        $withWrite = $input->getOption('with-write');
        $forceOverwrite = $input->getOption('overwrite');

        $questionHelper->writeSection($output, 'CRUD generation');

        /** @var DoctrineCrudGenerator $generator */
        $generator = $this->getGenerator();
        $generator->generate($entity, $format, $prefix, $withWrite, $forceOverwrite);

        $output->writeln('Generating the CRUD code: <info>OK</info>');

        $errors = [];
        $runner = $questionHelper->getRunner($output, $errors);

        // form
        if ($withWrite) {
            $this->generateForm($entity, $forceOverwrite);
            $output->writeln('Generating the Form code: <info>OK</info>');
        }

        // routing
        $output->write('Updating the routing: ');
        if ('annotation' == $format) {
            $runner($this->updateAnnotationRouting($entity));
        }

        $questionHelper->writeGeneratorSummary($output, $errors);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Welcome to the Doctrine2 CRUD generator');

        // namespace
        $output->writeln(
            [
                '',
                'This command helps you generate CRUD controllers and templates.',
                '',
                'First, give the name of the existing entity for which you want to generate a CRUD',
                '(use the shortcut notation like <comment>Post</comment>)',
                '',
            ]
        );

        $question = new Question(
            $questionHelper->getQuestion('The Entity shortcut name', $input->getArgument('entity')),
            $input->getArgument('entity')
        );
        $question->setValidator(['Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName']);

        //$autocompleter = new EntitiesAutoCompleter($this->getContainer()->get('doctrine')->getManager());
        //$autocompleteEntities = $autocompleter->getSuggestions();
        //$question->setAutocompleterValues($autocompleteEntities);
        $entity = $questionHelper->ask($input, $output, $question);

        $input->setArgument('entity', $entity);
        $entity = str_replace('/', '\\', $entity);

        // write?
        $withWrite = $input->getOption('with-write') ?: false;
        $output->writeln(
            [
                '',
                'By default, the generator creates two actions: list and show.',
                'You can also ask it to generate "write" actions: new, update, and delete.',
                '',
            ]
        );
        $question = new ConfirmationQuestion(
            $questionHelper->getQuestion('Do you want to generate the "write" actions', $withWrite ? 'yes' : 'no', '?'),
            $withWrite
        );

        $withWrite = $questionHelper->ask($input, $output, $question);
        $input->setOption('with-write', $withWrite);

        // format
        $format = $input->getOption('format');
        $output->writeln(
            [
                '',
                'Determine the format to use for the generated CRUD.',
                '',
            ]
        );
        $question = new Question(
            $questionHelper->getQuestion('Configuration format (yml, xml, php, or annotation)', $format), $format
        );
        $question->setValidator(['Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateFormat']);
        $format = $questionHelper->ask($input, $output, $question);
        $input->setOption('format', $format);

        // route prefix
        $prefix = $this->getRoutePrefix($input, $entity);
        $output->writeln(
            [
                '',
                'Determine the routes prefix (all the routes will be "mounted" under this',
                'prefix: /prefix/, /prefix/new, ...).',
                '',
            ]
        );
        $prefix = $questionHelper->ask(
            $input,
            $output,
            new Question($questionHelper->getQuestion('Routes prefix', '/'.$prefix), '/'.$prefix)
        );
        $input->setOption('route-prefix', $prefix);

        // summary
        $output->writeln(
            [
                '',
                $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg=white', true),
                '',
                sprintf('You are going to generate a CRUD controller for "<info>%s</info>"', $entity),
                sprintf('using the "<info>%s</info>" format.', $format),
                '',
            ]
        );
    }

    /**
     * Tries to generate forms if they don't exist yet and if we need write operations on entities.
     *
     * @param string $entity
     * @param bool $forceOverwrite
     */
    protected function generateForm($entity, $forceOverwrite = false)
    {
        $this->getFormGenerator()->generate($entity, $forceOverwrite);
    }

    protected function updateAnnotationRouting($entity)
    {
        $projectDir = dirname($this->getGenerator()->getKernelRootDir());

        $routing = new RoutingManipulator($projectDir.'/config/routes.yaml');

        if (!$routing->hasResourceInAnnotation()) {
            $routing->addAnnotationController($entity);
        }
    }

    protected function getRoutePrefix(InputInterface $input, $entity)
    {
        $prefix = $input->getOption('route-prefix') ?: strtolower(str_replace(['\\', '/'], '_', $entity));

        if ($prefix && '/' === $prefix[0]) {
            $prefix = substr($prefix, 1);
        }

        return $prefix;
    }

    protected function getFormGenerator()
    {
        return $this->formGenerator;
    }
}
