<?php
// bootstrap_roles.php
// Run this via: php bootstrap_roles.php email [name] [surname] [password]
// Example: php bootstrap_roles.php admin@intranet.com Super Admin admin

use App\Entity\Role;
use App\Entity\User;
use App\Entity\Permission;

require_once __DIR__ . '/vendor/autoload.php';

// Load .env variables
(new \Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__ . '/.env');

$kernel = new \App\Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();

$email = $argv[1] ?? null;

if (!$email) {
    echo "Usage: php bootstrap_roles.php your_email@example.com\n";
    exit(1);
}

// 1. Create Super Admin Role
$role = $em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);
if (!$role) {
    $role = new Role();
    $role->setTitle('Super Admin'); // This sets both title and name (ROLE_SUPER_ADMIN)
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
    'USER_VIEW',
    'USER_EDIT',
    'USER_DELETE',
    'USER_VIEW_DELETED',
    'USER_MANAGE',
    'USER_EDIT_ROLES',
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
    }

    // Assign permission to Super Admin
    if (!$role->getPermissions()->contains($p)) {
        $role->addPermission($p);
    }
}

// 3. Create or Update User
$user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
$isNew = false;

if (!$user) {
    $user = new User();
    $user->setEmail($email);
    $em->persist($user);
    $isNew = true;
    echo "Creating new user: $email\n";
} else {
    echo "Updating existing user: $email\n";
}

// Update name and surname
$user->setName($argv[2] ?? ($isNew ? 'Super' : $user->getName()));
$user->setSurname($argv[3] ?? ($isNew ? 'Admin' : $user->getSurname()));
$user->setDeletedAt(null); // Ensure user is active

// Hash and set password
$password = $argv[4] ?? ($isNew ? 'admin' : null);
if ($password) {
    $encoder = $container->get('security.password_encoder');
    $hashedPassword = $encoder->encodePassword($user, $password);
    $user->setPassword($hashedPassword);
    echo "Password set/updated for $email\n";
}

$user->setRole($role);
echo "Assigned ROLE_SUPER_ADMIN role.\n";

$em->flush();
echo "Done! Your database is now populated and your user is a Super Admin.\n";
