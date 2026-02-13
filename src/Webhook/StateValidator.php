<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Webhook;

use Wallee\PluginCore\State\ValidatesStateTransitions;
use Wallee\PluginCore\Webhook\Enum\WebhookListener as WebhookListenerEnum;

/**
 * Validates if a webhook 'state' is valid for a given listener.
 */
class StateValidator
{
    /**
     * Checks if the provided state is a valid case for the enum associated with the listener.
     */
    public function isValid(WebhookListenerEnum $listener, string $state): bool
    {
        $enumClass = $listener->getStateEnumClass();

        // If the listener has no specific states defined, any state is considered valid.
        if ($enumClass === null) {
            return true;
        }

        // Check if the class is a backed enum and if the state is a valid case.
        if (enum_exists($enumClass) && method_exists($enumClass, 'tryFrom')) {
            return $enumClass::tryFrom($state) !== null;
        }

        return false;
    }

    /**
     * Checks if the transition from a previous state to a current state is valid.
     */
    public function getTransitionPath(WebhookListenerEnum $listener, ?string $lastProcessedState, string $remoteState): ?array
    {
        if ($lastProcessedState === $remoteState) {
            return [];
        }

        $enumClass = $listener->getStateEnumClass();
        if ($enumClass === null || !in_array(ValidatesStateTransitions::class, class_uses($enumClass))) {
            return [$remoteState];
        }

        if ($lastProcessedState === null) {
            $map = $enumClass::getTransitionMap();
            $initialStates = $map['initial'] ?? [];
            return in_array($remoteState, $initialStates, true) ? [$remoteState] : null;
        }

        $localStateCase = $enumClass::tryFrom($lastProcessedState); // Use renamed variable
        $remoteStateCase = $enumClass::tryFrom($remoteState);
        if ($localStateCase === null || $remoteStateCase === null) {
            return null;
        }

        if ($localStateCase->canTransitionTo($remoteStateCase)) {
            return [$remoteState];
        }

        $map = $enumClass::getTransitionMap();
        $sequence = $map['sequence'] ?? [];

        if (!empty($sequence)) {
            $previousIndex = array_search($lastProcessedState, $sequence, true); // Use renamed variable
            $currentIndex = array_search($remoteState, $sequence, true);

            if ($previousIndex !== false && $currentIndex !== false && $currentIndex > $previousIndex) {
                return array_slice($sequence, $previousIndex + 1, $currentIndex - $previousIndex);
            }
        }
        
        return null;
    }
}
