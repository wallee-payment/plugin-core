<?php

declare(strict_types=1);

namespace Wallee\PluginCore\PaymentMethod;

use Wallee\PluginCore\PaymentMethod\Exception\PaymentMethodException;

/**
 * Interface for fetching payment methods from the infrastructure layer.
 *
 * Titles and descriptions come back in every locale the API has, whichever
 * locales are passed. The locales a caller passes are the ones checked for a
 * translation: a missing one is logged at DEBUG level, since
 * {@see \Wallee\PluginCore\Localization\LocalizedString::localize()}
 * would otherwise fall back to another language without notice. The parameter
 * matches the 0.2.x interface, where the locales decide which translations are
 * fetched, so the same call works against both.
 */
interface PaymentMethodGatewayInterface
{
    /**
     * Fetches a specific payment method by its ID.
     *
     * @param int $spaceId The ID of the space.
     * @param int $id The ID of the payment method.
     * @param list<string> $locales Locales the caller displays, as `de-DE` or `de_DE`.
     * @return PaymentMethod The payment method.
     * @throws \RuntimeException If the payment method is not found.
     */
    public function fetchById(int $spaceId, int $id, array $locales = []): PaymentMethod;

    /**
     * Fetches available payment methods for a given space.
     *
     * @param int $spaceId The ID of the space.
     * @param string|null $state Optional state to filter by.
     * @param list<string> $locales Locales the caller displays, as `de-DE` or `de_DE`.
     * @return PaymentMethodCollection List of available payment methods.
     * @throws PaymentMethodException If the payment methods cannot be retrieved.
     */
    public function fetchBySpaceId(int $spaceId, ?string $state = null, array $locales = []): PaymentMethodCollection;
}
