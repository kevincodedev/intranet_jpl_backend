<?php
// src/Dto/ProductoUpdateDto.php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ProductoUpdateDto
{
    // 1. Quitamos #[Assert\Optional] y movemos las reglas directamente.
    // Usamos 'allowNull: true' en NotBlank para que si no envían el nombre (es null), no falle.
    #[Assert\NotBlank(allowNull: true, message: "El nombre no puede estar vacío.")]
    #[Assert\Length(min: 3)]
    public ?string $nombre = null;

    // 2. Si solo tenía Optional y ninguna otra regla, simplemente se quita el atributo.
    // Al ser ?string = null, ya es opcional por defecto.
    public ?string $serial = null;

    public ?string $marca = null;

    public ?string $modelo = null;
    
    #[Assert\NotBlank(allowNull: true, message: "La sede es requerida.")]
    public ?string $sede = null;

    #[Assert\NotBlank(allowNull: true, message: "La oficina es requerida.")]
    public ?string $oficina = null;

    public ?string $detalle = null;

    public ?string $ubicacion = null;
}