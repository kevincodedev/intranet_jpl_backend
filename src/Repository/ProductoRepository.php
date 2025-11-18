<?php
// src/Repository/ProductoRepository.php

namespace App\Repository;

use App\Entity\Producto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator; // Importante

class ProductoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Producto::class);
    }

    // Método para el Soft Delete
    public function findActive(int $id): ?Producto
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.id = :id')
            ->andWhere('p.deleted = false') // Clave del Soft Delete
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // Método para el Paginado
    public function findPaginated(int $page, int $limit): Paginator
    {
        $query = $this->createQueryBuilder('p')
            ->andWhere('p.deleted = false') // Clave del Soft Delete
            ->orderBy('p.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        return new Paginator($query);
    }
}