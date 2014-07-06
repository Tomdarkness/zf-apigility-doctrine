<?php

namespace ZF\Apigility\Doctrine\Server\Hydrator\Strategy;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;
use Zend\Stdlib\Hydrator\Strategy\StrategyInterface;
use DoctrineModule\Stdlib\Hydrator\Strategy\AbstractCollectionStrategy;
use ZF\Hal\Link\Link;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\ServiceManagerAwareInterface;
use Zend\Filter\FilterChain;

/**
 * A field-specific hydrator for collecitons
 *
 * @returns Hal\Link
 */
class EntityLink extends AbstractCollectionStrategy
    implements StrategyInterface, ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;

    private $fieldName;

    public function setFieldName($fieldName)
    {
        $this->fieldName = $fieldName;
    }

    public function extract($value)
    {
        $config = $this->getServiceLocator()->get('Config');

        $className = get_class($value);

        if (!isset($config['zf-hal']['metadata_map'][$className])) {
            return;
        }

        $config = $config['zf-hal']['metadata_map'][$className];

        $entityIdName = $config['entity_identifier_name'];
        $routeIdName = $config['route_identifier_name'];

        $entityIdFunc = "get" . ucwords($entityIdName);

        $entityId = $value->$entityIdFunc();

        $link = new Link($this->fieldName);
        $link->setRoute($config['route_name']);
        $link->setRouteParams(array($routeIdName => $entityId));

        return $link;
    }

    public function hydrate($value)
    {
        // Hydration is not supported for collections.
        // A call to PATCH will use hydration to extract then hydrate
        // an entity.  In this process a collection will be included
        // so no error is thrown here.
    }
}
