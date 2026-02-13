<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Refund;

class Refund
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var float
     */
    public float $amount;

    /**
     * @var State
     */
    public State $state;

    /**
     * @var int
     */
    public int $transactionId;

    /**
     * @var string
     */
    public string $externalId;
}
