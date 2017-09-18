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

use Sensio\Bundle\GeneratorBundle\Model\EntityGeneratorResult;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\EntityGenerator;
use Doctrine\ORM\Tools\EntityRepositoryGenerator;
use Doctrine\ORM\Tools\Export\ClassMetadataExporter;
use Doctrine\Common\Util\Inflector;


/**
 * Generates a Doctrine entity class based on its name, fields and format.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class DoctrineEntityGenerator extends Generator
{
    private $registry;

    public function __construct(RegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param KernelInterface $kernel
     * @param string          $entity
     * @param string          $format
     * @param array           $fields
     *
     * @return EntityGeneratorResult
     *
     * @throws \Doctrine\ORM\Mapping\MappingException
     * @throws \Doctrine\ORM\Tools\Export\ExportException
     */
    public function generate(KernelInterface $kernel, $entity, $format, array $fields)
    {
        // configure the bundle (needed if the bundle does not contain any Entities yet)
        $config = $this->registry->getManager(null)->getConfiguration();

        $rc = new \ReflectionClass($kernel);

        $config->setEntityNamespaces(array_merge(
            array($rc->getNamespaceName() => $rc->getNamespaceName().'\\Entity'),
            $config->getEntityNamespaces()
        ));

        $entityClass = $this->registry->getAliasNamespace($rc->getNamespaceName()).'\\'.$entity;
        $entityPath = $kernel->getRootDir().'/Entity/'.str_replace('\\', '/', $entity).'.php';
        if (file_exists($entityPath)) {
            throw new \RuntimeException(sprintf('Entity "%s" already exists.', $entityClass));
        }

        $class = new ClassMetadataInfo($entityClass, $config->getNamingStrategy());
        $class->customRepositoryClassName = str_replace('\\Entity\\', '\\Repository\\', $entityClass).'Repository';
        $class->mapField(array('fieldName' => 'id', 'type' => 'integer', 'id' => true));
        $class->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_AUTO);
        foreach ($fields as $field) {
            $class->mapField($field);
        }

        $entityGenerator = $this->getEntityGenerator();
        $class->setPrimaryTable(array('name' => Inflector::tableize(str_replace('\\', '', $entity))));
        if ('annotation' === $format) {
            $entityGenerator->setGenerateAnnotations(true);
            $entityCode = $entityGenerator->generateEntityClass($class);
            $mappingPath = $mappingCode = false;
        } else {
            $cme = new ClassMetadataExporter();
            $exporter = $cme->getExporter('yml' == $format ? 'yaml' : $format);
            $mappingPath = $kernel->getRootDir().'/../config/doctrine/'.str_replace('\\', '.', $entity).'.orm.'.$format;

            if (file_exists($mappingPath)) {
                throw new \RuntimeException(sprintf('Cannot generate entity when mapping "%s" already exists.', $mappingPath));
            }

            $mappingCode = $exporter->exportClassMetadata($class);
            $entityGenerator->setGenerateAnnotations(false);
            $entityCode = $entityGenerator->generateEntityClass($class);
        }
        $entityCode = str_replace(
            array("@var integer\n", "@var boolean\n", "@param integer\n", "@param boolean\n", "@return integer\n", "@return boolean\n"),
            array("@var int\n", "@var bool\n", "@param int\n", "@param bool\n", "@return int\n", "@return bool\n"),
            $entityCode
        );

        self::mkdir(dirname($entityPath));
        self::dump($entityPath, $entityCode);

        if ($mappingPath) {
            self::mkdir(dirname($mappingPath));
            self::dump($mappingPath, $mappingCode);
        }

        $repositoryPath = $kernel->getRootDir().'/Repository/'.$entity.'Repository.php';
        $repositoryCode = $this->getRepositoryGenerator()->generateEntityRepositoryClass($class->customRepositoryClassName);

        self::mkdir(dirname($repositoryPath));
        self::dump($repositoryPath, $repositoryCode);

        return new EntityGeneratorResult($entityPath, $repositoryPath, $mappingPath);
    }

    public function isReservedKeyword($keyword)
    {
        return $this->registry->getConnection()->getDatabasePlatform()->getReservedKeywordsList()->isKeyword($keyword);
    }

    protected function getEntityGenerator()
    {
        $entityGenerator = new EntityGenerator();
        $entityGenerator->setGenerateAnnotations(false);
        $entityGenerator->setGenerateStubMethods(true);
        $entityGenerator->setRegenerateEntityIfExists(false);
        $entityGenerator->setUpdateEntityIfExists(true);
        $entityGenerator->setNumSpaces(4);
        $entityGenerator->setAnnotationPrefix('ORM\\');

        return $entityGenerator;
    }

    protected function getRepositoryGenerator()
    {
        return new EntityRepositoryGenerator();
    }

    /**
     * Checks if the given name is a valid PHP variable name.
     *
     * @see http://php.net/manual/en/language.variables.basics.php
     *
     * @param $name string
     *
     * @return bool
     */
    public function isValidPhpVariableName($name)
    {
        return (bool) preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $name, $matches);
    }
}
