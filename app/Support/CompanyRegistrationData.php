<?php

namespace App\Support;

class CompanyRegistrationData
{
    public function __construct(
        public readonly string $companyName,
        public readonly string $companyCode,
        public readonly ?int $countryId,
        public readonly ?string $address,
        public readonly ?string $phone,
        public readonly string $companyEmail,
        public readonly ?string $logoPath,
        public readonly array $social,
        public readonly string $ownerName,
        public readonly string $ownerEmail,
        public readonly string $ownerPassword,
        public readonly ?string $iataCode,
        public readonly ?string $gdsOfficeId,
        public readonly ?string $iataClientId,
        public readonly ?string $iataClientSecret,
        public readonly string $currency,
        public readonly array $agents,
        public readonly array $supplierIds,
        public readonly array $gateways,
        public readonly array $gdsPccs,
    ) {
    }

    public static function fromArray(array $a): self
    {
        return new self(
            companyName: $a['company_name'],
            companyCode: $a['company_code'],
            countryId: isset($a['country_id']) ? (int) $a['country_id'] : null,
            address: $a['address'] ?? null,
            phone: $a['phone'] ?? null,
            companyEmail: $a['company_email'],
            logoPath: $a['logo_path'] ?? null,
            social: $a['social'] ?? [],
            ownerName: $a['owner_name'],
            ownerEmail: $a['owner_email'],
            ownerPassword: $a['owner_password'],
            iataCode: $a['iata_code'] ?? null,
            gdsOfficeId: $a['gds_office_id'] ?? null,
            iataClientId: $a['iata_client_id'] ?? null,
            iataClientSecret: $a['iata_client_secret'] ?? null,
            currency: $a['currency'] ?? 'KWD',
            agents: array_values($a['agents'] ?? []),
            supplierIds: array_map('intval', $a['supplier_ids'] ?? []),
            gateways: array_values($a['gateways'] ?? []),
            gdsPccs: array_values($a['gds_pccs'] ?? []),
        );
    }
}
