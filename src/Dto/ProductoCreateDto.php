<?php
// src/Dto/ProductoCreateDto.php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ProductoCreateDto
{
    #[Assert\NotBlank(message: "El nombre no puede estar vacío.")]
    #[Assert\Length(min: 3)]
    public ?string $nombre = null;

    public ?string $serial = null;
    public ?string $marca = null;
    public ?string $modelo = null;
    
    #[Assert\NotBlank(message: "La sede es requerida.")]
    public ?string $sede = null;

    #[Assert\NotBlank(message: "La oficina es requerida.")]
    public ?string $oficina = null;

    public ?string $detalle = null;
    public ?string $ubicacion = null;
}