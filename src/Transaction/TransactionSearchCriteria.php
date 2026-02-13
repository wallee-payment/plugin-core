<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Transaction;

/**
 * Data Transfer Object for transaction search criteria.
 */
class TransactionSearchCriteria
{
    /**
     * @param int|null $limit The maximum number of results to return.
     * @param string|null $sortField The field to sort by.
     * @param string|null $sortOrder The sort order ('ASC' or 'DESC').
     * @param array $filters Key-value pairs for filtering (e.g., ['state' => 'FULFILLED']).
     */
    public function __construct(
        public ?int $limit = null,
        public ?string $sortField = 'id',
        public ?string $sortOrder = 'DESC',
        public array $filters = [],
    ) {}
}
