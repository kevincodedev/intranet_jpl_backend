<?php
// src/Controller/ProductoController.php

namespace App\Controller;

use App\Dto\ProductoCreateDto;
use App\Dto\ProductoUpdateDto;
use App\Service\ProductoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload; // Deserializa JSON a DTO
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/producto')] // Prefijo para todos los endpoints
class ProductoController extends AbstractController
{
    private $productoService;

    // Inyectamos el Service
    public function __construct(ProductoService $productoService)
    {
        $this->productoService = $productoService;
    }

    // --- ENDPOINT PAGINADO (GET) ---
    // GET /api/producto?page=1&limit=20
    #[Route('', name: 'app_producto_list', methods: ['GET'])]
    public function list(Request $request, SerializerInterface $serializer): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20); // Default 20

        $paginator = $this->productoService->getPaginated($page, $limit);

        // Serializamos los resultados para devolver JSON
        $data = $serializer->serialize($paginator->getIterator(), 'json', [
            'groups' => ['producto:read'] // Necesitas añadir grupos en la Entidad
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    // --- ENDPOINT BÚSQUEDA POR ID (GET) ---
    // GET /api/producto/5
    #[Route('/{id}', name: 'app_producto_show', methods: ['GET'])]
    public function show(int $id, SerializerInterface $serializer): JsonResponse
    {
        $producto = $this->productoService->findById($id);

        $data = $serializer->serialize($producto, 'json', ['groups' => ['producto:read']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    // --- ENDPOINT CREACIÓN (POST) ---
    // POST /api/producto
    #[Route('', name: 'app_producto_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] ProductoCreateDto $dto, // Mapea JSON al DTO y lo valida
        SerializerInterface $serializer
    ): JsonResponse {
        
        $producto = $this->productoService->create($dto);

        $data = $serializer->serialize($producto, 'json', ['groups' => ['producto:read']]);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }

    // --- ENDPOINT ACTUALIZACIÓN (PUT) ---
    // PUT /api/producto/5
    #[Route('/{id}', name: 'app_producto_update', methods: ['PUT'])]
    public function update(
        int $id,
        #[MapRequestPayload] ProductoUpdateDto $dto,
        SerializerInterface $serializer
    ): JsonResponse {
        $producto = $this->productoService->update($id, $dto);

        $data = $serializer->serialize($producto, 'json', ['groups' => ['producto:read']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    // --- ENDPOINT SOFT DELETE (DELETE) ---
    // DELETE /api/producto/5
    #[Route('/{id}', name: 'app_producto_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->productoService->softDelete($id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}