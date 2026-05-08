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

    //Rehashes the user's password automatically over time.
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
    //Queries only the page requested
    public function searchAndPaginate($term, $page = 1, $limit = 25, bool $hasAdminAccess = false, ?string $role = null)
    {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.role', 'r')
            ->addSelect('r'); // loads role into query to avoid N+1 queries

        // Logic: Non-admins only see non-deleted users
        if (!$hasAdminAccess) {
            $qb->where('u.deletedAt IS NULL');
        }

        // Optional Search (Email, Name, Surname)
        if ($term) {
            $qb->andWhere('u.email LIKE :term OR u.name LIKE :term OR u.surname LIKE :term')
                ->setParameter('term', '%' . $term . '%');
        }

        // Role Filter
        if ($role) {
            $qb->andWhere('r.name = :role')
                ->setParameter('role', $role);
        }

        $qb->orderBy('u.id', 'DESC');

        // Apply Pagination
        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $totalPages = ceil($totalItems / $limit);

        $data = [];
        foreach ($paginator as $user) {
            $userArray = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'surname' => $user->getSurname(),
                'role' => $user->getRole() ? $user->getRole()->getName() : 'ROLE_USER',
            ];
            //Admins have access to unactive roles and their detialed role permission list
            if ($hasAdminAccess) {
                $userArray['isActive'] = $user->isActive();
                $userArray['deletedAt'] = $user->getDeletedAt() ? $user->getDeletedAt()->format('Y-m-d H:i:s') : null;
                $userArray['roles'] = $user->getRoles();
            }

            $data[] = $userArray;
        }
        //Pagination metadata
        return [
            'data' => $data,
            'meta' => [
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
                'current_page' => (int) $page,
                'limit' => (int) $limit
            ]
        ];
    }
}
