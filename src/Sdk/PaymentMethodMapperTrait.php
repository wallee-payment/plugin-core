<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk;

use Wallee\PluginCore\Localization\LocalizedString;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\PaymentMethod\PaymentMethod;
use Wallee\PluginCore\PaymentMethod\State;
use Wallee\Sdk\Model\PaymentMethodConfiguration as SdkPaymentMethodConfiguration;
use Wallee\Sdk\Service\PaymentMethodConfigurationsService as SdkPaymentMethodConfigurationsService;

/**
 * Shared mapping for SDK PaymentMethodConfiguration objects to domain PaymentMethod entities.
 *
 * Centralizes locale-aware title/description resolution (delegated to LocalizedString)
 * so the transaction and payment-method gateways map configurations identically.
 */
trait PaymentMethodMapperTrait
{
    /**
     * Maps an SDK PaymentMethodConfiguration to a domain PaymentMethod.
     *
     * The LocalizedString VO takes ownership of locale resolution, so the raw SDK
     * maps are passed directly without pre-resolving.
     *
     * @param SdkPaymentMethodConfiguration $config The SDK configuration; supplies every
     *        non-localized field.
     * @param SdkPaymentMethodConfiguration ...$translations The same configuration as
     *        returned for other locales; only their resolved title and description are
     *        used, merged into the maps of `$config`. Where two responses carry the same
     *        locale key, the earlier one wins.
     * @return PaymentMethod The domain entity.
     */
    protected function mapToPaymentMethod(
        SdkPaymentMethodConfiguration $config,
        SdkPaymentMethodConfiguration ...$translations,
    ): PaymentMethod {
        $title = $config->getResolvedTitle();
        $description = $config->getResolvedDescription();

        foreach ($translations as $translation) {
            $title = self::mergeLocalizedMaps($title, $translation->getResolvedTitle());
            $description = self::mergeLocalizedMaps($description, $translation->getResolvedDescription());
        }

        return new PaymentMethod(
            id: (int) $config->getId(),
            spaceId: (int) $config->getLinkedSpaceId(),
            state: State::from((string) $config->getState()),
            title: new LocalizedString($title ?? $config->getName()),
            description: new LocalizedString($description ?? $config->getDescription()),
            sortOrder: (int) $config->getSortOrder(),
            imageUrl: $config->getResolvedImageUrl(),
        );
    }

    /**
     * Normalizes caller-supplied locales to the `xx-YY` form the API uses.
     *
     * Accepts both `de_DE` and `de-DE`, drops blanks and removes duplicates while
     * keeping the caller's order, since the first locale decides which response
     * supplies the non-localized fields.
     *
     * @param array<array-key, string> $locales
     * @return list<string>
     */
    private static function normalizeLocales(array $locales): array
    {
        $normalized = [];

        foreach ($locales as $locale) {
            $locale = str_replace('_', '-', trim($locale));
            if ($locale !== '') {
                $normalized[$locale] = true;
            }
        }

        return array_keys($normalized);
    }

    /**
     * Runs a payment method configuration fetch once per locale and merges the translations.
     *
     * API V2 resolves `resolvedTitle` and `resolvedDescription` to the single language
     * named by the request's `Accept-Language` header, so a map covering several
     * locales can only be assembled from one complete fetch per locale. Each fetch
     * runs against a service of its own (see {@see SdkProvider::getLocalizedService()}),
     * so the header applies to that fetch and nothing else.
     *
     * The first locale's response supplies the non-localized fields and decides which
     * configurations are returned. With no locales, the fetch runs once without an
     * `Accept-Language` header, and the API answers in its default language.
     *
     * @param SdkProvider $sdkProvider The SDK provider.
     * @param LoggerInterface $logger Receives a debug entry when a response lacks a requested locale.
     * @param array<array-key, string> $locales Locales to request, in `de-DE` or `de_DE` form.
     * @param callable(SdkPaymentMethodConfigurationsService): iterable<SdkPaymentMethodConfiguration> $fetch
     *        Performs the complete fetch (all pages) against the given service.
     * @return list<PaymentMethod> The mapped payment methods.
     */
    private function fetchLocalizedPaymentMethods(
        SdkProvider $sdkProvider,
        LoggerInterface $logger,
        array $locales,
        callable $fetch,
    ): array {
        $locales = self::normalizeLocales($locales);

        if ($locales === []) {
            $configs = $fetch($sdkProvider->getService(SdkPaymentMethodConfigurationsService::class));

            $methods = [];
            foreach ($configs as $config) {
                $methods[] = $this->mapToPaymentMethod($config);
            }

            return $methods;
        }

        /** @var array<string, array<int, SdkPaymentMethodConfiguration>> $configsByLocale */
        $configsByLocale = [];
        foreach ($locales as $locale) {
            $configsByLocale[$locale] = [];
            foreach ($fetch($sdkProvider->getLocalizedService(SdkPaymentMethodConfigurationsService::class, $locale)) as $config) {
                $configsByLocale[$locale][(int) $config->getId()] = $config;
            }

            self::logMissingLocale($logger, $locale, $configsByLocale[$locale]);
        }

        $primary = array_shift($configsByLocale);

        $methods = [];
        foreach ($primary as $id => $config) {
            $translations = [];
            foreach ($configsByLocale as $configs) {
                if (isset($configs[$id])) {
                    $translations[] = $configs[$id];
                }
            }

            $methods[] = $this->mapToPaymentMethod($config, ...$translations);
        }

        return $methods;
    }

    /**
     * Logs the configurations whose title came back without the requested language.
     *
     * {@see LocalizedString::localize()} falls back to whatever value it has when a
     * locale is missing, so without this entry a missing translation would only show
     * up as text in the wrong language. A response keyed by another region of the
     * same language (`de-DE` for `de-CH`) is not a miss: `localize()` matches it by
     * language.
     *
     * @param array<int, SdkPaymentMethodConfiguration> $configs The configurations returned for the locale.
     */
    private static function logMissingLocale(LoggerInterface $logger, string $locale, array $configs): void
    {
        $languagePrefix = strtok($locale, '-') . '-';
        $missingIds = [];
        $returnedLocales = [];

        foreach ($configs as $id => $config) {
            $title = $config->getResolvedTitle();
            if ($title === null || $title === []) {
                continue;
            }

            $matches = false;
            foreach (array_keys($title) as $key) {
                $returnedLocales[$key] = true;
                if ($key === $locale || str_starts_with($key, $languagePrefix)) {
                    $matches = true;
                }
            }

            if (!$matches) {
                $missingIds[] = $id;
            }
        }

        if ($missingIds !== []) {
            $logger->debug('Payment method translations are missing for a requested locale.', [
                'locale' => $locale,
                'returnedLocales' => array_keys($returnedLocales),
                'paymentMethodIds' => $missingIds,
            ]);
        }
    }

    /**
     * @param array<string, string>|null $map
     * @param array<string, string>|null $addition
     * @return array<string, string>|null
     */
    private static function mergeLocalizedMaps(?array $map, ?array $addition): ?array
    {
        if ($addition === null) {
            return $map;
        }

        return ($map ?? []) + $addition;
    }
}
