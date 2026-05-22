<?php

declare(strict_types=1);

namespace Wallee\PluginCore\PaymentMethod;

use Wallee\PluginCore\Localization\LocalizedString;
use Wallee\PluginCore\Render\JsonStringableTrait;

/**
 * Domain entity representing a Payment Method.
 *
 * This class maps the external SDK configuration to a domain-specific
 * representation that is easier to use within the application.
 */
readonly class PaymentMethod
{
    use JsonStringableTrait;

    /**
     * @param int $id The ID of the payment method configuration.
     * @param int $spaceId The ID of the space this method belongs to.
     * @param State $state The state of the payment method.
     * @param LocalizedString $title The localized titles.
     * @param LocalizedString $description The localized descriptions.
     * @param int $sortOrder The sort order for display purposes.
     * @param string|null $imageUrl The URL of the payment method image, if available.
     */
    public function __construct(
        public int $id,
        public int $spaceId,
        public State $state,
        public LocalizedString $title,
        public LocalizedString $description,
        public int $sortOrder,
        public ?string $imageUrl,
    ) {
    }

    /**
     * Extracts the relative image path from the absolute image URL.
     *
     * The API typically returns an absolute URL (e.g., 'https://gateway.com/s/123/resource/payment/icon.svg').
     * This method safely strips the base URL and the 'resource/' segment, returning just the relative path.
     * If the 'resource/' segment is not found, it falls back to returning the raw URL string.
     *
     * @return string The relative image path, or an empty string if no URL is set.
     */
    public function getRelativeImagePath(): string
    {
        $url = (string) ($this->imageUrl ?? '');

        // Strip the base URL up to 'resource/'
        $index = \strpos($url, 'resource/');
        $path = $index !== false ? \substr($url, $index + 9) : $url;

        // Strip any query parameters (e.g., '?strategy=snapshot...')
        $queryIndex = \strpos($path, '?');
        if ($queryIndex !== false) {
            $path = \substr($path, 0, $queryIndex);
        }

        return $path;
    }
}
