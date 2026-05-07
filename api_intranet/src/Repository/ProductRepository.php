<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    //function handling search field and pagination
    public function searchAndPaginate($term, $page = 1, $limit = 25, bool $onlyActive = true)
    {
        $qb = $this->createQueryBuilder('p');

        // Only filter by deletedAt if $onlyActive is true (non-admins)
        if ($onlyActive) {
            $qb->where('p.deletedAt IS NULL');
        }

        // Incremental Search
        if ($term !== null && $term !== '') {
            // Use andWhere so it doesn't overwrite the deletedAt filter above
            $qb->andWhere('p.nombre LIKE :term OR p.color LIKE :term OR p.categoria LIKE :term OR p.marca LIKE :term OR p.modelo LIKE :term OR p.serial LIKE :term OR p.locacion LIKE :term OR p.caracteristicas LIKE :term')
                ->setParameter('term', '%' . $term . '%');
        }

        $qb->orderBy('p.id', 'DESC');

        // Pagination
        $offset = ($page - 1) * $limit;
        $qb->setFirstResult($offset)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $totalPages = ceil($totalItems / $limit);

        $data = [];
        foreach ($paginator as $product) {
            $productArray = [
                'id' => $product->getId(),
                'nombre' => $product->getNombre(),
                'categoria' => $product->getCategoria(),
                'marca' => $product->getMarca(),
                'modelo' => $product->getModelo(),
                'caracteristicas' => $product->getCaracteristicas(),
                'color' => $product->getColor(),
                'serial' => $product->getSerial(),
                'condicion' => $product->getCondicion(),
                'locacion' => $product->getLocacion(),
            ];

            // If an admin is viewing, let's include the deletedAt info in the list too
            if (!$onlyActive) {
                $productArray['deletedAt'] = $product->getDeletedAt() ? $product->getDeletedAt()->format('Y-m-d H:i:s') : null;
            }

            $data[] = $productArray;
        }

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
