<?php

namespace App\Controller;

use App\Entity\Role;
use App\Entity\Permission;
use App\Repository\RoleRepository;
use App\Repository\PermissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Annotations as OA;

/**
 * @Route("/api/roles")
 */
class RoleController extends AbstractController
{
    /**
     * @Route("", methods={"GET"})
     * @OA\Get(
     *     path="/api/roles",
     *     summary="List all roles and their permissions",
     *     tags={"Roles"},
     *     @OA\Response(response=200, description="List of roles")
     * )
     */
    public function index(RoleRepository $repository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE');

        $roles = $repository->findAll();
        $data = [];

        foreach ($roles as $role) {
            $permissions = [];
            foreach ($role->getPermissions() as $permission) {
                $permissions[] = [
                    'id' => $permission->getId(),
                    'name' => $permission->getName()
                ];
            }

            $data[] = [
                'id' => $role->getId(),
                'name' => $role->getName(),
                'title' => $role->getTitle(),
                'permissions' => $permissions
            ];
        }

        return $this->json($data);
    }

    /**
     * @Route("", methods={"POST"})
     * @OA\Post(
     *     path="/api/roles",
     *     summary="Create a new role",
     *     tags={"Roles"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="title", type="string", example="Secretaria"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"), description="Array of permission names")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Role created")
     * )
     */
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE');

        $data = json_decode($request->getContent(), true);
        if (empty($data['title'])) {
            return $this->json(['error' => 'El título es obligatorio'], 400);
        }

        // Prevent creating a Super Admin via API
        if (strtoupper(trim($data['title'])) === 'SUPER ADMIN') {
            return $this->json(['error' => 'El rol Super Admin solo puede ser creado mediante scripts del sistema.'], 403);
        }

        $role = new Role();
        $role->setTitle($data['title']);

        // Optional: Sync Permissions on creation
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $permRepo = $em->getRepository(Permission::class);
            foreach ($data['permissions'] as $permName) {
                $permission = $permRepo->findOneBy(['name' => $permName]);
                if (!$permission) {
                    return $this->json(['error' => sprintf('El permiso "%s" no existe.', $permName)], 400);
                }
                $role->addPermission($permission);
            }
        }

        $em->persist($role);
        $em->flush();

        return $this->json(['message' => 'Rol creado', 'id' => $role->getId()], 201);
    }

    /**
     * @Route("/{id}", methods={"PUT"})
     * @OA\Put(
     *     path="/api/roles/{id}",
     *     summary="Update a role name or its permissions",
     *     tags={"Roles"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="title", type="string", example="Secretaria Ejecutiva"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"), description="Array of permission names")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Role updated")
     * )
     */
    public function update(int $id, Request $request, RoleRepository $roleRepo, PermissionRepository $permRepo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE');

        $role = $roleRepo->find($id);
        if (!$role) {
            return $this->json(['error' => 'Rol no encontrado'], 404);
        }

        $data = json_decode($request->getContent(), true);

        // Update Title (which auto-updates Name)
        if (!empty($data['title'])) {
            // Prevent renaming a role to Super Admin via API
            if (strtoupper(trim($data['title'])) === 'SUPER ADMIN') {
                return $this->json(['error' => 'No se puede asignar el título de Super Admin a través de la API.'], 403);
            }
            $role->setTitle($data['title']);
        }

        // Sync Permissions via removing all then readding
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            // Clear current permissions
            foreach ($role->getPermissions() as $p) {
                $role->removePermission($p);
            }

            // Add new ones
            foreach ($data['permissions'] as $permName) {
                $permission = $permRepo->findOneBy(['name' => $permName]);
                if (!$permission) {
                    return $this->json(['error' => sprintf('El permiso "%s" no existe.', $permName)], 400);
                }
                $role->addPermission($permission);
            }
        }

        $em->flush();

        return $this->json(['message' => 'Rol actualizado correctamente']);
    }

    /**
     * @Route("/{id}", methods={"DELETE"})
     * @OA\Delete(
     *     path="/api/roles/{id}",
     *     summary="Delete a role",
     *     tags={"Roles"},
     *     @OA\Response(response=200, description="Role deleted")
     * )
     */
    public function delete(int $id, RoleRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE');

        $role = $repository->find($id);
        if (!$role) {
            return $this->json(['error' => 'Rol no encontrado'], 404);
        }

        // PROTECTION: Prevent deleting the Super Admin role
        if ($role->getName() === 'ROLE_SUPER_ADMIN') {
            return $this->json(['error' => 'El rol Super Admin es un rol del sistema y no puede ser eliminado.'], 403);
        }

        // Check if users are using this role
        if (count($role->getUsers()) > 0) {
            return $this->json(['error' => 'No se puede eliminar un rol que tiene usuarios asignados.'], 400);
        }

        $em->remove($role);
        $em->flush();

        return $this->json(['message' => 'Rol eliminado']);
    }

    /**
     * @Route("/permissions", methods={"GET"}, priority=2)
     * @OA\Get(
     *     path="/api/roles/permissions",
     *     summary="List all available permissions in the system",
     *     tags={"Roles"},
     *     @OA\Response(response=200, description="List of permissions")
     * )
     */
    public function listPermissions(PermissionRepository $repository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE');

        $permissions = $repository->findAll();
        $data = [];

        foreach ($permissions as $p) {
            $data[] = [
                'id' => $p->getId(),
                'name' => $p->getName()
            ];
        }

        return $this->json($data);
    }
}
