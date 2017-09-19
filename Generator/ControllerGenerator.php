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
                $template = $controller.'\\'.
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

        $controllerName = strtolower(str_replace('\\', '_',$controller));

        $file = $kernel->getRootDir().'/../config/routes/'.$controllerName.'.'.$format;
        if (file_exists($file)) {
            $content = file_get_contents($file);
        } elseif (!is_dir($dir = $kernel->getRootDir().'/../config/routes')) {
            self::mkdir($dir);
        }

        $controller = NamespaceExtractor::from($kernel).'\\Controller\\'.$controller.':'.$action['name'];
        $name = $controllerName.'_'.strtolower(preg_replace('/([A-Z])/', '_\\1', $action['basename']));

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
        } elseif ('xml' == $format) {
            // xml
            if (!isset($content)) {
                // new file
                $content = <<<EOT
<?xml version="1.0" encoding="UTF-8" ?>
<routes xmlns="http://symfony.com/schema/routing"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/routing http://symfony.com/schema/routing/routing-1.0.xsd">
</routes>
EOT;
            }
            $sxe = simplexml_load_string($content);
            $route = $sxe->addChild('route');
            $route->addAttribute('id', $name);
            $route->addAttribute('path', $action['route']);
            $default = $route->addChild('default', $controller);
            $default->addAttribute('key', '_controller');
            $dom = new \DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($sxe->asXML());
            $content = $dom->saveXML();
        } elseif ('php' == $format) {
            // php
            if (isset($content)) {
                // edit current file
                $pointer = strpos($content, 'return');
                if (!preg_match('/(\$[^ ]*).*?new RouteCollection\(\)/', $content, $collection) || false === $pointer) {
                    throw new \RuntimeException('Routing.php file is not correct, please initialize RouteCollection.');
                }
                $content = substr($content, 0, $pointer);
                $content .= sprintf("%s->add('%s', new Route('%s', array(", $collection[1], $name, $action['route']);
                $content .= sprintf("\n    '_controller' => '%s',", $controller);
                $content .= "\n)));\n\nreturn ".$collection[1].';';
            } else {
                // new file
                $content = <<<EOT
<?php
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
\$collection = new RouteCollection();
EOT;
                $content .= sprintf("\n\$collection->add('%s', new Route('%s', array(", $name, $action['route']);
                $content .= sprintf("\n    '_controller' => '%s',", $controller);
                $content .= "\n)));\n\nreturn \$collection;";
            }
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
        $data = [];

        if (1 <= substr_count($logicalName, '\\')) {
            $t = explode('\\', $logicalName);

            $data['template'] = array_pop($t);
            $data['controller'] = implode('\\', $t);
        } else {
            throw new \RuntimeException(sprintf('The given template name ("%s") is not correct (it must contain at least one backslash).', $logicalName));
        }

        return $part ? $data[$part] : $data;
    }
}
