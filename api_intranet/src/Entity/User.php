<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 * @ORM\Table(name="`user`")
 * @UniqueEntity(fields={"email"}, message="Este correo ya está registrado.")
 */
class User implements UserInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     * @Assert\NotBlank(message="El email es obligatorio")
     * @Assert\Email(message="El email '{{ value }}' no es un correo válido.")
     * @Assert\Length(max=180, maxMessage="El email no puede tener más de {{ limit }} caracteres")
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=100)
     * @Assert\NotBlank(message="El nombre es obligatorio")
     * @Assert\Length(max=100, maxMessage="El nombre no puede tener más de {{ limit }} caracteres")
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=100)
     * @Assert\NotBlank(message="El apellido es obligatorio")
     * @Assert\Length(max=100, maxMessage="El apellido no puede tener más de {{ limit }} caracteres")
     */
    private $surname;

    /**
     * @ORM\ManyToOne(targetEntity=Role::class, inversedBy="users")
     * @ORM\JoinColumn(name="role_id", referencedColumnName="id", nullable=true)
     */
    private $role;

    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     */
    private $password;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $deletedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSurname(): string
    {
        return $this->surname;
    }

    public function setSurname(string $surname): self
    {
        $this->surname = $surname;
        return $this;
    }

    public function getDisplayName(): string //Name to be shown on Site
    {
        return trim($this->name . ' ' . $this->surname);
    }

    public function getUsername(): string //Symfony requirement, using email
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = [];

        if ($this->role) {
            $roles[] = $this->role->getName();
            foreach ($this->role->getPermissions() as $permission) {
                $roles[] = $permission->getName();
            }
        }

        return array_unique($roles);
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Required by Symfony
     */
    public function setRoles(array $roles): self
    {
        return $this;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void {}

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function isActive(): bool //checks for soft delete
    {
        return $this->deletedAt === null;
    }
}
