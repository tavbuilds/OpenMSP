<?php

namespace App\Distributors;

final class SyncReport
{
    public int $companiesCreated = 0;

    public int $companiesMatched = 0;

    public int $productsUpserted = 0;

    public int $contractsCreated = 0;

    public int $contractsUpdated = 0;

    public int $pricesUpdated = 0;

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
            'companies_created' => $this->companiesCreated,
            'companies_matched' => $this->companiesMatched,
            'products_upserted' => $this->productsUpserted,
            'contracts_created' => $this->contractsCreated,
            'contracts_updated' => $this->contractsUpdated,
            'prices_updated' => $this->pricesUpdated,
            'errors' => $this->errors,
            'finished_at' => now()->toIso8601String(),
        ];
    }
}
