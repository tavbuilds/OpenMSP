<?php

namespace App\Registrars;

final class DomainSyncReport
{
    public int $domainsCreated = 0;

    public int $domainsUpdated = 0;

    public int $domainsUnassigned = 0;

    public int $tldPricesUpdated = 0;

    /**
     * Extensions the registrar returned no price for. They still get a catalog
     * entry, without a cost — worth naming rather than leaving to be noticed.
     *
     * @var list<string>
     */
    public array $extensionsWithoutPrice = [];

    /** @var list<string> */
    public array $errors = [];

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'domains_created' => $this->domainsCreated,
            'domains_updated' => $this->domainsUpdated,
            'domains_unassigned' => $this->domainsUnassigned,
            'tld_prices_updated' => $this->tldPricesUpdated,
            'extensions_without_price' => array_values(array_unique($this->extensionsWithoutPrice)),
            'errors' => $this->errors,
            'finished_at' => now()->toIso8601String(),
        ];
    }
}
