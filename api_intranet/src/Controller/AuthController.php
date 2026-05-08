<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Repository\UserRepository;
use OpenApi\Annotations as OA;

class AuthController extends AbstractController
{
    /**
     * @Route("/api/register", name="api_register", methods={"POST"})
     * @OA\Post(
     * path="/api/register",
     * summary="Register a new user into the database",
     * tags={"Autenticación"},
     * @OA\RequestBody(
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="email", type="string", example="admin@intranet.com"),
     * @OA\Property(property="name", type="string", example="Juan"),
     * @OA\Property(property="surname", type="string", example="Pérez"),
     * @OA\Property(property="password", type="string", example="mi_password_seguro")
     * )
     * ),
     * @OA\Response(response=201, description="User Creation successful"),
     * @OA\Response(response=400, description="Validation error")
     * )
     */
    public function register(Request $request, UserPasswordEncoderInterface $encoder, EntityManagerInterface $em, ValidatorInterface $validator, UserRepository $userRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !is_array($data)) {
            return new JsonResponse(['error' => 'Datos inválidos o JSON malformado'], 400);
        }

        // Set Validation
        if (!isset($data['email']) || !isset($data['password']) || !isset($data['name']) || !isset($data['surname'])) {
            return new JsonResponse(['error' => 'Email, nombre, apellido y password son obligatorios'], 400);
        }

        // Create user
        $user = new User();
        $user->setEmail($data['email']);
        $user->setName($data['name']);
        $user->setSurname($data['surname']);

        // Hash Password
        $hashedPassword = $encoder->encodePassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        // Password hashed and set. Role will remain NULL unless explicitly assigned 
        // via the database-driven Role entity system.

        // Validate user (checks constraints like UniqueEntity and Assert\Email)
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['error' => implode(' ', $errorMessages)], 400);
        }

        // Save into the database
        try {
            $em->persist($user);
            $em->flush();
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error al guardar el usuario en la base de datos. Verifique que los datos sean correctos.'], 500);
        }

        return new JsonResponse(['message' => 'Usuario creado exitosamente'], 201);
    }

    /**
     * @Route("/api/me", name="api_me", methods={"GET"})
     */
    //Returns user info
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'No autenticado'], 401);
        }

        return new JsonResponse([
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'surname' => $user->getSurname(),
            'rol' => $user->getRole() ? $user->getRole()->getName() : 'ROLE_USER',
            'roles' => $user->getRoles(),
        ]);
    }
}
