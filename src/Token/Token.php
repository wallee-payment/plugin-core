<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Token;

/**
 * Domain object representing a Token.
 */
class Token
{
    /**
     * @var int The token ID.
     */
    public int $id;

    /**
     * @var int The space ID.
     */
    public int $spaceId;

    /**
     * @var State The strict state enum.
     */
    public State $state;

    /**
     * @var int The version number.
     */
    public int $version;

    /**
     * @var string|null The token name/value logic if needed, but SDK usually just needs ID.
     */
    // Add other fields as discovered necessary, keeping it minimal for now.
}
