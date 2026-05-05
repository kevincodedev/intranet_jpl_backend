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
     *     @OA\Response(response=200, description="List of users"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(UserRepository $repository): JsonResponse
    {
        $hasAdminAccess = $this->isGranted('ROLE_ADMIN');
        
        if ($hasAdminAccess) {
            $users = $repository->findAll();
        } else {
            $users = $repository->findBy(['deletedAt' => null]);
        }

        $data = [];

        foreach ($users as $user) {
            $userArray = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'surname' => $user->getSurname(),
                'roles' => $user->getRoles(),
            ];

            if ($hasAdminAccess) {
                $userArray['isActive'] = $user->isActive();
                $userArray['deletedAt'] = $user->getDeletedAt() ? $user->getDeletedAt()->format('Y-m-d H:i:s') : null;
            }

            $data[] = $userArray;
        }

        return $this->json($data);
    }

    /**
     * @Route("/{id}", methods={"GET"})
     * @OA\Get(
     *     path="/api/users/{id}",
     *     summary="Get details of a single user",
     *     tags={"Usuarios"},
     *     @OA\Parameter(name="id", in="path", description="User ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User details"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function show(int $id, UserRepository $repository): JsonResponse
    {
        $user = $repository->find($id);

        if (!$user) {
            return $this->json(['error' => 'Usuario no encontrado'], 404);
        }

        // Hide deleted users from non-admins
        if (!$user->isActive() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Usuario no encontrado'], 404);
        }


        // Return ALL attributes for the profile view
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'surname' => $user->getSurname(),
            'roles' => $user->getRoles(),
        ]);
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
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string"), example={"ROLE_ADMIN"}),
     *             @OA\Property(property="password", type="string", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="User updated successfully"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=400, description="Validation error")
     * )
     */
    public function update(int $id, Request $request, UserRepository $repository, EntityManagerInterface $em, UserPasswordEncoderInterface $encoder, ValidatorInterface $validator): JsonResponse
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
        $dto->roles = $data['roles'] ?? null;
        $dto->password = $data['password'] ?? null;

        // Validates data
        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], 400);
        }

        // Checks if user has permission to edit an user role, if not, disallows
        $canEditRoles = $this->isGranted('USER_EDIT_ROLES', $user);
        $isTryingToGrantSuper = $dto->roles && in_array('ROLE_SUPER_ADMIN', $dto->roles);
        // Calculate if a role change to super user is allowed
        $canChangeRoles = $canEditRoles && (!$isTryingToGrantSuper || $this->isGranted('ROLE_SUPER_ADMIN'));

        // Checks if user has permission to change roles of another
        if ($dto->roles !== null && !$canChangeRoles) {
            return $this->json([
                'error' => 'No tienes permisos para modificar los roles de este usuario o asignar el rango solicitado.'
            ], 403);
        }

        //updates DB with values
        $dto->updateEntity($user, $encoder, $canChangeRoles);
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

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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

