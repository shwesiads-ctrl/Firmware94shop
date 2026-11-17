<?php

return [
    'required' => 'The :attribute field is required.',
    'email' => 'The :attribute must be a valid email address.',
    'min' => ['string' => 'The :attribute must be at least :min characters.'],
    'unique' => 'This :attribute has already been taken.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'custom' => [
        'amount' => ['min' => 'The deposit amount is below the allowed minimum.'],
    ],
    'attributes' => [
        'email' => 'email address',
        'password' => 'password',
    ],
];
