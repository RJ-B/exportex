<?php

namespace App\Fakturace;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Ověřená událost z fakturace (webhook, podpis V2): `invoice.issued`,
 * `invoice.paid`, `invoice.cancelled`… Projekt si na ni pověsí posluchače
 * (Event::listen(FakturaceUdalost::class, …)) – šablona sama nic nedělá.
 *
 * `data` je tělo události (`data.document` = doklad: id, number, external_id,
 * status, paid_at, public_url…), `doruceni` id doručení (každé jen jednou).
 */
class FakturaceUdalost
{
    use Dispatchable;

    /** @param  array<string, mixed>  $data */
    public function __construct(
        public string $udalost,
        public array $data,
        public ?string $doruceni = null,
    ) {}

    /** @return array<string, mixed> doklad z události */
    public function doklad(): array
    {
        return (array) ($this->data['document'] ?? []);
    }
}
