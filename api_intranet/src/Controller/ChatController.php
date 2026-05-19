<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\ChatMessage;
use App\Repository\UserRepository;
use App\Repository\ConversationRepository;
use App\Repository\ChatMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use OpenApi\Annotations as OA;

/**
 * @Route("/api/chat", name="api_chat_")
 */
class ChatController extends AbstractController
{
    /**
     * Creates a new conversation (private or group).
     * 
     * @Route("/conversations", name="create_conversation", methods={"POST"})
     * 
     * @OA\Post(
     *     path="/api/chat/conversations",
     *     summary="Crea una nueva conversación (privada o grupal)",
     *     tags={"Mensajeria"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="type", type="string", example="private", description="Tipo de chat: private o group"),
     *             @OA\Property(property="name", type="string", nullable=true, example="General IT", description="Nombre del chat grupal"),
     *             @OA\Property(property="participantIds", type="array", @OA\Items(type="integer"), example={2}, description="Lista de IDs de usuarios a incluir en la conversación")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Conversación creada exitosamente"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="La conversación ya existía, devuelve el ID de la conversación existente"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Petición incorrecta (p.ej., falta el ID del destinatario para chat privado)"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Destinatario no encontrado"
     *     )
     * )
     */
    public function createConversation(Request $request, UserRepository $userRepository, ConversationRepository $convRepo, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $type = $data['type'] ?? 'private';
        $name = $data['name'] ?? null;
        $participantIds = $data['participantIds'] ?? [];

        if ($type === 'private') {
            if (empty($participantIds)) {
                return $this->json(['error' => 'Recipient ID is required for private chat'], 400);
            }
            $recipientId = $participantIds[0];

            // Check if conversation already exists
            $existing = $convRepo->findPrivateConversationBetweenUsers($currentUser->getId(), $recipientId);
            if ($existing) {
                return $this->json(['id' => $existing->getId(), 'message' => 'Conversation already exists'], 200);
            }

            $recipient = $userRepository->find($recipientId);
            if (!$recipient) {
                return $this->json(['error' => 'Recipient not found'], 404);
            }

            $conversation = new Conversation();
            $conversation->setType('private');

            $p1 = new ConversationParticipant();
            $p1->setUser($currentUser);
            $p1->setRole('member');
            $conversation->addParticipant($p1);

            $p2 = new ConversationParticipant();
            $p2->setUser($recipient);
            $p2->setRole('member');
            $conversation->addParticipant($p2);

            $em->persist($conversation);
            $em->persist($p1);
            $em->persist($p2);
        } else {
            // Group chat
            $conversation = new Conversation();
            $conversation->setType('group');
            $conversation->setName($name ?: 'Group Chat');

            $pSelf = new ConversationParticipant();
            $pSelf->setUser($currentUser);
            $pSelf->setRole('admin');
            $conversation->addParticipant($pSelf);
            $em->persist($pSelf);

            foreach ($participantIds as $pId) {
                $user = $userRepository->find($pId);
                if ($user) {
                    $p = new ConversationParticipant();
                    $p->setUser($user);
                    $p->setRole('member');
                    $conversation->addParticipant($p);
                    $em->persist($p);
                }
            }
            $em->persist($conversation);
        }

        $em->flush();

        return $this->json([
            'id' => $conversation->getId(),
            'type' => $conversation->getType(),
            'name' => $conversation->getName()
        ], 201);
    }

    /**
     * Lists all conversations for the authenticated user.
     * 
     * @Route("/conversations", name="list_conversations", methods={"GET"})
     * 
     * @OA\Get(
     *     path="/api/chat/conversations",
     *     summary="Lista todas las conversaciones del usuario autenticado",
     *     tags={"Mensajeria"},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de conversaciones obtenida exitosamente"
     *     )
     * )
     */
    public function listConversations(ConversationRepository $convRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $conversations = $convRepo->findAllForUser($user->getId());

        $data = [];
        foreach ($conversations as $conv) {
            $participants = [];
            foreach ($conv->getParticipants() as $p) {
                if ($p->getUser()->getId() !== $user->getId() || $conv->getType() === 'group') {
                    $participants[] = [
                        'id' => $p->getUser()->getId(),
                        'name' => $p->getUser()->getDisplayName()
                    ];
                }
            }

            $data[] = [
                'id' => $conv->getId(),
                'type' => $conv->getType(),
                'name' => $conv->getName(),
                'lastUpdatedAt' => $conv->getUpdatedAt()->format('c'),
                'participants' => $participants
            ];
        }

        return $this->json($data);
    }

    /**
     * Send a real-time message to a conversation.
     * 
     * @Route("/conversations/{id}/messages", name="send_message", methods={"POST"})
     * 
     * @OA\Post(
     *     path="/api/chat/conversations/{id}/messages",
     *     summary="Envía un mensaje en tiempo real a una conversación",
     *     tags={"Mensajeria"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la conversación",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Hola grupo, ¿cómo están?")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Mensaje enviado y publicado mediante Mercure"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="El contenido del mensaje no puede estar vacío"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="El usuario no participa en esta conversación"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Conversación no encontrada"
     *     )
     * )
     */
    public function sendMessage(int $id, Request $request, HubInterface $hub, EntityManagerInterface $em, ConversationRepository $convRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $conversation = $convRepo->find($id);

        if (!$conversation) {
            return $this->json(['error' => 'Conversation not found'], 404);
        }

        // Verify participation
        $isParticipant = false;
        foreach ($conversation->getParticipants() as $p) {
            if ($p->getUser()->getId() === $user->getId()) {
                $isParticipant = true;
                break;
            }
        }

        if (!$isParticipant) {
            return $this->json(['error' => 'You are not a participant in this conversation'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $content = $data['message'] ?? '';

        if (empty($content)) {
            return $this->json(['error' => 'Message content cannot be empty'], 400);
        }

        $chatMessage = new ChatMessage();
        $chatMessage->setContent($content);
        $chatMessage->setSender($user);
        $chatMessage->setConversation($conversation);

        $conversation->setUpdatedAt(new \DateTime());

        // Restore/unhide conversation for any participant who deleted/hid it previously
        foreach ($conversation->getParticipants() as $p) {
            if ($p->getDeletedAt() !== null) {
                $p->setDeletedAt(null);
            }
        }

        $em->persist($chatMessage);
        $em->flush();

        $payload = [
            'id' => $chatMessage->getId(),
            'conversationId' => $conversation->getId(),
            'senderId' => $user->getId(),
            'senderName' => $user->getDisplayName(),
            'message' => $content,
            'timestamp' => $chatMessage->getCreatedAt()->format('c')
        ];

        $update = new Update(
            "conversations/{$conversation->getId()}",
            json_encode($payload),
            true
        );

        try {
            $hub->publish($update);
        } catch (\Exception $e) {
            error_log('Mercure publish failed: ' . $e->getMessage());
        }

        return $this->json($payload, 201);
    }

    /**
     * Get message history for a conversation.
     * 
     * @Route("/conversations/{id}/messages", name="get_history", methods={"GET"})
     * 
     * @OA\Get(
     *     path="/api/chat/conversations/{id}/messages",
     *     summary="Obtiene el historial de mensajes de una conversación",
     *     tags={"Mensajeria"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la conversación",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de mensajes de la conversación en orden cronológico"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Acceso denegado (el usuario no es participante ni administrador)"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Conversación no encontrada"
     *     )
     * )
     */
    public function getHistory(int $id, ChatMessageRepository $repository, ConversationRepository $convRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $conversation = $convRepo->find($id);

        if (!$conversation) {
            return $this->json(['error' => 'Conversation not found'], 404);
        }

        // Verify participation
        $isParticipant = false;
        foreach ($conversation->getParticipants() as $p) {
            if ($p->getUser()->getId() === $user->getId()) {
                $isParticipant = true;
                break;
            }
        }

        if (!$isParticipant) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $messages = $repository->findBy(
            ['conversation' => $conversation, 'deletedAt' => null],
            ['createdAt' => 'ASC'],
            50
        );

        $data = [];
        foreach ($messages as $msg) {
            $data[] = [
                'id' => $msg->getId(),
                'senderId' => $msg->getSender()->getId(),
                'senderName' => $msg->getSender()->getDisplayName(),
                'message' => $msg->getContent(),
                'timestamp' => $msg->getCreatedAt()->format('c'),
                'updatedAt' => $msg->getUpdatedAt() ? $msg->getUpdatedAt()->format('c') : null
            ];
        }

        return $this->json($data);
    }

    /**
     * Edit a chat message.
     * 
     * @Route("/messages/{id}", name="update_message", methods={"PUT"})
     * 
     * @OA\Put(
     *     path="/api/chat/messages/{id}",
     *     summary="Edita un mensaje de chat dentro de una ventana de 30 minutos",
     *     tags={"Mensajeria"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del mensaje a editar",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Mensaje editado y corregido")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mensaje editado correctamente"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="El contenido del mensaje no puede estar vacío"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado (no eres el emisor) o el periodo de edición de 30 minutos ha expirado"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Mensaje no encontrado"
     *     )
     * )
     */
    public function updateMessage(int $id, Request $request, ChatMessageRepository $repository, EntityManagerInterface $em, HubInterface $hub): JsonResponse
    {
        $chatMessage = $repository->find($id);

        if (!$chatMessage) {
            return $this->json(['error' => 'Message not found'], 404);
        }

        /** @var User $user */
        $user = $this->getUser();

        if ($chatMessage->getSender() !== $user) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $now = new \DateTime();
        $diff = $now->getTimestamp() - $chatMessage->getCreatedAt()->getTimestamp();
        if ($diff > (30 * 60)) {
            return $this->json(['error' => 'Edit time window expired'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $newMessage = $data['message'] ?? '';

        if (empty($newMessage)) {
            return $this->json(['error' => 'Message content cannot be empty'], 400);
        }

        $chatMessage->setContent($newMessage);
        $chatMessage->setUpdatedAt(new \DateTime());
        $em->flush();

        $payload = [
            'type' => 'message_updated',
            'id' => $id,
            'message' => $newMessage,
            'updatedAt' => $chatMessage->getUpdatedAt()->format('c')
        ];

        $update = new Update(
            "conversations/{$chatMessage->getConversation()->getId()}",
            json_encode($payload),
            true
        );

        try {
            $hub->publish($update);
        } catch (\Exception $e) {
            error_log('Mercure update failed: ' . $e->getMessage());
        }

        return $this->json(['status' => 'success']);
    }

    /**
     * Delete a chat message.
     * 
     * @Route("/messages/{id}", name="delete_message", methods={"DELETE"})
     * 
     * @OA\Delete(
     *     path="/api/chat/messages/{id}",
     *     summary="Elimina un mensaje de chat (soft delete)",
     *     tags={"Mensajeria"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del mensaje a eliminar",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mensaje eliminado correctamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado (no eres el emisor ni un administrador)"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Mensaje no encontrado"
     *     )
     * )
     */
    public function deleteMessage(int $id, ChatMessageRepository $repository, EntityManagerInterface $em, HubInterface $hub): JsonResponse
    {
        $chatMessage = $repository->find($id);

        if (!$chatMessage) {
            return $this->json(['error' => 'Message not found'], 404);
        }

        /** @var User $user */
        $user = $this->getUser();

        $conversation = $chatMessage->getConversation();
        $isParticipant = false;
        foreach ($conversation->getParticipants() as $p) {
            if ($p->getUser()->getId() === $user->getId()) {
                $isParticipant = true;
                break;
            }
        }

        $canDelete = ($chatMessage->getSender() === $user) || ($this->isGranted('ROLE_ADMIN') && $isParticipant);
        if (!$canDelete) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $payload = [
            'type' => 'message_deleted',
            'id' => $id
        ];

        $chatMessage->setDeletedAt(new \DateTime());
        $em->flush();

        try {
            $update = new Update(
                "conversations/{$chatMessage->getConversation()->getId()}",
                json_encode($payload),
                true
            );
            $hub->publish($update);
        } catch (\Exception $e) {
            error_log('Mercure delete failed: ' . $e->getMessage());
        }

        return $this->json(['status' => 'success']);
    }

    /**
     * Delete/Hide a conversation for the authenticated user.
     * 
     * @Route("/conversations/{id}", name="delete_conversation", methods={"DELETE"})
     * 
     * @OA\Delete(
     *     path="/api/chat/conversations/{id}",
     *     summary="Oculta o elimina una conversación para el usuario autenticado",
     *     tags={"Mensajeria"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la conversación a ocultar",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Conversación oculta exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado (no eres participante)"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Conversación no encontrada"
     *     )
     * )
     */
    public function deleteConversation(int $id, ConversationRepository $convRepo, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $conversation = $convRepo->find($id);

        if (!$conversation) {
            return $this->json(['error' => 'Conversation not found'], 404);
        }

        $participant = null;
        foreach ($conversation->getParticipants() as $p) {
            if ($p->getUser()->getId() === $user->getId()) {
                $participant = $p;
                break;
            }
        }

        if (!$participant) {
            return $this->json(['error' => 'You are not a participant in this conversation'], 403);
        }

        $participant->setDeletedAt(new \DateTime());
        $em->flush();

        return $this->json(['status' => 'success', 'message' => 'Conversation hidden successfully']);
    }

    /**
     * Add one or multiple participants to a group conversation.
     * 
     * @Route("/conversations/{id}/participants", name="add_participant", methods={"POST"})
     * 
     * @OA\Post(
     *     path="/api/chat/conversations/{id}/participants",
     *     summary="Agrega uno o varios miembros a una conversación grupal (solo administradores de la conversación)",
     *     tags={"Mensajeria"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la conversación grupal",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="userId", type="integer", example=3, description="ID del usuario a agregar (individual)"),
     *             @OA\Property(property="userIds", type="array", @OA\Items(type="integer"), example={2, 3}, description="Lista de IDs de usuarios a agregar en lote")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Miembros agregados exitosamente"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Petición incorrecta o falta de IDs"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado (debes ser administrador de la conversación)"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Conversación no encontrada"
     *     )
     * )
     */
    public function addParticipant(int $id, Request $request, ConversationRepository $convRepo, UserRepository $userRepository, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $conversation = $convRepo->find($id);

        if (!$conversation) {
            return $this->json(['error' => 'Conversation not found'], 404);
        }

        if ($conversation->getType() !== 'group') {
            return $this->json(['error' => 'Cannot add participants to a private conversation'], 400);
        }

        // Verify current user is group admin
        $isGroupAdmin = false;
        foreach ($conversation->getParticipants() as $p) {
            if ($p->getUser()->getId() === $currentUser->getId()) {
                if ($p->getRole() === 'admin') {
                    $isGroupAdmin = true;
                }
                break;
            }
        }

        if (!$isGroupAdmin) {
            return $this->json(['error' => 'Only conversation admins can add members'], 403);
        }

        $data = json_decode($request->getContent(), true);
        
        $userIds = $data['userIds'] ?? [];
        if (isset($data['userId'])) {
            $userIds[] = $data['userId'];
        }
        $userIds = array_unique(array_filter($userIds));

        if (empty($userIds)) {
            return $this->json(['error' => 'User ID or User IDs are required'], 400);
        }

        $addedUsers = [];
        $failedUsers = [];

        foreach ($userIds as $userId) {
            $targetUser = $userRepository->find($userId);
            if (!$targetUser) {
                $failedUsers[] = ['userId' => $userId, 'error' => 'User not found'];
                continue;
            }

            // Check if already participant
            $alreadyParticipant = false;
            foreach ($conversation->getParticipants() as $p) {
                if ($p->getUser()->getId() === $targetUser->getId()) {
                    $alreadyParticipant = true;
                    // If they previously left or deleted, reactivate them
                    if ($p->getDeletedAt() !== null) {
                        $p->setDeletedAt(null);
                        $p->setJoinedAt(new \DateTime());
                        $p->setRole('member');
                        $addedUsers[] = ['userId' => $userId, 'status' => 'reactivated', 'name' => $targetUser->getDisplayName()];
                    } else {
                        $failedUsers[] = ['userId' => $userId, 'error' => 'User is already an active participant'];
                    }
                    break;
                }
            }

            if (!$alreadyParticipant) {
                $participant = new ConversationParticipant();
                $participant->setUser($targetUser);
                $participant->setRole('member');
                $conversation->addParticipant($participant);
                $em->persist($participant);
                
                $addedUsers[] = ['userId' => $userId, 'status' => 'added', 'name' => $targetUser->getDisplayName()];
            }
        }

        $em->flush();

        return $this->json([
            'status' => 'success',
            'added' => $addedUsers,
            'failed' => $failedUsers
        ]);
    }

    /**
     * Kick a participant from a group conversation.
     * 
     * @Route("/conversations/{id}/participants/{userId}", name="kick_participant", methods={"DELETE"})
     * 
     * @OA\Delete(
     *     path="/api/chat/conversations/{id}/participants/{userId}",
     *     summary="Expulsa un miembro de una conversación grupal",
     *     tags={"Mensajeria"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la conversación grupal",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         description="ID del usuario a expulsar",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Miembro expulsado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="No puedes expulsarte a ti mismo"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado (debes ser participante y tener rol administrador del sistema)"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Conversación o participante no encontrado"
     *     )
     * )
     */
    public function kickParticipant(int $id, int $userId, ConversationRepository $convRepo, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $conversation = $convRepo->find($id);

        if (!$conversation) {
            return $this->json(['error' => 'Conversation not found'], 404);
        }

        if ($conversation->getType() !== 'group') {
            return $this->json(['error' => 'Cannot kick participants from a private conversation'], 400);
        }

        if ($currentUser->getId() === $userId) {
            return $this->json(['error' => 'You cannot kick yourself. Please use the delete endpoint to leave the conversation'], 400);
        }

        // Verify current user is group admin
        $isGroupAdmin = false;
        foreach ($conversation->getParticipants() as $p) {
            if ($p->getUser()->getId() === $currentUser->getId()) {
                if ($p->getRole() === 'admin') {
                    $isGroupAdmin = true;
                }
                break;
            }
        }

        if (!$isGroupAdmin) {
            return $this->json(['error' => 'Only conversation admins can kick members'], 403);
        }

        $targetParticipant = null;
        foreach ($conversation->getParticipants() as $p) {
            if ($p->getUser()->getId() === $userId) {
                $targetParticipant = $p;
                break;
            }
        }

        if (!$targetParticipant) {
            return $this->json(['error' => 'Participant not found in this conversation'], 404);
        }

        $em->remove($targetParticipant);
        $em->flush();

        return $this->json(['status' => 'success', 'message' => 'User kicked from group successfully']);
    }

    /**
     * Leave a group conversation.
     * 
     * @Route("/conversations/{id}/leave", name="leave_conversation", methods={"POST"})
     * 
     * @OA\Post(
     *     path="/api/chat/conversations/{id}/leave",
     *     summary="Permite a un participante abandonar una conversación grupal voluntariamente",
     *     tags={"Mensajeria"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la conversación a abandonar",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Has abandonado la conversación exitosamente"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="No se puede abandonar una conversación privada"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado (no eres participante de esta conversación)"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Conversación no encontrada"
     *     )
     * )
     */
    public function leaveConversation(int $id, ConversationRepository $convRepo, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $conversation = $convRepo->find($id);

        if (!$conversation) {
            return $this->json(['error' => 'Conversation not found'], 404);
        }

        if ($conversation->getType() !== 'group') {
            return $this->json(['error' => 'Cannot leave a private conversation. Use delete endpoint to hide it'], 400);
        }

        $targetParticipant = null;
        foreach ($conversation->getParticipants() as $p) {
            if ($p->getUser()->getId() === $currentUser->getId()) {
                $targetParticipant = $p;
                break;
            }
        }

        if (!$targetParticipant) {
            return $this->json(['error' => 'You are not a participant in this conversation'], 403);
        }

        $em->remove($targetParticipant);
        $em->flush();

        return $this->json(['status' => 'success', 'message' => 'You have left the conversation successfully']);
    }
}
