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
     *             @OA\Property(property="name", type="string", example="SECRETARIA")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Role created")
     * )
     */
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE');

        $data = json_decode($request->getContent(), true);
        if (empty($data['name'])) {
            return $this->json(['error' => 'El nombre es obligatorio'], 400);
        }

        $role = new Role();
        $role->setName($data['name']);

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
     *             @OA\Property(property="name", type="string"),
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

        // Update Name
        if (!empty($data['name'])) {
            $role->setName($data['name']);
        }

        // Sync Permissions
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            // Clear current permissions
            foreach ($role->getPermissions() as $p) {
                $role->removePermission($p);
            }

            // Add new ones
            foreach ($data['permissions'] as $permName) {
                $permission = $permRepo->findOneBy(['name' => $permName]);
                if (!$permission) {
                    $permission = new Permission();
                    $permission->setName($permName);
                    $em->persist($permission);
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
