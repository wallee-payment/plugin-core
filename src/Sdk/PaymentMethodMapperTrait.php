<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk;

use Wallee\PluginCore\Localization\LocalizedString;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\PaymentMethod\PaymentMethod;
use Wallee\PluginCore\PaymentMethod\State;
use Wallee\Sdk\Model\PaymentMethodConfiguration as SdkPaymentMethodConfiguration;

/**
 * Shared mapping for SDK PaymentMethodConfiguration objects to domain PaymentMethod entities.
 *
 * Centralizes locale-aware title/description resolution (delegated to LocalizedString)
 * so the transaction and payment-method gateways map configurations identically.
 */
trait PaymentMethodMapperTrait
{
    /**
     * Logs, per requested locale, the configurations whose title lacks that language.
     *
     * API V1 already returns every locale in one response, so the requested locales
     * do not change what is fetched; they only decide what counts as missing.
     * {@see LocalizedString::localize()} falls back to whatever value it has when a
     * locale is missing, so without this entry a missing translation would only show
     * up as text in the wrong language. A key for another region of the same
     * language (`de-DE` for `de-CH`) is not a miss: `localize()` matches it by language.
     *
     * @param array<array-key, string> $locales Locales in `de-DE` or `de_DE` form.
     * @param iterable<SdkPaymentMethodConfiguration> $configs The configurations returned by the API.
     */
    private static function logMissingLocales(LoggerInterface $logger, array $locales, iterable $configs): void
    {
        $locales = self::normalizeLocales($locales);
        if ($locales === []) {
            return;
        }

        foreach ($locales as $locale) {
            $languagePrefix = strtok($locale, '-') . '-';
            $missingIds = [];
            $returnedLocales = [];

            foreach ($configs as $config) {
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
                    $missingIds[] = (int) $config->getId();
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
    }
    /**
     * Maps an SDK PaymentMethodConfiguration to a domain PaymentMethod.
     *
     * The LocalizedString VO takes ownership of locale resolution, so the raw SDK
     * maps are passed directly without pre-resolving.
     *
     * @param SdkPaymentMethodConfiguration $config The SDK configuration.
     * @return PaymentMethod The domain entity.
     */
    protected function mapToPaymentMethod(SdkPaymentMethodConfiguration $config): PaymentMethod
    {
        return new PaymentMethod(
            id: (int) $config->getId(),
            spaceId: (int) $config->getLinkedSpaceId(),
            state: State::from((string) $config->getState()),
            title: new LocalizedString($config->getResolvedTitle() ?? $config->getName()),
            description: new LocalizedString($config->getResolvedDescription() ?? $config->getDescription()),
            sortOrder: (int) $config->getSortOrder(),
            imageUrl: $config->getResolvedImageUrl(),
        );
    }

    /**
     * Normalizes caller-supplied locales to the `xx-YY` form the API uses.
     *
     * Accepts both `de_DE` and `de-DE`, drops blanks and removes duplicates while
     * keeping the caller's order.
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
}
