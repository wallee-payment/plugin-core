<?php

declare(strict_types=1);

namespace Wallee\PluginCore\PaymentMethod;

use Wallee\PluginCore\PaymentMethod\Exception\PaymentMethodException;

/**
 * Interface for fetching payment methods from the infrastructure layer.
 *
 * Titles and descriptions only cover the locales a caller asks for: the API
 * answers each request in a single language. Pass the locales the shop displays
 * so that {@see \Wallee\PluginCore\Localization\LocalizedString::localize()}
 * can resolve each of them; without any, the API's default language (en-US) is
 * all that comes back.
 */
interface PaymentMethodGatewayInterface
{
    /**
     * Fetches a specific payment method by its ID.
     *
     * @param int $spaceId The ID of the space.
     * @param int $id The ID of the payment method.
     * @param list<string> $locales Locales to fetch titles and descriptions for, as
     *        `de-DE` or `de_DE`. Each locale costs one request.
     * @return PaymentMethod The payment method.
     * @throws \RuntimeException If the payment method is not found.
     */
    public function fetchById(int $spaceId, int $id, array $locales = []): PaymentMethod;

    /**
     * Fetches available payment methods for a given space.
     *
     * @param int $spaceId The ID of the space.
     * @param string|null $state Optional state to filter by.
     * @param list<string> $locales Locales to fetch titles and descriptions for, as
     *        `de-DE` or `de_DE`. Each locale costs one complete paged search.
     * @return PaymentMethodCollection List of available payment methods.
     * @throws PaymentMethodException If the payment methods cannot be retrieved.
     */
    public function fetchBySpaceId(int $spaceId, ?string $state = null, array $locales = []): PaymentMethodCollection;
}
