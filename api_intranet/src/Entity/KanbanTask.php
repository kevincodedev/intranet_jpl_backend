<?php

namespace App\Entity;

use App\Repository\KanbanTaskRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=KanbanTaskRepository::class)
 * @ORM\HasLifecycleCallbacks
 */
class KanbanTask
{
    // Status Constants
    const STATUS_BACKLOG = 'En espera';
    const STATUS_TODO = 'Por Hacer';
    const STATUS_IN_PROGRESS = 'En Progreso';
    const STATUS_COMPLETE = 'Completado';

    // Importance Constants
    const IMPORTANCE_LOW = 'baja';
    const IMPORTANCE_MEDIUM = 'mediana';
    const IMPORTANCE_HIGH = 'alta';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Groups({"kanban:read"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank(message="El título es obligatorio")
     * @Groups({"kanban:read"})
     */
    private $title;

    /**
     * @ORM\Column(type="string", length=100)
     * @Assert\NotBlank(message="La categoría es obligatoria")
     * @Groups({"kanban:read"})
     */
    private $category;

    /**
     * @ORM\Column(type="string", length=20)
     * @Assert\Choice(choices={self::IMPORTANCE_LOW, self::IMPORTANCE_MEDIUM, self::IMPORTANCE_HIGH})
     * @Groups({"kanban:read"})
     */
    private $importance = self::IMPORTANCE_MEDIUM;

    /**
     * @ORM\Column(type="string", length=50)
     * @Assert\Choice(choices={self::STATUS_BACKLOG, self::STATUS_TODO, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETE})
     * @Groups({"kanban:read"})
     */
    private $status = self::STATUS_BACKLOG;

    /**
     * @ORM\Column(type="json")
     * @Groups({"kanban:read"})
     */
    private $subtasks = [];

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"kanban:read"})
     */
    private $owner;

    /**
     * @ORM\Column(type="datetime")
     * @Groups({"kanban:read"})
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $deletedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getImportance(): ?string
    {
        return $this->importance;
    }

    public function setImportance(string $importance): self
    {
        $this->importance = $importance;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getSubtasks(): ?array
    {
        return $this->subtasks;
    }

    public function setSubtasks(array $subtasks): self
    {
        $this->subtasks = $subtasks;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
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

    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }
}
