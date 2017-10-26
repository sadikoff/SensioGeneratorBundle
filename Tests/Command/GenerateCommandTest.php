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
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;

abstract class GenerateCommandTest extends TestCase
{
    protected $tmpDir = null;

    protected function tearDown()
    {
        if (null !== $this->tmpDir) {
            $fs = new Filesystem();
            $fs->remove(dirname($this->tmpDir));
        }
    }

    protected function getHelperSet()
    {
        return new HelperSet(array(new FormatterHelper(), new QuestionHelper()));
    }

    /**
     * @param CommandTester $tester
     * @param $input
     */
    protected function setInputs($tester, $input)
    {
        $input .= str_repeat("\n", 10);

        $tester->setInputs(explode("\n", $input));
    }

    protected function getKernelRootDir()
    {
        if (null !== $this->tmpDir) {
            return $this->tmpDir;
        }

        $this->tmpDir = sys_get_temp_dir().'/sf'.mt_rand(111111, 999999).'/src';
        @mkdir($this->tmpDir, 0777, true);

        return $this->tmpDir;
    }
}
