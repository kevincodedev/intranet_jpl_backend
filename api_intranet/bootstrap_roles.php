<?php
// bootstrap_roles.php
// Run this via: php bootstrap_roles.php YOUR_EMAIL@example.com

use App\Entity\Role;
use App\Entity\User;
use App\Entity\Permission;

require_once __DIR__ . '/vendor/autoload.php';

// Load .env variables
(new \Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__ . '/.env');

$kernel = new \App\Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$em = $container->get('doctrine.orm.entity_manager');

$email = $argv[1] ?? null;

if (!$email) {
    echo "Usage: php bootstrap_roles.php your_email@example.com\n";
    exit(1);
}

// 1. Create Super Admin Role
$role = $em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);
if (!$role) {
    $role = new Role();
    $role->setName('ROLE_SUPER_ADMIN');
    $em->persist($role);
    echo "Created ROLE_SUPER_ADMIN\n";
}

// 2. Full List of Permissions
$perms = [
    // Products
    'PRODUCT_VIEW_DELETED',
    'PRODUCT_CREATE',
    'PRODUCT_EDIT',
    'PRODUCT_DELETE',
    // Users
    'USER_LIST',
    'USER_VIEW_DELETED',
    'USER_MANAGE',
    // System
    'ROLE_MANAGE'
];

foreach ($perms as $pName) {
    $p = $em->getRepository(Permission::class)->findOneBy(['name' => $pName]);
    if (!$p) {
        $p = new Permission();
        $p->setName($pName);
        $em->persist($p);
        echo "Created Permission: $pName\n";
    } else {
        $p = $em->getRepository(Permission::class)->findOneBy(['name' => $pName]);
    }

    // Assign permission to Super Admin
    if (!$role->getPermissions()->contains($p)) {
        $role->addPermission($p);
    }
}

// 3. Assign to your user
$user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
if ($user) {
    $user->setRole($role);
    echo "Assigned ROLE_SUPER_ADMIN to user: $email\n";
} else {
    echo "User not found: $email\n";
}

$em->flush();
echo "Done! Your database is now populated and your user is a Super Admin.\n";
