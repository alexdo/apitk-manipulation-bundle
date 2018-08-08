<?php

declare(strict_types=1);

namespace Shopping\ApiTKManipulationBundle\Annotation;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

/**
 * Class Update.
 *
 * @Annotation
 *
 * Annotation for automatic handling of POST, PUT and PATCH methods.
 *
 * @example Update("deal", type=DealV1Type::class)
 * @example Update("user", type=UserV1Type::class, entityManager="otherConnection")
 * @example Update(
 *      "item",
 *      type="App\Form\Type\ItemV1Type",
 *      requestParam="item_name",
 *      repositoryFindMethodName="findByName"
 * )
 *
 * @package Shopping\ApiTKManipulationBundle\Annotation
 */
class Update extends ParamConverter
{
    /**
     * Specify the name of this filter.
     *
     * @var string
     */
    public $name;

    /**
     * @param $type
     */
    public function setType($type)
    {
        $options = $this->getOptions();
        $options['type'] = $type;

        $this->setOptions($options);
    }

    /**
     * @param $entityManager
     */
    public function setEntityManager($entityManager)
    {
        $options = $this->getOptions();
        $options['entityManager'] = $entityManager;

        $this->setOptions($options);
    }

    /**
     * @param $repositoryFindMethodName
     */
    public function setRepositoryFindMethodName($repositoryFindMethodName)
    {
        $options = $this->getOptions();
        $options['repositoryFindMethodName'] = $repositoryFindMethodName;

        $this->setOptions($options);
    }

    /**
     * @param $requestParam
     */
    public function setRequestParam($requestParam)
    {
        $options = $this->getOptions();
        $options['requestParam'] = $requestParam;

        $this->setOptions($options);
    }
}