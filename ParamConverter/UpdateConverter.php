<?php

declare(strict_types=1);

namespace Shopping\ApiTKManipulationBundle\ParamConverter;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityNotFoundException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Shopping\ApiTKCommonBundle\Exception\ValidationException;
use Shopping\ApiTKCommonBundle\ParamConverter\ContextAwareParamConverterTrait;
use Shopping\ApiTKCommonBundle\ParamConverter\EntityAwareParamConverterTrait;
use Shopping\ApiTKCommonBundle\ParamConverter\RequestParamAwareParamConverterTrait;
use Shopping\ApiTKManipulationBundle\Annotation\Update;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class UpdateConverter.
 *
 * Handles POST, PUT and PATCH requests for a given entity and form type.
 * For PUT & PATCH, it will fetch the requested entity from doctrine, create a form and validate it.
 * For POST, it will create a new entity instance from doctrine, create a form and validate it.
 *
 * Objects without validation errors will be updated/persisted to the DB automatically.
 *
 * @package Shopping\ApiTKManipulationBundle\ParamConverter
 */
class UpdateConverter implements ParamConverterInterface
{
    use ContextAwareParamConverterTrait;
    use EntityAwareParamConverterTrait;
    use RequestParamAwareParamConverterTrait;

    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var string
     */
    private $entityClass;

    /**
     * @var FormInterface
     */
    private $form;

    /**
     * UpdateConverter constructor.
     *
     * @param FormFactoryInterface $formFactory
     * @param ManagerRegistry      $registry
     */
    public function __construct(FormFactoryInterface $formFactory, ManagerRegistry $registry)
    {
        $this->registry = $registry;
        $this->formFactory = $formFactory;
    }

    /**
     * Stores the object in the request.
     *
     * @param Request        $request
     * @param ParamConverter $configuration Contains the name, class and options of the object
     *
     * @throws EntityNotFoundException
     * @throws \InvalidArgumentException
     *
     * @return bool True if the object has been successfully set, else false
     */
    public function apply(Request $request, ParamConverter $configuration)
    {
        $this->initialize($request, $configuration);

        if ($this->getOption('type') === null) {
            throw new \InvalidArgumentException('You have to specify "type" option for the UpdateConverter.');
        }

        // already create the form to read the data_class from it
        $this->form = $this->formFactory->create($this->getOption('type'), null, ['csrf_protection' => false]);
        $this->entityClass = $this->form->getConfig()->getDataClass();

        if ($this->entityClass === null) {
            throw new \InvalidArgumentException(
                'You have to specify "data_class" option in "' . $this->getOption('type') . '" for the UpdateConverter.'
            );
        }

        if ($request->isMethod(Request::METHOD_POST)) {
            $entity = null;
        } else {
            $entity = $this->getEntity();
        }

        $updatedEntity = $this->validateForm($this->form, $entity, $request);

        $om = $this->getManager();
        $om->persist($updatedEntity);
        $om->flush();

        $request->attributes->set($configuration->getName(), $updatedEntity);

        return true;
    }

    /**
     * @throws EntityNotFoundException
     *
     * @return mixed Entity
     */
    private function getEntity()
    {
        if ($this->getRequestParamValue() === null) {
            throw new \InvalidArgumentException(
                sprintf(
                    '""%s" is missing from the Request attributes but is required for the UpdateConverter. '
                    . 'It defaults to "id" but may be changed via the "requestParam" option',
                    $this->getRequestParamName()
                )
            );
        }

        $result = $this->findInRepository();

        if ($result === null) {
            throw new EntityNotFoundException(
                sprintf(
                    'Unable to find Entity of class %s with %s "%s"',
                    $this->entityClass,
                    $this->getRequestParamName(),
                    $this->getRequestParamValue()
                )
            );
        }

        return $result;
    }

    /**
     * @param FormInterface $form
     * @param Request       $request
     * @param mixed         $data
     *
     * @return mixed Updated entity
     */
    private function validateForm(FormInterface $form, $data, Request $request)
    {
        $form->setData($data);

        // clearMissing = true when method is put or post; patch allows partial bodies
        $form->submit($request->request->all(), !$request->isMethod(Request::METHOD_PATCH));

        if (!$form->isValid()) {
            throw new ValidationException((string) $form->getErrors());
        }

        return $form->getData();
    }

    /**
     * @return null|mixed Returns a matching entity or 0 if nothing has been found
     */
    private function findInRepository()
    {
        $repository = $this->getManager()->getRepository($this->entityClass);
        $methodName = $this->getRepositoryMethodName('find');

        return $repository->$methodName($this->getRequestParamValue());
    }

    /**
     * Checks if the object is supported.
     *
     * @param ParamConverter $configuration
     *
     * @return bool True if the object is supported, else false
     */
    public function supports(ParamConverter $configuration)
    {
        return $configuration instanceof Update && $this->registry instanceof ManagerRegistry;
    }
}
