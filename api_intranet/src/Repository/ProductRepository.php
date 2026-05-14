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
    public function searchAndPaginate($term, $page = 1, $limit = 25, ?string $empresa = null, bool $onlyActive = true, $sort = 'id', $order = 'DESC')
    {
        $qb = $this->createQueryBuilder('p');

        // 1. Activity Filter
        if ($onlyActive) {
            $qb->andWhere('p.deletedAt IS NULL');
        }

        // 2. Empresa Filter 
        if ($empresa !== null && $empresa !== '') {
            $qb->andWhere('p.empresa = :empresa')
                ->setParameter('empresa', $empresa);
        }

        // 3. Incremental Search 
        if ($term !== null && $term !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    'p.nombre LIKE :term',
                    'p.color LIKE :term',
                    'p.categoria LIKE :term',
                    'p.marca LIKE :term',
                    'p.modelo LIKE :term',
                    'p.serial LIKE :term',
                    'p.locacion LIKE :term',
                    'p.caracteristicas LIKE :term',
                    'p.empresa LIKE :term'
                )
            )->setParameter('term', '%' . $term . '%');
        }

        // Dynamic Sorting
        $allowedFields = ['id', 'nombre', 'categoria', 'marca', 'modelo', 'color', 'serial', 'condicion', 'locacion', 'cantidad', 'empresa', 'registeredAt'];
        if (!in_array($sort, $allowedFields)) {
            $sort = 'id';
        }
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy('p.' . $sort, $order);

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
                'cantidad' => $product->getCantidad(),
                'empresa' => $product->getEmpresa(),
                'registeredAt' => $product->getRegisteredAt() ? $product->getRegisteredAt()->format('Y-m-d') : null,
                'isActive' => $product->isActive(),
            ];

            // If admin, include deletedat info
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
