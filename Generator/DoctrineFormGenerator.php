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

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * Generates a form class based on a Doctrine entity.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Hugo Hamon <hugo.hamon@sensio.com>
 */
class DoctrineFormGenerator extends Generator
{
    private $className;
    private $classPath;

    private $registry;
    
        public function __construct(Filesystem $filesystem, $projectDir, RegistryInterface $registry)
        {
            parent::__construct($filesystem, $projectDir);
    
            $this->registry = $registry;
        }

    public function getClassName()
    {
        return $this->className;
    }

    public function getClassPath()
    {
        return $this->classPath;
    }

    /**
     * Generates the entity form class.
     *
     * @param string            $entity         The entity relative class name
     * @param bool              $forceOverwrite If true, remove any existing form class before generating it again
     */
    public function generate($entity, $forceOverwrite = false)
    {
        $parts = explode('\\', $entity);
        $entityClass = array_pop($parts);

        $this->className = $entityClass.'Type';
        $dirPath = $this->getKernelRootDir().'/Form';
        $this->classPath = $dirPath.'/'.str_replace('\\', '/', $entity).'Type.php';

        $manager = $this->registry->getManager();
        $metadata = $manager->getClassMetadata('App\\Entity\\'.$entity);

        if (!$forceOverwrite && file_exists($this->classPath)) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to generate the %s form class as it already exists under the %s file',
                    $this->className,
                    $this->classPath
                )
            );
        }

        if (count($metadata->identifier) > 1) {
            throw new \RuntimeException(
                'The form generator does not support entity classes with multiple primary keys.'
            );
        }

        $parts = explode('\\', $entity);
        array_pop($parts);

        $this->renderFile(
            'form/FormType.php.twig',
            $this->classPath,
            [
                'fields'           => $this->getFieldsFromMetadata($metadata),
                'entity_namespace' => implode('\\', $parts),
                'entity_class'     => $entityClass,
                'form_class'       => $this->className,
                'form_type_name'   => strtolower(
                    'app'.($parts ? '_' : '').implode('_', $parts).'_'.substr($this->className, 0, -4)
                ),
            ]
        );
    }

    /**
     * Returns an array of fields. Fields can be both column fields and
     * association fields.
     *
     * @param ClassMetadataInfo $metadata
     *
     * @return array $fields
     */
    private function getFieldsFromMetadata(ClassMetadataInfo $metadata)
    {
        $fields = (array)$metadata->fieldNames;

        // Remove the primary key field if it's not managed manually
        if (!$metadata->isIdentifierNatural()) {
            $fields = array_diff($fields, $metadata->identifier);
        }

        foreach ($metadata->associationMappings as $fieldName => $relation) {
            if ($relation['type'] !== ClassMetadataInfo::ONE_TO_MANY) {
                $fields[] = $fieldName;
            }
        }

        return $fields;
    }
}
