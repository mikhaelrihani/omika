<?php

namespace App\DataFixtures\Provider;

use Faker\Provider\Base;

/**
 * Custom Faker Provider for generating specific application-related data.
 */
class AppProvider extends Base
{
    /**
     * Generates a random \DateTimeImmutable instance between two dates.
     *
     * @param string|\DateTime $startDate The start date of the range.
     * @param string|\DateTime $endDate The end date of the range.
     * @return \DateTimeImmutable
     */
    public function dateTimeImmutableBetween($startDate = '-30 years', $endDate = 'now'): \DateTimeImmutable
    {
        $dateTime = $this->generator->dateTimeBetween($startDate, $endDate);
        return \DateTimeImmutable::createFromMutable($dateTime);
    }

    /**
     * Generates timestamps in chronological order for created and updated fields.
     *
     * @return array An associative array with 'createdAt' and 'updatedAt' keys.
     */
    public function createTimeStamps(): array
    {
        $createdAt = $this->dateTimeImmutableBetween('-5 years', 'now');
        $updatedAt = $this->dateTimeImmutableBetween($createdAt->format('Y-m-d H:i:s'), 'now');
        return compact('createdAt', 'updatedAt');
    }

    //! Role property

    /** @var array List of available roles. */
    private $roles = [
        ["ROLE_USER"],
        ["ROLE_ADMIN"],
        ["ROLE_SUPER_ADMIN"]
    ];

    /**
     * Returns a random role from the predefined roles list.
     *
     * @return array A randomly selected role.
     */
    public function role(): array
    {
        return $this->roles[array_rand($this->roles)];
    }

    //! Status Property

    /** @var array List of possible statuses. */
    private $status = [
        "done",
        "todo",
        "unrealised",
        "late",
        "pending",
        "warning",
        "todo_modified"
    ];

    /**
     * Returns one random status from the predefined list.
     *
     * @return string A random status.
     */
    public function getOneRandomStatus(): string
    {
        return $this->status[array_rand($this->status)];
    }

    //! Business entity

    /** @var array List of business names. */
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

    /**
     * Returns one random business name from the predefined list.
     *
     * @return string A random business name.
     */
    public function getOneRandombusiness(): string
    {
        return $this->business[array_rand($this->business)];
    }

    /**
     * Returns the complete list of business names.
     *
     * @return array An array of all business names.
     */
    public function getBusinessList(): array
    {
        return $this->business;
    }

    //! Mimes Entity

    /** @var array List of MIME types. */
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

    /**
     * Returns one random MIME type from the predefined list.
     *
     * @return string A random MIME type.
     */
    public function oneRandomMime(): string
    {
        return $this->mimes[array_rand($this->mimes)];
    }

    /**
     * Returns the complete list of MIME types.
     *
     * @return array An array of all MIME types.
     */
    public function getMimelist(): array
    {
        return $this->mimes;
    }

    //! Units Entity

    /** @var array List of measurement units. */
    private $units = [
        "Kilogram"   => "kg",
        "Gram"       => "g",
        "Liter"      => "l",
        "Centiliter" => "cl",
        "Unit"       => "unit",
        "Piece"      => "piece",
    ];

    /**
     * Returns one random unit from the predefined units list.
     *
     * @return string A random unit name.
     */
    public function oneRandomUnit(): string
    {
        return array_rand($this->units);
    }

    /**
     * Returns the complete list of units and their abbreviations.
     *
     * @return array An associative array of units.
     */
    public function getUnitList(): array
    {
        return $this->units;
    }

    //! ProductType Entity

    /** @var array List of product types. */
    private $productTypes = [
        "surgelé",
        "frais",
        "sec",
        "autre"
    ];

    /**
     * Returns one random product type from the predefined list.
     *
     * @return string A random product type.
     */
    public function oneRandomProductType(): string
    {
        return $this->productTypes[array_rand($this->productTypes)];
    }

    /**
     * Returns the complete list of product types.
     *
     * @return array An array of product types.
     */
    public function getProductTypeList(): array
    {
        return $this->productTypes;
    }

    //! Section Entity

    /** @var array List of sections. */
    private $Sections = [
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

    /**
     * Returns one random  section from the predefined list.
     *
     * @return string A random section name.
     */
    public function getOneRandomSection(): string
    {
        return $this->Sections[array_rand($this->Sections)];
    }

    /**
     * Returns the complete list of sections.
     *
     * @return array An array of section names.
     */
    public function getSectionList(): array
    {
        return $this->Sections;
    }

   
    //! KitchenSpace Property

    /** @var array List of kitchen spaces. */
    private $kitchenSpace = [
        "Chaud",
        "Livraison",
        "salade",
        "dessert",
        "flam",
        "Frite"
    ];

    /**
     * Returns one random kitchen space from the predefined list.
     *
     * @return string A random kitchen space.
     */
    public function oneRandomKitchenSpace(): string
    {
        return $this->kitchenSpace[array_rand($this->kitchenSpace)];
    }

    /**
     * Returns the complete list of kitchen spaces.
     *
     * @return array An array of kitchen spaces.
     */
    public function getKitchenSpaceList(): array
    {
        return $this->kitchenSpace;
    }

    //! Room Property

    /** @var array List of rooms. */
    private $rooms = [
        "Chambre Chaud",
        "Chambre Dur",
        "Sèche",
        "BOF",
        "FrigoFlam",
        "FrigoDessert",
        "CongélateurGlaces"
    ];

    /**
     * Returns one random room from the predefined list.
     *
     * @return string A random room name.
     */
    public function oneRandomRoom(): string
    {
        return $this->rooms[array_rand($this->rooms)];
    }

    /**
     * Returns the complete list of rooms.
     *
     * @return array An array of room names.
     */
    public function getRoomList(): array
    {
        return $this->rooms;
    }
}
