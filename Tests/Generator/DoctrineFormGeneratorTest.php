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

use Sensio\Bundle\GeneratorBundle\Generator\DoctrineFormGenerator;

class DoctrineFormGeneratorTest extends GeneratorTest
{
    public function testGenerate()
    {
        $this->generateForm(false);

        $this->assertTrue(file_exists($this->tmpDir.'/Form/PostType.php'));

        $content = file_get_contents($this->tmpDir.'/Form/PostType.php');
        $this->assertContains('namespace App\Form', $content);
        $this->assertContains('use App\Entity\Post;', $content);
        $this->assertContains('class PostType extends AbstractType', $content);
        $this->assertContains('->add(\'title\')', $content);
        $this->assertContains('->add(\'createdAt\')', $content);
        $this->assertContains('->add(\'publishedAt\')', $content);
        $this->assertContains('->add(\'updatedAt\')', $content);
        $this->assertContains('public function configureOptions(OptionsResolver $resolver)', $content);
        $this->assertContains('\'data_class\' => Post::class', $content);
    }

    public function testGenerateSubNamespacedEntity()
    {
        $this->generateSubNamespacedEntityForm(false);

        $this->assertTrue(file_exists($this->tmpDir.'/Form/Blog/PostType.php'));

        $content = file_get_contents($this->tmpDir.'/Form/Blog/PostType.php');
        $this->assertContains('namespace App\Form\Blog', $content);
        $this->assertContains('use App\Entity\Blog\Post;', $content);
        $this->assertContains('class PostType extends AbstractType', $content);
        $this->assertContains('->add(\'title\')', $content);
        $this->assertContains('->add(\'createdAt\')', $content);
        $this->assertContains('->add(\'publishedAt\')', $content);
        $this->assertContains('->add(\'updatedAt\')', $content);
        $this->assertContains('public function configureOptions(OptionsResolver $resolver)', $content);
        $this->assertContains('\'data_class\' => Post::class', $content);
        $this->assertContains('public function getBlockPrefix()', $content);
        $this->assertContains('return \'app_blog_post\';', $content);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp: Unable to generate the PostType form class as it already exists under the .* file
     */
    public function testNonOverwrittenForm()
    {
        $this->generateForm(false);
        $this->generateForm(false);
    }

    public function testOverwrittenForm()
    {
        $this->generateForm(false);
        $this->generateForm(true);

        $this->assertTrue(file_exists($this->tmpDir.'/Form/PostType.php'));
    }

    private function generateForm($overwrite)
    {
        $generator = new DoctrineFormGenerator($this->filesystem, $this->tmpDir, $this->getRegistry());

        $generator->generate('Post', $overwrite);
    }

    private function generateSubNamespacedEntityForm($overwrite)
    {
        $generator = new DoctrineFormGenerator($this->filesystem, $this->tmpDir, $this->getRegistry());

        $generator->generate('Blog\Post', $overwrite);
    }

    public function getRegistry()
    {
        $registry = $this->getMockBuilder('Symfony\Bridge\Doctrine\RegistryInterface')->getMock();
        $registry->expects($this->any())->method('getManager')->will($this->returnValue($this->getManager()));

        return $registry;
    }

    public function getManager()
    {
        $manager = $this->getMockBuilder('Doctrine\ORM\EntityManagerInterface')->getMock();
        $manager->expects($this->any())
            ->method('getClassMetadata')
            ->will($this->returnValue($this->getMetadata()));

        return $manager;
    }

    public function getMetadata()
    {
        $metadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadataInfo')->disableOriginalConstructor()->getMock();
        $metadata->identifier = array('id');
        $metadata->fieldMappings = array(
            'title' => array('type' => 'string'),
            'createdAt' => array('type' => 'date'),
            'publishedAt' => array('type' => 'time'),
            'updatedAt' => array('type' => 'datetime'),
        );
        $metadata->associationMappings = $metadata->fieldMappings;

        return $metadata;
    }
}
