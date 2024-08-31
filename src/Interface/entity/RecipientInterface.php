<?php
namespace App\Interface\entity;

interface RecipientInterface
{
    public function getFullName(): string;
    public function getPhone(): ?string;
   
}
