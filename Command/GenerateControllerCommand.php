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

use Sensio\Bundle\GeneratorBundle\Manipulator\RoutingManipulator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Sensio\Bundle\GeneratorBundle\Generator\ControllerGenerator;

/**
 * Generates controllers.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
class GenerateControllerCommand extends GeneratorCommand
{
    protected static $defaultName = 'generate:controller';

    /**
     * @see Command
     */
    public function configure()
    {
        $this
            ->setDescription('Generates a controller')
            ->setDefinition(
                [
                    new InputOption(
                        'controller',
                        '',
                        InputOption::VALUE_REQUIRED,
                        'The name of the controller to create'
                    ),
                    new InputOption(
                        'route-format',
                        '',
                        InputOption::VALUE_REQUIRED,
                        'The format that is used for the routing (yaml, xml, php, annotation)',
                        'annotation'
                    ),
                    new InputOption(
                        'template-format',
                        '',
                        InputOption::VALUE_REQUIRED,
                        'The format that is used for templating (twig, php)',
                        'twig'
                    ),
                    new InputOption(
                        'actions',
                        '',
                        InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                        'The actions in the controller'
                    ),
                ]
            )
            ->setHelp(
                <<<EOT
The <info>%command.name%</info> command helps you generates new controllers
inside bundles.

By default, the command interacts with the developer to tweak the generation.
Any passed option will be used as a default value for the interaction
(<comment>--controller</comment> is the only one needed if you follow the conventions):

<info>php %command.full_name% --controller=Post</info>

If you want to disable any user interaction, use <comment>--no-interaction</comment>
but don't forget to pass all needed options:

<info>php %command.full_name% --controller=Post --no-interaction</info>

Every generated file is based on a template. There are default templates but they can
be overridden by placing custom templates in one of the following locations, by order of priority:

<info>APP_PATH/Resources/SensioGeneratorBundle/skeleton/controller</info>

You can check https://github.com/sensio/SensioGeneratorBundle/tree/master/Resources/skeleton
in order to know the file structure of the skeleton
EOT
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
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

        if (null === $input->getOption('controller')) {
            throw new \RuntimeException('The controller option must be provided.');
        }

        $controller = $input->getOption('controller');

        $questionHelper->writeSection($output, 'Controller generation');

        $routingFormat = $input->getOption('route-format');
        /** @var ControllerGenerator $generator */
        $generator = $this->getGenerator();
        $generator->generate(
            $controller,
            $routingFormat,
            $input->getOption('template-format'),
            $this->parseActions($input->getOption('actions'))
        );

        if ('annotations' === $routingFormat) {
            $this->tryUpdateAnnotationRouting($controller);
        }

        $output->writeln('Generating the code: <info>OK</info>');

        $questionHelper->writeGeneratorSummary($output, []);
    }

    public function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Welcome to the Symfony controller generator');

        // namespace
        $output->writeln(
            [
                '',
                'Every page, and even sections of a page, are rendered by a <comment>controller</comment>.',
                'This command helps you generate them easily.',
                '',
                'First, you need to give the controller name you want to generate.',
                'You must use the shortcut notation like <comment>Post</comment>',
                '',
            ]
        );

        while (true) {
            $question = new Question(
                $questionHelper->getQuestion('Controller name', $input->getOption('controller')),
                $input->getOption('controller')
            );
            $question->setValidator(['Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateControllerName']);
            $controller = $questionHelper->ask($input, $output, $question);

            $controller = str_replace('/', '\\', $controller);

            if (!file_exists($this->getGenerator()->getKernelRootDir().'/Controller/'.$controller.'Controller.php')) {
                break;
            }

            $output->writeln(sprintf('<bg=red>Controller "%s" already exists.</>', $controller));
        }
        $input->setOption('controller', $controller);

        // routing format
        $defaultFormat = (null !== $input->getOption('route-format') ? $input->getOption('route-format') : 'annotation');
        $output->writeln(
            [
                '',
                'Determine the format to use for the routing.',
                '',
            ]
        );
        $question = new Question(
            $questionHelper->getQuestion('Routing format (yaml, xml, php, annotation)', $defaultFormat), $defaultFormat
        );
        $question->setValidator(['Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateFormat']);
        $routeFormat = $questionHelper->ask($input, $output, $question);
        $input->setOption('route-format', $routeFormat);

        // templating format
        $validateTemplateFormat = function ($format) {
            if (!in_array($format, ['twig', 'php'])) {
                throw new \InvalidArgumentException(
                    sprintf('The template format must be twig or php, "%s" given', $format)
                );
            }

            return $format;
        };

        $defaultFormat = (null !== $input->getOption('template-format') ? $input->getOption(
            'template-format'
        ) : 'twig');
        $output->writeln(
            [
                '',
                'Determine the format to use for templating.',
                '',
            ]
        );
        $question = new Question(
            $questionHelper->getQuestion('Template format (twig, php)', $defaultFormat),
            $defaultFormat
        );
        $question->setValidator($validateTemplateFormat);

        $templateFormat = $questionHelper->ask($input, $output, $question);
        $input->setOption('template-format', $templateFormat);

        // actions
        $input->setOption('actions', $this->addActions($input, $output, $questionHelper));

        // summary
        $output->writeln(
            [
                '',
                $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg-white', true),
                '',
                sprintf('You are going to generate a "<info>%s</info>" controller', $controller),
                sprintf(
                    'using the "<info>%s</info>" format for the routing and the "<info>%s</info>" format',
                    $routeFormat,
                    $templateFormat
                ),
                'for templating',
            ]
        );
    }

    public function addActions(InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper)
    {
        $output->writeln(
            [
                '',
                'Instead of starting with a blank controller, you can add some actions now. An action',
                'is a PHP function or method that executes, for example, when a given route is matched.',
                'Actions should be suffixed by <comment>Action</comment>.',
                '',
            ]
        );

        $actions = $this->parseActions($input->getOption('actions'));

        $controller = $input->getOption('controller');
        $controllerParts = explode('\\', $controller);
        array_pop($controllerParts);
        $routeNamespacePrefix = count($controllerParts) ? strtolower('/'.implode('/', $controllerParts)) : '';

        while (true) {
            // name
            $output->writeln('');
            $question = new Question(
                $questionHelper->getQuestion('New action name (press <return> to stop adding actions)', null), null
            );
            $question->setValidator(
                function ($name) use ($actions) {
                    if (null === $name) {
                        return $name;
                    }

                    if (isset($actions[$name])) {
                        throw new \InvalidArgumentException(sprintf('Action "%s" is already defined', $name));
                    }

                    if ('Action' != substr($name, -6)) {
                        throw new \InvalidArgumentException(sprintf('Name "%s" is not suffixed by Action', $name));
                    }

                    return $name;
                }
            );

            $actionName = $questionHelper->ask($input, $output, $question);
            if (!$actionName) {
                break;
            }

            // route
            $defaultRoute = $routeNamespacePrefix.'/'.substr($actionName, 0, -6);
            $question = new Question($questionHelper->getQuestion('Action route', $defaultRoute), $defaultRoute);
            $route = $questionHelper->ask($input, $output, $question);
            $placeholders = $this->getPlaceholdersFromRoute($route);

            // template
            $defaultTemplate = strtolower($input->getOption('controller')).'\\'.
                strtolower(
                    preg_replace(
                        ['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'],
                        ['\\1_\\2', '\\1_\\2'],
                        strtr(substr($actionName, 0, -6), '_', '.')
                    )
                )
                .'.html.'.$input->getOption('template-format');
            $question = new Question(
                $questionHelper->getQuestion('Template name (optional)', $defaultTemplate),
                $defaultTemplate
            );
            $template = $questionHelper->ask($input, $output, $question);

            // adding action
            $actions[$actionName] = [
                'name'         => $actionName,
                'route'        => $route,
                'placeholders' => $placeholders,
                'template'     => $template,
            ];
        }

        return $actions;
    }

    public function parseActions($actions)
    {
        if (empty($actions) || $actions !== array_values($actions)) {
            return $actions;
        }

        // '$actions' can be an array with just 1 element defining several actions
        // separated by white spaces: $actions = array('... ... ...');
        if (1 === count($actions)) {
            $actions = explode(' ', $actions[0]);
        }

        $parsedActions = [];

        foreach ($actions as $action) {
            $data = explode(':', $action);

            // name
            if (!isset($data[0])) {
                throw new \InvalidArgumentException('An action must have a name');
            }
            $name = array_shift($data);

            // route
            $route = (isset($data[0]) && '' != $data[0]) ? array_shift($data) : '/'.substr($name, 0, -6);
            if ($route) {
                $placeholders = $this->getPlaceholdersFromRoute($route);
            } else {
                $placeholders = [];
            }

            // template
            $template = (0 < count($data) && '' != $data[0]) ? implode('\\', $data) : 'default';

            $parsedActions[$name] = [
                'name'         => $name,
                'route'        => $route,
                'placeholders' => $placeholders,
                'template'     => $template,
            ];
        }

        return $parsedActions;
    }

    public function getPlaceholdersFromRoute($route)
    {
        preg_match_all('/{(.*?)}/', $route, $placeholders);
        $placeholders = $placeholders[1];

        return $placeholders;
    }

    private function tryUpdateAnnotationRouting($controller)
    {
        $routing = new RoutingManipulator(dirname($this->getGenerator()->getKernelRootDir()).'/config/routes.yaml');

        if ($routing->hasResourceInAnnotation()) {
            return;
        }

        $routing->addAnnotationController($controller);
    }
}
