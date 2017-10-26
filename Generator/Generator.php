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

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Generator is the base class for all generators.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Generator
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $kernelRootDir;

    /**
     * @var array
     */
    private $skeletonDirs;

    /**
     * @var ConsoleOutput
     */
    private static $output;

    public function __construct(Filesystem $filesystem, $kernelRootDir)
    {
        $this->filesystem = $filesystem;
        $this->kernelRootDir = $kernelRootDir;

        $this->registerSkeletonDirs();
    }

    /**
     * @return Filesystem
     */
    public function getFilesystem()
    {
        return $this->filesystem;
    }

    public function getKernelRootDir()
    {
        return $this->kernelRootDir;
    }

    private function registerSkeletonDirs()
    {
        $dirs = [];

        if (is_dir($dir = dirname($this->kernelRootDir).'/templates/bundles/SensioGenerator/skeleton')) {
            $dirs[] = $dir;
        }

        $dirs[] = dirname(dirname(__FILE__)).'/Resources/skeleton';

        $this->skeletonDirs = $dirs;
    }

    protected function render($template, $parameters)
    {
        $twig = $this->getTwigEnvironment();

        return $twig->render($template, $parameters);
    }

    /**
     * Gets the twig environment that will render skeletons.
     *
     * @return \Twig_Environment
     */
    protected function getTwigEnvironment()
    {
        return new \Twig_Environment(
            new \Twig_Loader_Filesystem($this->skeletonDirs), [
                'debug'            => true,
                'cache'            => false,
                'strict_variables' => true,
                'autoescape'       => false,
            ]
        );
    }

    protected function renderFile($template, $target, $parameters)
    {
        self::mkdir(dirname($target));

        return self::dump($target, $this->render($template, $parameters));
    }

    /**
     * @internal
     */
    public static function mkdir($dir, $mode = 0777, $recursive = true)
    {
        if (!file_exists($dir)) {
            mkdir($dir, $mode, $recursive);
            self::writeln(sprintf('  <fg=green>created</> %s', self::relativizePath($dir)));
        }
    }

    /**
     * @internal
     */
    public static function dump($filename, $content)
    {
        if (file_exists($filename)) {
            self::writeln(sprintf('  <fg=yellow>updated</> %s', self::relativizePath($filename)));
        } else {
            self::writeln(sprintf('  <fg=green>created</> %s', self::relativizePath($filename)));
        }

        return file_put_contents($filename, $content);
    }

    private static function writeln($message)
    {
        if (null === self::$output) {
            self::$output = new ConsoleOutput();
        }

        self::$output->writeln($message);
    }

    private static function relativizePath($absolutePath)
    {
        $relativePath = str_replace([getcwd(), '\\'], ['.', '/'], $absolutePath);

        return is_dir($absolutePath) ? rtrim($relativePath, '/').'/' : $relativePath;
    }
}
