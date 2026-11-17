<?php

namespace App\Services\Pricing;

use App\Models\Service;
use App\Models\User;

/**
 * Resolves the price a specific user pays for a service, in this priority
 * order: an explicit per-reseller override, then a reseller-group override,
 * then an active pricing rule matching the user's role, falling back to the
 * service's base_price. All arithmetic is done on strings via bcmath —
 * monetary values are never floats (master prompt §5 Module B: PRICING).
 */
class PricingService
{
    public function priceFor(Service $service, User $user): string
    {
        $resellerProfile = $user->resellerProfile;

        if ($resellerProfile) {
            $override = $service->pricingRules()
                ? \App\Models\ResellerPrice::where('service_id', $service->id)
                    ->where('reseller_profile_id', $resellerProfile->id)
                    ->first()
                : null;

            if ($override) {
                return (string) $override->price;
            }

            if ($resellerProfile->reseller_group_id) {
                $groupOverride = \App\Models\ResellerPrice::where('service_id', $service->id)
                    ->where('reseller_group_id', $resellerProfile->reseller_group_id)
                    ->first();

                if ($groupOverride) {
                    return (string) $groupOverride->price;
                }
            }
        }

        $roleSlug = $user->getRoleNames()->first();

        $rule = $service->pricingRules()
            ->where('is_active', true)
            ->where(function ($q) use ($roleSlug) {
                $q->where('applies_to', 'all')
                    ->orWhere(function ($q2) use ($roleSlug) {
                        $q2->where('applies_to', 'role')->where('role_or_group_slug', $roleSlug);
                    });
            })
            ->orderByDesc('id')
            ->first();

        $base = (string) $service->base_price;

        if (! $rule) {
            return $base;
        }

        return match ($rule->adjustment_type) {
            'fixed_price' => (string) $rule->adjustment_value,
            'markup_fixed' => bcadd($base, (string) $rule->adjustment_value, 4),
            'markup_percent' => bcadd($base, bcmul($base, bcdiv((string) $rule->adjustment_value, '100', 6), 4), 4),
            default => $base,
        };
    }
}
