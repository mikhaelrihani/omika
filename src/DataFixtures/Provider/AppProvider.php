<?php

namespace App\DataFixtures\Provider;

use Faker\Provider\Base;


class AppProvider extends Base
{

    //! TimeStamps property
    public function dateTimeImmutableBetween($startDate = '-30 years', $endDate = 'now')
    {
        $dateTime = $this->generator->dateTimeBetween($startDate, $endDate);
        return \DateTimeImmutable::createFromMutable($dateTime);
    }
    public function createTimeStamps(): array
    {
        $createdAt = $this->dateTimeImmutableBetween('-5 years', 'now');
        $updatedAt = $this->dateTimeImmutableBetween($createdAt->format('Y-m-d H:i:s'), 'now');
    
        return compact('createdAt', 'updatedAt');
    }
    

    //! Role property
    private $roles = [
        ["ROLE_USER"],
        ["ROLE_ADMIN"],
        ["ROLE_SUPER_ADMIN"]
    ];
    public function role(): array
    {
        return $this->roles[array_rand($this->roles)];
    }

    //! Status Property
    private $status = [
        "draft",
        "pending",
        "submitted",
        "approved",
    ];
    public function getOneRandomStatus(): string
    {
        return $this->status[array_rand($this->status)];
    }

    //! Business entity
    private $business = [
        "3Brasseurs",
        "FraisImport",
        "ProAPro",
        "Runlog",
        "Victoria",
        "MartinPecheur",
        "ArmementDesMascareignes",
        "SalaisonsdeBourbon"
    ];
    public function getOneRandombusiness(): string
    {
        return $this->business[array_rand($this->business)];
    }
    public function getBusinessList(): array
    {
        return $this->business;
    }

    //! Mimes Entity
    private $mimes = [
        "jpeg",
        "png",
        "gif",
        "bmp",
        "tiff",
        "webp",
        "svg",
        "x-icon"
    ];
    public function oneRandomMime(): string
    {
        return $this->mimes[array_rand($this->mimes)];
    }
    public function getMimelist(): array
    {
        return $this->mimes;
    }

}