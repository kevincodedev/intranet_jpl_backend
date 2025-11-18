<?php
// src/Service/ProductoService.php

namespace App\Service;

use App\Dto\ProductoCreateDto;
use App\Dto\ProductoUpdateDto;
use App\Entity\Producto;
use App\Repository\ProductoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductoService
{
    private $productoRepository;
    private $entityManager;

    // Inyección de dependencias (SOLID)
    public function __construct(
        ProductoRepository $productoRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->productoRepository = $productoRepository;
        $this->entityManager = $entityManager;
    }

    // --- LÓGICA DE PAGINADO (GET) ---
    public function getPaginated(int $page, int $limit): Paginator
    {
        // Validamos el límite
        if (!in_array($limit, [20, 50])) {
            $limit = 20; // Default
        }

        // El repositorio se encarga de la query (ver más abajo)
        return $this->productoRepository->findPaginated($page, $limit);
    }

    // --- LÓGICA DE CREACIÓN (POST) ---
    public function create(ProductoCreateDto $dto): Producto
    {
        $producto = new Producto();
        $producto->setNombre($dto->nombre);
        $producto->setSerial($dto->serial);
        $producto->setMarca($dto->marca);
        $producto->setModelo($dto->modelo);
        $producto->setSede($dto->sede);
        $producto->setOficina($dto->oficina);
        $producto->setDetalle($dto->detalle);
        $producto->setUbicacion($dto->ubicacion);
        // $producto->setDeleted(false); // Ya es 'false' por defecto (ver Entidad)

        $this->entityManager->persist($producto);
        $this->entityManager->flush();

        return $producto;
    }

    // --- LÓGICA DE SOFT DELETE (DELETE) ---
    public function softDelete(int $id): void
    {
        // Usamos el repositorio para buscar solo activos (ver más abajo)
        $producto = $this->productoRepository->findActive($id);

        if (!$producto) {
            throw new NotFoundHttpException('Producto no encontrado o ya eliminado.');
        }

        $producto->setDeleted(true); // ¡Eliminado Lógico!
        $this->entityManager->flush();
    }

    // --- LÓGICA DE BÚSQUEDA POR ID (GET /id) ---
    public function findById(int $id): Producto
    {
        $producto = $this->productoRepository->findActive($id);

        if (!$producto) {
            throw new NotFoundHttpException('Producto no encontrado.');
        }

        return $producto;
    }

    // --- LÓGICA DE ACTUALIZACIÓN (PUT) ---
    public function update(int $id, ProductoUpdateDto $dto): Producto
    {
        $producto = $this->productoRepository->findActive($id);

        if (!$producto) {
            throw new NotFoundHttpException('Producto no encontrado.');
        }

        // Actualizamos solo los campos que vienen en el DTO
        if ($dto->nombre !== null) {
            $producto->setNombre($dto->nombre);
        }
        if ($dto->serial !== null) {
            $producto->setSerial($dto->serial);
        }
        if ($dto->marca !== null) {
            $producto->setMarca($dto->marca);
        }
        if ($dto->modelo !== null) {
            $producto->setModelo($dto->modelo);
        }
        if ($dto->sede !== null) {
            $producto->setSede($dto->sede);
        }
        if ($dto->oficina !== null) {
            $producto->setOficina($dto->oficina);
        }
        if ($dto->detalle !== null) {
            $producto->setDetalle($dto->detalle);
        }
        if ($dto->ubicacion !== null) {
            $producto->setUbicacion($dto->ubicacion);
        }

        $this->entityManager->flush();

        return $producto;
    }
}