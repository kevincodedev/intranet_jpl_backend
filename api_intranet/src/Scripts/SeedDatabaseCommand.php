<?php

namespace App\Scripts;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Entity\ChatMessage;
use App\Entity\KanbanTask;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * Seeds the database with a Super Admin and sample data for all tables.
 *
 * Usage:
 *   php bin/console app:seed-database
 *
 * Safe to re-run — existing records (matched by email / serial / title) are skipped.
 */
class SeedDatabaseCommand extends Command
{
    protected static $defaultName = 'app:seed-database';
    protected static $defaultDescription = 'Seeds the database with a Super Admin user and sample data for all tables.';

    private EntityManagerInterface $em;
    private UserPasswordEncoderInterface $encoder;

    public function __construct(EntityManagerInterface $em, UserPasswordEncoderInterface $encoder)
    {
        parent::__construct();
        $this->em = $em;
        $this->encoder = $encoder;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🌱  Intranet Database Seeder');

        // ── 1. USERS ──────────────────────────────────────────────────────────
        $io->section('Users');

        $usersData = [
            [
                'email'             => 'admin@intranet.com',
                'name'              => 'Administrador',
                'surname'           => 'Principal',
                'password'          => 'admin',
                'roles'             => ['ROLE_SUPER_ADMIN'],
                'mustChangePassword' => false,
            ],
            [
                'email'             => 'jlopez@intranet.com',
                'name'              => 'Juan',
                'surname'           => 'López',
                'password'          => '123',
                'roles'             => ['ROLE_ADMIN'],
                'mustChangePassword' => true,
            ],
            [
                'email'             => 'mgarcia@intranet.com',
                'name'              => 'María',
                'surname'           => 'García',
                'password'          => '123',
                'roles'             => ['ROLE_USER'],
                'mustChangePassword' => true,
            ],
            [
                'email'             => 'logistica@intranet.com',
                'name'              => 'Logística',
                'surname'           => 'Inventario',
                'password'          => '123',
                'roles'             => ['ROLE_LOGISTICS'],
                'mustChangePassword' => true,
            ],
        ];

        $userRepo  = $this->em->getRepository(User::class);
        $createdUsers = [];

        foreach ($usersData as $userData) {
            $existing = $userRepo->findOneBy(['email' => $userData['email']]);
            $user = $existing ?: new User();

            $user->setEmail($userData['email']);
            $user->setName($userData['name']);
            $user->setSurname($userData['surname']);
            $user->setRoles($userData['roles']);
            $user->setMustChangePassword($userData['mustChangePassword']);
            $user->setPassword($this->encoder->encodePassword($user, $userData['password']));

            $this->em->persist($user);
            $createdUsers[$userData['email']] = $user;

            if ($existing) {
                $io->text("  Updated: <info>{$userData['email']}</info>");
            } else {
                $io->text("  Created: <info>{$userData['email']}</info> [" . implode(', ', $userData['roles']) . "]");
            }
        }

        $this->em->flush();

        // Ensure we always have a reference to the super admin for relations
        $superAdmin = $createdUsers['admin@intranet.com'];

        // ── 2. PRODUCTS ───────────────────────────────────────────────────────
        $io->section('Products');

        $productsData = [
            [
                'nombre'          => 'Laptop Dell XPS 15',
                'categoria'       => 'Computadoras',
                'marca'           => 'Dell',
                'modelo'          => 'XPS 15 9530',
                'caracteristicas' => 'Intel Core i7, 16GB RAM, 512GB SSD, OLED 15.6"',
                'color'           => 'Plata',
                'serial'          => 'DXPS15-001',
                'condicion'       => 'Bueno',
                'locacion'        => 'Oficina A',
                'cantidad'        => 5,
                'empresa'         => 'JPL',
            ],
            [
                'nombre'          => 'Monitor LG UltraWide',
                'categoria'       => 'Monitores',
                'marca'           => 'LG',
                'modelo'          => '34WN80C-B',
                'caracteristicas' => '34" IPS, 3440x1440, 60Hz, USB-C',
                'color'           => 'Negro',
                'serial'          => 'LGUW34-002',
                'condicion'       => 'Excelente',
                'locacion'        => 'Sala de Reuniones',
                'cantidad'        => 10,
                'empresa'         => 'Pafar',
            ],
            [
                'nombre'          => 'Teclado Mecánico Logitech MX',
                'categoria'       => 'Periféricos',
                'marca'           => 'Logitech',
                'modelo'          => 'MX Keys S',
                'caracteristicas' => 'Teclado inalámbrico, retroiluminado, compatible multi-OS',
                'color'           => 'Grafito',
                'serial'          => 'LMXKS-003',
                'condicion'       => 'Nuevo',
                'locacion'        => 'Almacén',
                'cantidad'        => 20,
                'empresa'         => '3d3',
            ],
        ];

        $productRepo = $this->em->getRepository(Product::class);

        foreach ($productsData as $pd) {
            $existing = $productRepo->findOneBy(['serial' => $pd['serial']]);
            $product = $existing ?: new Product();

            $product->setNombre($pd['nombre']);
            $product->setCategoria($pd['categoria']);
            $product->setMarca($pd['marca']);
            $product->setModelo($pd['modelo']);
            $product->setCaracteristicas($pd['caracteristicas']);
            $product->setColor($pd['color']);
            $product->setSerial($pd['serial']);
            $product->setCondicion($pd['condicion']);
            $product->setLocacion($pd['locacion']);
            $product->setCantidad($pd['cantidad']);
            $product->setEmpresa($pd['empresa'] ?? null);

            $this->em->persist($product);

            if ($existing) {
                $io->text("  🔄 Updated: <info>{$pd['nombre']}</info> (serial: {$pd['serial']})");
            } else {
                $io->text("  ✅ Created: <info>{$pd['nombre']}</info> (serial: {$pd['serial']})");
            }
        }

        $this->em->flush();

        // ── 3. KANBAN TASKS ───────────────────────────────────────────────────
        $io->section('Kanban Tasks');

        $tasksData = [
            [
                'title'      => 'Configurar VPN corporativa',
                'category'   => 'IT',
                'importance' => KanbanTask::IMPORTANCE_HIGH,
                'status'     => KanbanTask::STATUS_IN_PROGRESS,
                'subTasks'   => [
                    ['title' => 'Instalar cliente OpenVPN', 'done' => true],
                    ['title' => 'Configurar certificados', 'done' => false],
                ],
            ],
            [
                'title'      => 'Inventario de equipos Q2',
                'category'   => 'Administración',
                'importance' => KanbanTask::IMPORTANCE_MEDIUM,
                'status'     => KanbanTask::STATUS_TODO,
                'subTasks'   => [
                    ['title' => 'Listar activos fijos', 'done' => false],
                    ['title' => 'Actualizar hoja de cálculo', 'done' => false],
                ],
            ],
            [
                'title'      => 'Actualizar política de contraseñas',
                'category'   => 'Seguridad',
                'importance' => KanbanTask::IMPORTANCE_HIGH,
                'status'     => KanbanTask::STATUS_BACKLOG,
                'subTasks'   => [],
            ],
        ];

        $taskRepo = $this->em->getRepository(KanbanTask::class);

        foreach ($tasksData as $td) {
            $existing = $taskRepo->findOneBy(['title' => $td['title']]);
            if ($existing) {
                $io->text("  Skipped (title exists): <comment>{$td['title']}</comment>");
                continue;
            }

            $task = new KanbanTask();
            $task->setTitle($td['title']);
            $task->setCategory($td['category']);
            $task->setImportance($td['importance']);
            $task->setStatus($td['status']);
            $task->setSubTasks($td['subTasks']);
            $task->setOwner($superAdmin);

            $this->em->persist($task);
            $io->text("  Created: <info>{$td['title']}</info> [{$td['status']}]");
        }

        $this->em->flush();

        // ── 4. CHAT MESSAGES ──────────────────────────────────────────────────
        $io->section('Chat Messages');

        $messagesData = [
            [
                'content'  => '¡Bienvenidos al canal de IT! Aquí pueden reportar incidencias y solicitar soporte técnico.',
                'category' => 'general',
                'topic'    => 'IT',
            ],
            [
                'content'  => 'Recordatorio: la reunión de planificación del Q2 es el lunes a las 9:00 AM en la sala principal.',
                'category' => 'general',
                'topic'    => 'Administración',
            ],
            [
                'content'  => 'Se ha actualizado el inventario de equipos. Por favor revisen el nuevo listado en el portal.',
                'category' => 'general',
                'topic'    => 'Inventario',
            ],
        ];

        foreach ($messagesData as $md) {
            $msg = new ChatMessage();
            $msg->setContent($md['content']);
            $msg->setCategory($md['category']);
            $msg->setTopic($md['topic']);
            $msg->setSender($superAdmin);

            $this->em->persist($msg);
            $io->text("  Created message in topic: <info>{$md['topic']}</info>");
        }

        $this->em->flush();

        // ── Summary ───────────────────────────────────────────────────────────
        $io->success([
            'Database seeded successfully!',
            '',
            '  Super Admin  →  admin@intranet.com  /  admin',
            '  Admin        →  jlopez@intranet.com  /  123',
            '  Logistics    →  logistica@intranet.com / 123',
            '  User         →  mgarcia@intranet.com  /  123',
            '  User         →  cperez@intranet.com   /  123',
        ]);

        return Command::SUCCESS;
    }
}
