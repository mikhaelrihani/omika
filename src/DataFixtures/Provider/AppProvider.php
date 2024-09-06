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

    //! Units Entity
    private $units = [
        "Kilogram"   => "kg",
        "Gram"       => "g",
        "Liter"      => "l",
        "Centiliter" => "cl",
        "Unit"       => "unit",
        "Piece"      => "piece",
    ];
    public function oneRandomUnit(): string
    {
        return array_rand($this->units);

    }
    public function getUnitList(): array
    {
        return $this->units;
    }

    //! ProductType Entity
    private $productTypes = [
        "surgelé",
        "frais",
        "sec",
        "autre"
    ];
    public function oneRandomProductType(): string
    {
        return $this->productTypes[array_rand($this->productTypes)];
    }
    public function getProductTypeList(): array
    {
        return $this->productTypes;
    }

    //! EventSection entity
    private $eventSections = [
        "carte",
        "product",
        "recipe",
        "menu",
        "supplier",
        "planning",
        "contact",
        "inventaire",
        "other"
    ];
    public function oneRandomEventSection(): string
    {
        return $this->eventSections[array_rand($this->eventSections)];
    }
    public function getEventSectionList(): array
    {
        return $this->eventSections;
    }

    //! kitchenSpace Property
    private $kitchenSpace = [
        "Chaud",
        "Livraison",
        "salade",
        "dessert",
        "flam",
        "Frite"
    ];
    public function oneRandomKitchenSpace(): string
    {
        return $this->kitchenSpace[array_rand($this->kitchenSpace)];
    }
    public function getKitchenSpaceList(): array
    {
        return $this->kitchenSpace;
    }

     //! kitchenSpace Property
     private $rooms = [
        "Chambre Chaud",
        "Chambre Dur",
        "Sèche",
        "BOF",
        "FrigoFlam",
        "FrigoDessert",
        "CongélateurGlaces"
    ];
    public function oneRandomRoom(): string
    {
        return $this->rooms[array_rand($this->rooms)];
    }
    public function getRoomList(): array
    {
        return $this->rooms;
    }
}