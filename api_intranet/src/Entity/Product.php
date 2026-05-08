<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=ProductRepository::class)
 * @UniqueEntity(fields={"serial"}, message="Este número de serial ya está registrado.")
 */
class Product
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank(message="El nombre es obligatorio")
     * @Assert\Length(max=255, maxMessage="El nombre no puede tener más de {{ limit }} caracteres")
     */
    private $nombre;

    /**
     * @ORM\Column(type="string", length=100)
     * @Assert\NotBlank(message="La categoría es obligatoria")
     * @Assert\Length(max=100, maxMessage="La categoría no puede tener más de {{ limit }} caracteres")
     */
    private $categoria;

    /**
     * @ORM\Column(type="string", length=100)
     * @Assert\NotBlank(message="La marca es obligatoria")
     * @Assert\Length(max=100, maxMessage="La marca no puede tener más de {{ limit }} caracteres")
     */
    private $marca;

    /**
     * @ORM\Column(type="string", length=100)
     * @Assert\NotBlank(message="El modelo es obligatorio")
     * @Assert\Length(max=100, maxMessage="El modelo no puede tener más de {{ limit }} caracteres")
     */
    private $modelo;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $caracteristicas;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     * @Assert\Type("string")
     * @Assert\Regex(
     *     pattern="/\d/",
     *     match=false,
     *     message="El color no puede contener números"
     * )
     */
    private $color;

    /**
     * @ORM\Column(type="string", length=150, nullable=true, unique=true)
     * @Assert\Length(max=150, maxMessage="El serial no puede tener más de {{ limit }} caracteres")
     */
    private $serial;

    /**
     * @ORM\Column(type="integer")
     * @Assert\NotBlank(message="La cantidad es obligatoria")
     * @Assert\PositiveOrZero(message="La cantidad no puede ser negativa")
     */
    private $cantidad = 0;

    /**
     * @ORM\Column(type="string", length=150)
     * @Assert\NotBlank(message="La condición es obligatoria")
     * @Assert\Length(max=150, maxMessage="La condición no puede tener más de {{ limit }} caracteres")
     */
    private $condicion;

    /**
     * @ORM\Column(type="string", length=150)
     * @Assert\NotBlank(message="La locación es obligatoria")
     * @Assert\Length(max=150, maxMessage="La locación no puede tener más de {{ limit }} caracteres")
     */
    private $locacion;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $deletedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): self
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getCategoria(): ?string
    {
        return $this->categoria;
    }

    public function setCategoria(string $categoria): self
    {
        $this->categoria = $categoria;
        return $this;
    }

    public function getMarca(): ?string
    {
        return $this->marca;
    }

    public function setMarca(string $marca): self
    {
        $this->marca = $marca;
        return $this;
    }

    public function getModelo(): ?string
    {
        return $this->modelo;
    }

    public function setModelo(string $modelo): self
    {
        $this->modelo = $modelo;
        return $this;
    }

    public function getCaracteristicas(): ?string
    {
        return $this->caracteristicas;
    }

    public function setCaracteristicas(?string $caracteristicas): self
    {
        $this->caracteristicas = $caracteristicas;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function getSerial(): ?string
    {
        return $this->serial;
    }

    public function setSerial(?string $serial): self
    {
        $this->serial = $serial;
        return $this;
    }

    public function getCantidad(): ?int
    {
        return $this->cantidad;
    }

    public function setCantidad(int $cantidad): self
    {
        $this->cantidad = $cantidad;
        return $this;
    }

    public function getCondicion(): ?string
    {
        return $this->condicion;
    }

    public function setCondicion(string $condicion): self
    {
        $this->condicion = $condicion;
        return $this;
    }

    public function getLocacion(): ?string
    {
        return $this->locacion;
    }

    public function setLocacion(string $locacion): self
    {
        $this->locacion = $locacion;
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
