<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(UserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        //saves the new hashed password to DB
        $user->setPassword($newHashedPassword);
        $this->_em->persist($user);
        $this->_em->flush();
    }
    public function searchAndPaginate($term, $page = 1, $limit = 25, bool $hasAdminAccess = false, ?string $role = null)
    {
        $qb = $this->createQueryBuilder('u');

        // Non-admins only see non-deleted users
        if (!$hasAdminAccess) {
            $qb->andWhere('u.deletedAt IS NULL');
        }

        //  Primary role (category) filter:
        if ($role !== null && $role !== '') {
            $qb->andWhere('u.roles LIKE :role')
                ->setParameter('role', '%"' . $role . '"%');
        }

        // Secondary search term filter
        if ($term) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'u.email LIKE :term',
                    'u.name LIKE :term',
                    'u.surname LIKE :term'
                )
            )->setParameter('term', '%' . $term . '%');
        }

        $qb->orderBy('u.id', 'DESC');

        // Apply Pagination
        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $totalPages = ceil((int)$totalItems / $limit);

        $data = [];
        foreach ($paginator as $user) {
            $roles = $user->getRoles();
            $userArray = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'surname' => $user->getSurname(),
                'role' => count($roles) > 0 ? $roles[0] : 'ROLE_USER',
                'isActive' => $user->isActive(),
            ];

            if ($hasAdminAccess) {
                $userArray['deletedAt'] = $user->getDeletedAt() ? $user->getDeletedAt()->format('Y-m-d H:i:s') : null;
            }

            $data[] = $userArray;
        }

        return [
            'data' => $data,
            'meta' => [
                'total_items' => $totalItems,
                'total_pages' => (int)$totalPages,
                'current_page' => (int)$page,
                'limit' => (int)$limit
            ]
        ];
    }
}
