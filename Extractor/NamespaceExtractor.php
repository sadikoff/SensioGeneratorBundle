<?php

namespace Sensio\Bundle\GeneratorBundle\Extractor;


class NamespaceExtractor
{
    public static function from($object)
    {
        $rc = new \ReflectionClass($object);

        return $rc->getNamespaceName() ?: 'App';
    }
}