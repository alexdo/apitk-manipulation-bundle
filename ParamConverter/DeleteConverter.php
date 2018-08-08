<?php

declare(strict_types=1);

namespace Shopping\ApiTKManipulationBundle\ParamConverter;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Shopping\ApiTKManipulationBundle\Annotation\Delete;
use Shopping\ApiTKManipulationBundle\Exception\DeletionException;
use Shopping\ApiTKManipulationBundle\Repository\ApiTKDeletableRepositoryInterface;
use Shopping\ApiTKManipulationBundle\Service\ApiTKDeletionService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class DeleteConverter.
 *
 * ParamConverter to delete an entity from the database.
 *
 * @package Shopping\ApiTKManipulationBundle\ParamConverter
 *
 * @author Alexander Dormann <alexander.dormann@check24.de>
 */
class DeleteConverter implements ParamConverterInterface
{
    /**
     * @var ManagerRegistry
     */
    private $registry;
    /**
     * @var ApiTKDeletionService
     */
    private $deletionService;

    /**
     * UpdateConverter constructor.
     *
     * @param ApiTKDeletionService $deletionService
     * @param ManagerRegistry|null $registry
     */
    public function __construct(ApiTKDeletionService $deletionService, ManagerRegistry $registry = null)
    {
        $this->registry = $registry;
        $this->deletionService = $deletionService;
    }

    /**
     * @param Request        $request
     * @param ParamConverter $configuration
     *
     * @throws EntityNotFoundException
     * @throws DeletionException
     * @throws \InvalidArgumentException
     *
     * @return bool
     */
    public function apply(Request $request, ParamConverter $configuration)
    {
        $options = $configuration->getOptions();

        if (!isset($options['entity'])) {
            throw new \InvalidArgumentException('You have to specify "entity" option for the DeleteConverter.');
        }

        $requestParamName = $configuration->getName();
        $requestParam = $request->attributes->get($requestParamName);

        $this->deleteInRepository(
            $options['entity'],
            $requestParam,
            $requestParamName,
            $options['entityManager'] ?? null
        );

        $response = new Response(null, Response::HTTP_NO_CONTENT);

        $request->attributes->set('response', $response);

        return true;
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
        return $configuration instanceof Delete && $this->registry instanceof ManagerRegistry;
    }

    /**
     * @param string      $entity
     * @param string      $requestParam
     * @param string      $requestParamName
     * @param string|null $manager
     *
     * @throws DeletionException
     * @throws EntityNotFoundException
     *
     * @return bool Deletion status
     */
    private function deleteInRepository(
        string $entity,
        string $requestParam,
        string $requestParamName,
        string $manager = null
    ): bool {
        $om = $this->getManager($manager, $entity);
        $repository = $om->getRepository($entity);

        if (!$repository instanceof ApiTKDeletableRepositoryInterface) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Repository for entity "%s" does not implement the ApiTKDeletableRepositoryInterface.',
                    $entity
                )
            );
        }

        // fill deletion service with appropriate values
        $this->deletionService
            ->setParameterName($requestParamName)
            ->setParameterValue($requestParam);

        /*
         * normalize exceptions:
         * - DeleteException for ORM errors
         * - EntityNotFoundException when the given ID doesn't return an entity
         */

        try {
            $result = $repository->deleteByRequest($this->deletionService);
        } catch (EntityNotFoundException $e) {
            // check for meaningful error message and re-raise
            if (empty($e->getMessage())) {
                throw new EntityNotFoundException(
                    sprintf(
                        'Unable to find Entity of class %s with %s "%s" for deletion.',
                        $entity,
                        $requestParamName,
                        $requestParam
                    ),
                    0,
                    $e
                );
            }

            throw $e;
        } catch (OptimisticLockException $e) {
            throw new DeletionException($e->getMessage(), 0, $e);
        } catch (ORMException $e) {
            throw new DeletionException($e->getMessage(), 0, $e);
        }

        // deleteByRequest can return false when the deletion procedure failed or 0 rows were deleted, etc.
        if ($result === false) {
            throw new DeletionException(
                sprintf(
                    'Unable to delete Entity of class %s with %s "%s".',
                    $entity,
                    $requestParamName,
                    $requestParam
                )
            );
        }

        return $result;
    }

    /**
     * @param string|null $name
     * @param string      $entity
     *
     * @return \Doctrine\Common\Persistence\ObjectManager|null
     */
    private function getManager(?string $name, string $entity)
    {
        if (null === $name) {
            return $this->registry->getManagerForClass($entity);
        }

        return $this->registry->getManager($name);
    }
}