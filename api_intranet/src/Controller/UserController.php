<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Annotations as OA;

/**
 * @Route("/api/users")
 */
class UserController extends AbstractController
{
    /**
     * @Route("", methods={"GET"})
     * @OA\Get(
     *     path="/api/users",
     *     summary="Returns a list of all the users",
     *     tags={"Usuarios"},
     * @OA\Parameter(name="search", in="query", description="Search String", @OA\Schema(type="string")),
     * @OA\Parameter(name="role", in="query", description="Filter by role name (e.g. ROLE_ADMIN)", @OA\Schema(type="string")),
     * @OA\Parameter(name="limit", in="query", description="Page limit (10, 25, 50, 100)", @OA\Schema(type="integer", default=10)),
     * @OA\Parameter(name="page", in="query", description="Page Number", @OA\Schema(type="integer", default=1)),
     *     @OA\Response(response=200, description="List of users"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request, UserRepository $repository): JsonResponse
    {
        $this->denyAccessUnlessGranted('USER_VIEW');

        $search = $request->query->get('search', '');
        $role = $request->query->get('role');
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 25);

        // 2. Validate limit to prevent database stress
        if (!in_array($limit, [10, 25, 50, 100])) {
            $limit = 10;
        }
        // If they have permission to see deleted users, we don't filter them out
        $hasPermissionToSeeDeleted = $this->isGranted('USER_VIEW_DELETED');
        $result = $repository->searchAndPaginate($search, $page, $limit, $hasPermissionToSeeDeleted, $role);

        return $this->json($result);
    }

    /**
     * @Route("/{id}", methods={"GET"})
     * @OA\Get(
     *     path="/api/users/{id}",
     *     summary="Get details of a single user",
     *     tags={"Usuarios"},
     *     @OA\Parameter(name="id", in="path", description="User ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="User details",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="surname", type="string"),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="isActive", type="boolean", description="Only for admins"),
     *             @OA\Property(property="deletedAt", type="string", format="date-time", description="Only for admins")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function show(int $id, UserRepository $repository): JsonResponse
    {
        $user = $repository->find($id);

        if (!$user) {
            return $this->json(['error' => 'Usuario no encontrado'], 404);
        }

        // Authorization check: User can view themselves, or must have USER_VIEW permission
        if ($user !== $this->getUser() && !$this->isGranted('USER_VIEW')) {
            throw $this->createAccessDeniedException('No tienes permisos para ver otros usuarios.');
        }

        // Hide deleted users from users without view_deleted permission
        if (!$user->isActive() && !$this->isGranted('USER_VIEW_DELETED')) {
            return $this->json(['error' => 'Usuario no encontrado'], 404);
        }


        $userData = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'surname' => $user->getSurname(),
            'role' => $user->getRole() ? $user->getRole()->getName() : 'ROLE_USER',
        ];

        // Only show detailed permissions and status to admins or to the user themselves
        if ($this->isGranted('USER_VIEW_DELETED') || $user === $this->getUser()) {
            $userData['roles'] = $user->getRoles();
            $userData['isActive'] = $user->isActive();
            $userData['deletedAt'] = $user->getDeletedAt() ? $user->getDeletedAt()->format('Y-m-d H:i:s') : null;
        }

        return $this->json($userData);
    }

    /**
     * @Route("/{id}", methods={"PUT"})
     * @OA\Put(
     *     path="/api/users/{id}",
     *     summary="Updates the credentials or roles of an user",
     *     tags={"Usuarios"},
     *     @OA\Parameter(name="id", in="path", description="User ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="email", type="string", example="user@example.com"),
     *             @OA\Property(property="name", type="string", example="John"),
     *             @OA\Property(property="surname", type="string", example="Doe"),
     *             @OA\Property(property="rol", type="string", example="ROLE_ADMIN"),
     *             @OA\Property(property="password", type="string", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="User updated successfully"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=400, description="Validation error")
     * )
     */
    public function update(int $id, Request $request, UserRepository $repository, EntityManagerInterface $em, UserPasswordEncoderInterface $encoder, ValidatorInterface $validator, \App\Repository\RoleRepository $roleRepository): JsonResponse
    {
        $user = $repository->find($id);
        // If the user doesn't exist, throw error
        if (!$user) {
            return $this->json(['error' => 'Usuario no encontrado'], 404);
        }

        // Checks if the user is able to edit the target user, if not blocks access
        $this->denyAccessUnlessGranted('USER_EDIT', $user);
        // If allowed, turns the JSON into PHP
        $data = json_decode($request->getContent(), true);

        // Maps array into DTO
        $dto = new \App\Dto\UserUpdateDto();
        $dto->email = $data['email'] ?? null;
        $dto->name = $data['name'] ?? null;
        $dto->surname = $data['surname'] ?? null;
        $dto->role = $data['role'] ?? $data['rol'] ?? null;
        $dto->password = $data['password'] ?? null;

        // Validates data
        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], 400);
        }

        // Checks if user has permission to edit an user role, if not, disallows
        $canEditRoles = $this->isGranted('USER_EDIT_ROLES', $user);
        $isTryingToGrantSuper = $dto->role === 'ROLE_SUPER_ADMIN';
        // Calculate if a role change to super user is allowed
        $canChangeRoles = $canEditRoles && (!$isTryingToGrantSuper || $this->isGranted('ROLE_SUPER_ADMIN'));

        // Checks if user has permission to change roles of another
        if ($dto->role !== null) {
            if (!$canChangeRoles) {
                return $this->json(['error' => 'No tienes permisos para modificar los roles.'], 403);
            }

            // Check if the role actually exists
            $roleExists = $roleRepository->findOneBy(['name' => $dto->role]);
            if (!$roleExists && $dto->role !== 'ROLE_USER') {
                return $this->json(['error' => sprintf('El rol "%s" no existe. Debe crearlo primero.', $dto->role)], 400);
            }
        }

        //updates DB with values
        $dto->updateEntity($user, $encoder, $canChangeRoles, $roleRepository);
        $em->flush();

        return $this->json(['message' => 'Usuario actualizado correctamente']);
    }

    /**
     * @Route("/{id}", methods={"DELETE"})
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     summary="Delete a user",
     *     tags={"Usuarios"},
     *     @OA\Parameter(name="id", in="path", description="User ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User deleted successfully"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function delete(int $id, UserRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $user = $repository->find($id);
        // If the user doesn't exist, throw error
        if (!$user) {
            return $this->json(['error' => 'Usuario no encontrado'], 404);
        }

        // Authorization check using Voter
        $this->denyAccessUnlessGranted('USER_DELETE', $user);

        // Soft delete
        $user->setDeletedAt(new \DateTime());
        $em->flush();

        return $this->json(['message' => 'Usuario desactivado correctamente (borrado lógico)']);
    }

    /**
     * @Route("/{id}/toggle-active", methods={"POST"})
     * @OA\Post(
     *     path="/api/users/{id}/toggle-active",
     *     summary="Toggle user active status (soft delete/restore)",
     *     tags={"Usuarios"},
     *     @OA\Parameter(name="id", in="path", description="User ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Status toggled successfully"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function toggleActive(int $id, UserRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $user = $repository->find($id);
        if (!$user) {
            return $this->json(['error' => 'Usuario no encontrado'], 404);
        }

        $this->denyAccessUnlessGranted('USER_DELETE', $user);

        if ($user->isActive()) {
            $user->setDeletedAt(new \DateTime());
        } else {
            $user->setDeletedAt(null);
        }

        $em->flush();

        return $this->json([
            'message' => $user->isActive() ? 'Usuario activado' : 'Usuario desactivado',
            'isActive' => $user->isActive()
        ]);
    }
}
