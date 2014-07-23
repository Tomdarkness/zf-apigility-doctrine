<?php

namespace ZF\Apigility\Doctrine\Server\Hydrator\Strategy;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;
use Zend\Stdlib\Hydrator\Strategy\StrategyInterface;
use DoctrineModule\Stdlib\Hydrator\Strategy\AbstractCollectionStrategy;
use ZF\Hal\Collection;
use ZF\Hal\Entity;

/**
 * A field-specific hydrator for collecitons
 *
 * @returns HalCollection
 */
class CollectionExtract extends AbstractCollectionStrategy
    implements StrategyInterface, ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;

    public function extract($value)
    {
        if (!$value) {
            return;
        }

        if (!$value->isInitialized()) {
            return;
        }

        $mapping = $value->getMapping();
        $mappedBy = $mapping['mappedBy'];
        $targetEntity = $mapping['targetEntity'];

        $config = $this->getServiceLocator()->get('Config');

        if (!isset($config['zf-hal']['metadata_map'][$targetEntity])) {
            return;
        }

        $hydratorName = $config['zf-hal']['metadata_map'][$targetEntity]['hydrator'];

        $hydrator = $this->getServiceLocator()->get('HydratorManager')->get($hydratorName);
        $entityLink = $this->getServiceLocator()->get('ZF\\Apigility\\Doctrine\\Server\\Hydrator\\Strategy\\EntityLink');

        $entityLink->setFieldName($mappedBy);

        if ($hydrator->getExtractService()->hasStrategy($mappedBy)) {
            $hydrator->getExtractService()->removeStrategy($mappedBy);
        }

        $hydrator->getExtractService()->addStrategy($mappedBy, $entityLink);

        $result = array();

        foreach ($value as $entity) {
            $entityName = get_class($entity);
            $metadata = $this->getObjectManager()->getClassMetadata($entityName);
            $id = $metadata->getIdentifierValues($entity);

            $result[] = new Entity($hydrator->getExtractService()->extract($entity), $id);
        }

        $halCollection = new Collection($result);
        $halCollection->setCollectionName($mapping['fieldName']);
        $halCollection->setCollectionRoute($hydratorName = $config['zf-hal']['metadata_map'][$targetEntity]['route_name']);
        $halCollection->setCollectionRouteOptions(
            array(
                'query' => array(
                    'query' => array(
                        array('field' => $mapping['mappedBy'], 'type'=>'eq', 'value' => $value->getOwner()->getId()),
                    ),
                )
            )
        );
        return $halCollection;
    }

    public function getObjectManager()
    {
        return $this->serviceLocator->get('Doctrine\ORM\EntityManager');
    }

    public function hydrate($value)
    {
        // Hydration is not supported for collections.
        // A call to PATCH will use hydration to extract then hydrate
        // an entity.  In this process a collection will be included
        // so no error is thrown here.
    }
}
