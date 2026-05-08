<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

//Receives and validates the json
class UserUpdateDto
{
    /** @Assert\Email */
    public $email;

    /** @Assert\Type("string") */
    public $role;

    /** @Assert\Type("string") */
    public $name;

    /** @Assert\Type("string") */
    public $surname;

    public $password;

    public function updateEntity($user, $encoder, $canChangeRoles, $roleRepository): void
    {
        if ($this->email) {
            $user->setEmail($this->email);
        }

        if ($this->password) {
            $user->setPassword($encoder->encodePassword($user, $this->password));
        }

        if ($this->name) {
            $user->setName($this->name);
        }

        if ($this->surname) {
            $user->setSurname($this->surname);
        }

        // Only assign the role if it exists in the database
        if ($this->role && $canChangeRoles) {
            $role = $roleRepository->findOneBy(['name' => $this->role]);
            if ($role) {
                $user->setRole($role);
            }
        }
    }
}
