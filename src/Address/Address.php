<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Address;

class Address
{
    public string $city;
    public string $country; // ISO 3166-1 alpha-2 (e.g., 'US', 'DE')
    public ?string $emailAddress = null;
    public ?string $familyName = null;
    public ?string $givenName = null;
    public ?string $organizationName = null;
    public ?string $phoneNumber = null;
    public ?string $postcode = null;
    public ?string $street = null;
    public ?string $salutation = null; // e.g., 'Mrs', 'Mr', 'Dr'
    public ?\DateTime $dateOfBirth = null;
    public ?string $salesTaxNumber = null;
}