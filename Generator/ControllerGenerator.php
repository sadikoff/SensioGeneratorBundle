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

use Sensio\Bundle\GeneratorBundle\Extractor\NamespaceExtractor;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Generates a Controller inside a bundle.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
class ControllerGenerator extends Generator
{

    public function generate(KernelInterface $kernel, $controller, $routeFormat, $templateFormat, array $actions = array())
    {
        $dir = $kernel->getRootDir();

        $controller = str_replace('\\', '/', $controller);
        $controllerParts = explode('/', $controller);

        $controllerName = array_pop($controllerParts);

        $controllerFile = $dir.'/Controller/'.$controller.'Controller.php';
        if (file_exists($controllerFile)) {
            throw new \RuntimeException(sprintf('Controller "%s" already exists', $controller));
        }

        $parameters = array(
            'namespace' => NamespaceExtractor::from($kernel),
            'sub_namespace' => false,
            'format' => array(
                'routing' => $routeFormat,
            ),
            'controller' => $controllerName,
        );

        if (count($controllerParts)) {
            $parameters = array_merge($parameters, array('sub_namespace' => implode('\\', $controllerParts)));
        }

        foreach ($actions as $i => $action) {
            // get the action name without the suffix Action (for the template logical name)
            $actions[$i]['basename'] = substr($action['name'], 0, -6);
            $params = $parameters;
            $params['action'] = $actions[$i];

            // create a template
            $template = $actions[$i]['template'];
            if ('default' == $template) {
                @trigger_error('The use of the "default" keyword is deprecated. Use the real template name instead.', E_USER_DEPRECATED);
                $template = $controller.':'.
                    strtolower(preg_replace(array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('\\1_\\2', '\\1_\\2'), strtr(substr($action['name'], 0, -6), '_', '.')))
                    .'.html.'.$templateFormat;
            }

            if ('twig' == $templateFormat) {
                $this->renderFile('controller/Template.html.twig.twig', $dir.'/../templates/'.$this->parseTemplatePath($template), $params);
            } else {
                $this->renderFile('controller/Template.html.php.twig', $dir.'/../templates/'.$this->parseTemplatePath($template), $params);
            }

            $this->generateRouting($kernel, $controller, $actions[$i], $routeFormat);
        }

        $parameters['actions'] = $actions;

        $this->renderFile('controller/Controller.php.twig', $controllerFile, $parameters);
        $this->renderFile('controller/ControllerTest.php.twig', $dir.'/Tests/Controller/'.$controller.'ControllerTest.php', $parameters);
    }

    public function generateRouting(KernelInterface $kernel, $controller, array $action, $format)
    {
        // annotation is generated in the templates
        if ('annotation' == $format) {
            return true;
        }

        $file = $kernel->getRootDir().'/../config/routes.'.$format;
        if (file_exists($file)) {
            $content = file_get_contents($file);
        } elseif (!is_dir($dir = $kernel->getRootDir().'/../config')) {
            self::mkdir($dir);
        }

        $controller = $controller.':'.$action['basename'];
        $name = strtolower(preg_replace('/([A-Z])/', '_\\1', $action['basename']));

        if ('yaml' == $format) {
            // yaml
            if (!isset($content)) {
                $content = '';
            }

            $content .= sprintf(
                "\n%s:\n    path:     %s\n    defaults: { _controller: %s }\n",
                $name,
                $action['route'],
                $controller
            );
        }

        $flink = fopen($file, 'w');
        if ($flink) {
            $write = fwrite($flink, $content);

            if ($write) {
                fclose($flink);
            } else {
                throw new \RuntimeException(sprintf('We cannot write into file "%s", has that file the correct access level?', $file));
            }
        } else {
            throw new \RuntimeException(sprintf('Problems with generating file "%s", did you gave write access to that directory?', $file));
        }
    }

    protected function parseTemplatePath($template)
    {
        $data = $this->parseLogicalTemplateName($template);

        return $data['controller'].'/'.$data['template'];
    }

    protected function parseLogicalTemplateName($logicalName, $part = '')
    {
        if (1 !== substr_count($logicalName, ':')) {
            throw new \RuntimeException(sprintf('The given template name ("%s") is not correct (it must contain one colon).', $logicalName));
        }

        $data = array();

        list($data['controller'], $data['template']) = explode(':', $logicalName);

        return $part ? $data[$part] : $data;
    }
}
