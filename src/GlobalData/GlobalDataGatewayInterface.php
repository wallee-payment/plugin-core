<?php

declare(strict_types=1);

namespace Wallee\PluginCore\GlobalData;

use Wallee\PluginCore\GlobalData\Currency\CurrencyCollection;
use Wallee\PluginCore\GlobalData\Exception\GlobalDataException;
use Wallee\PluginCore\GlobalData\LabelDescriptor\LabelDescriptorCollection;
use Wallee\PluginCore\GlobalData\LabelDescriptorGroup\LabelDescriptorGroupCollection;
use Wallee\PluginCore\GlobalData\Language\LanguageCollection;
use Wallee\PluginCore\GlobalData\PaymentConnector\PaymentConnectorCollection;

/**
 * Read access to the Wallee Portal's global reference data.
 *
 * These lists describe what the Wallee Portal itself supports — the currencies and
 * languages it accepts, the connectors it can route through, and the label
 * descriptors used to annotate charge attempts and tokens. They are the same for
 * every space, so — unlike most other gateways in PluginCore — **no method here
 * takes a space ID**, and none of them are scoped to a merchant.
 *
 * Implementations translate the API's representation into this namespace's domain
 * entities, so consumers never handle SDK models and see no difference between
 * API versions. Where the APIs disagree on a field's shape (one reporting a bare
 * ID, the other embedding a whole entity), implementations normalize down to the
 * ID, so an otherwise identical read never costs an extra round trip on one API
 * version but not the other.
 */
interface GlobalDataGatewayInterface
{
    /**
     * Returns every currency the Wallee Portal supports.
     *
     * @return CurrencyCollection The supported currencies.
     * @throws GlobalDataException If the currencies cannot be retrieved, e.g. because
     *         the API is unreachable or rejects the request. Exposes `isRetryable()`
     *         like every other PluginCore exception.
     */
    public function getCurrencies(): CurrencyCollection;

    /**
     * Returns every label descriptor group the Wallee Portal defines.
     *
     * @return LabelDescriptorGroupCollection The label descriptor groups.
     * @throws GlobalDataException If the groups cannot be retrieved.
     */
    public function getLabelDescriptorGroups(): LabelDescriptorGroupCollection;

    /**
     * Returns every label descriptor the Wallee Portal defines.
     *
     * A label descriptor is the definition of a kind of label; it is what a
     * {@see \Wallee\PluginCore\Charge\Attempt\Label}'s `descriptorId`
     * refers to.
     *
     * @return LabelDescriptorCollection The label descriptors.
     * @throws GlobalDataException If the descriptors cannot be retrieved.
     */
    public function getLabelDescriptors(): LabelDescriptorCollection;

    /**
     * Returns every language the Wallee Portal supports.
     *
     * @return LanguageCollection The supported languages.
     * @throws GlobalDataException If the languages cannot be retrieved.
     */
    public function getLanguages(): LanguageCollection;

    /**
     * Returns every payment connector the Wallee Portal defines.
     *
     * @return PaymentConnectorCollection The payment connectors.
     * @throws GlobalDataException If the connectors cannot be retrieved.
     */
    public function getPaymentConnectors(): PaymentConnectorCollection;
}
