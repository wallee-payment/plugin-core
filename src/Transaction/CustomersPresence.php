<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Transaction;

enum CustomersPresence: string
{
    case NOT_PRESENT = 'NOT_PRESENT';
    case PHYSICAL_PRESENT = 'PHYSICAL_PRESENT';
    case VIRTUAL_PRESENT = 'VIRTUAL_PRESENT';
}
