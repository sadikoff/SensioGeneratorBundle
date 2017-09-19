<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sensio\Bundle\GeneratorBundle\Tests\Generator;

use Sensio\Bundle\GeneratorBundle\Generator\ControllerGenerator;

class ControllerGeneratorTest extends GeneratorTest
{
    public function testGenerateController()
    {
        $this->getGenerator()->generate($this->getKernel(), 'Welcome', 'annotation', 'twig');

        $files = array(
            'Controller/WelcomeController.php',
            'Tests/Controller/WelcomeControllerTest.php',
        );
        foreach ($files as $file) {
            $this->assertTrue(file_exists($this->tmpDir.'/'.$file), sprintf('%s has been generated', $file));
        }

        $content = file_get_contents($this->tmpDir.'/Controller/WelcomeController.php');
        $strings = array(
            'namespace App\\Controller',
            'class WelcomeController',
        );
        foreach ($strings as $string) {
            $this->assertContains($string, $content);
        }

        $content = file_get_contents($this->tmpDir.'/Tests/Controller/WelcomeControllerTest.php');
        $strings = array(
            'namespace App\\Tests\\Controller',
            'class WelcomeControllerTest',
        );
        foreach ($strings as $string) {
            $this->assertContains($string, $content);
        }
    }

    public function testGenerateActions()
    {
        $generator = $this->getGenerator();
        $actions = array(
            0 => array(
                'name' => 'showPageAction',
                'route' => '/{id}/{slug}',
                'placeholders' => array('id', 'slug'),
                'template' => 'Page\show_page.html.twig',
            ),
            1 => array(
                'name' => 'getListOfPagesAction',
                'route' => '/_get-pages/{max_count}',
                'placeholders' => array('max_count'),
                'template' => 'Page\pages_list.html.twig',
            ),
        );

        $generator->generate($this->getKernel(), 'Page', 'annotation', 'twig', $actions);

        $files = array(
            '../templates/Page/show_page.html.twig',
            '../templates/Page/pages_list.html.twig',
        );
        foreach ($files as $file) {
            $this->assertTrue(file_exists($this->tmpDir.'/'.$file), sprintf('%s has been generated', $file));
        }

        $content = file_get_contents($this->tmpDir.'/Controller/PageController.php');
        $strings = array(
            'public function showPageAction($id, $slug)',
            'public function getListOfPagesAction($max_count)',
            'return $this->render(\'Page\show_page.html.twig\', array(',
            'return $this->render(\'Page\pages_list.html.twig\', array(',
        );
        foreach ($strings as $string) {
            $this->assertContains($string, $content);
        }
    }

    public function testGenerateActionsWithNonDefaultFormats()
    {
        $generator = $this->getGenerator();

        $generator->generate($this->getKernel(), 'Page', 'yaml', 'php', array(
            1 => array(
                'name' => 'showPageAction',
                'route' => '/{slug}',
                'placeholders' => array('slug'),
                'template' => 'Page\showPage.html.php',
            ),
        ));

        $generator->generate($this->getKernel(), 'Backend\Page', 'yaml', 'php', array(
            1 => array(
                'name' => 'showPageAction',
                'route' => '/backend/{slug}',
                'placeholders' => array('slug'),
                'template' => 'Backend\Page\showPage.html.php',
            ),
        ));

        $files = array(
            '../templates/Page/showPage.html.php',
            '../config/routes/page.yaml',
            '../templates/Backend/Page/showPage.html.php',
            '../config/routes/backend_page.yaml',
        );
        foreach ($files as $file) {
            $this->assertTrue(file_exists($this->tmpDir.'/'.$file), $file.' has been generated');
        }

        $content = file_get_contents($this->tmpDir.'/Controller/PageController.php');
        $this->assertNotContains('@Route()', $content, 'Routing is done via a yml file');
        $this->assertContains("return \$this->render('Page\showPage.html.php', array(", $content, 'Controller renders template');

        $content = file_get_contents($this->tmpDir.'/Controller/Backend/PageController.php');
        $this->assertNotContains('@Route()', $content, 'Routing is done via a yml file');
        $this->assertContains("return \$this->render('Backend\Page\showPage.html.php', array(", $content, 'Controller renders template');

        $content = file_get_contents($this->tmpDir.'/../templates/Page/showPage.html.php');
        $this->assertContains('Page:showPage', $content);

        $content = file_get_contents($this->tmpDir.'/../templates/Backend/Page/showPage.html.php');
        $this->assertContains('Backend\Page:showPage', $content);

        $content = file_get_contents($this->tmpDir.'/../config/routes/page.yaml');
        $this->assertContains("page_show_page:\n    path:     /{slug}\n    defaults: { _controller: App\Controller\PageController:showPageAction }", $content);

        $content = file_get_contents($this->tmpDir.'/../config/routes/backend_page.yaml');
        $this->assertContains("backend_page_show_page:\n    path:     /backend/{slug}\n    defaults: { _controller: App\Controller\Backend\PageController:showPageAction }", $content);
    }

    protected function getGenerator()
    {
        $generator = new ControllerGenerator();
        $generator->setSkeletonDirs(__DIR__.'/../../Resources/skeleton');

        return $generator;
    }

    protected function getKernel()
    {

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\KernelInterface')->getMock();
        $kernel
            ->expects($this->any())
            ->method('getRootDir')
            ->will($this->returnValue($this->tmpDir))
        ;

        return $kernel;
    }
}
