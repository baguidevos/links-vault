<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Filament Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during Filament for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    // Navigation
    'navigation_label' => 'Discounts',
    'navigation_group' => 'Subscriptions',

    'create_discount' => 'Create Discount',
    'edit_discount' => 'Edit Discount',

    // Table Columns
    'name' => 'Name',
    'code' => 'Code',
    'type' => 'Type',
    'value' => 'Value',
    'currency' => 'Currency',
    'min_amount' => 'Minimum Amount',
    'max_uses' => 'Maximum Uses',
    'used_count' => 'Used Count',
    'starts_at' => 'Start Date',
    'expires_at' => 'Expiry Date',
    'is_active' => 'Is Active',
    'priority' => 'Priority',
    'description' => 'Description',

    // Form Fields
    'basic_information' => 'Basic Information',
    'discount_value' => 'Discount Value',
    'usage_limits' => 'Usage & Schedule',
    'applicability' => 'Applicability',
    'min_amount_hint' => 'Leave empty for no minimum',
    'priority_hint' => 'Higher number = applied first when multiple discounts match',
    'applies_to' => 'Applies To',
    'applies_to_plans' => 'Plans',
    'applies_to_features' => 'Features',
    'applicable_plans' => 'Applicable Plans',
    'applicable_features' => 'Applicable Features',

    // Types
    'type_percentage' => 'Percentage',
    'type_fixed' => 'Fixed Amount',

    // Messages
    'discount_applied' => 'Discount applied successfully',
    'discount_invalid' => 'Invalid discount code',
    'discount_expired' => 'This discount has expired',
    'discount_not_active' => 'This discount is not active',
    'discount_max_uses_reached' => 'This discount has reached its maximum usage limit',
    'discount_min_amount_not_met' => 'Minimum amount not met for this discount',
];