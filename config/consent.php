<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Purposes
    |--------------------------------------------------------------------------
    |
    | The things you ask the user about. Keys are stored with the user's
    | choice; labels and descriptions are for your consent screen. Add your
    | own purposes freely — the store accepts any key listed here.
    |
    | `required` purposes (e.g. "essential") are always granted and are not
    | asked about.
    |
    */

    'purposes' => [
        'analytics' => [
            'label' => 'Analytics',
            'description' => 'Count screens and taps so we can improve the app.',
        ],
        'ads' => [
            'label' => 'Advertising',
            'description' => 'Measure ads and show ones that are relevant to you.',
        ],
        'personalisation' => [
            'label' => 'Personalisation',
            'description' => 'Remember your preferences to tailor what you see.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Policy version
    |--------------------------------------------------------------------------
    |
    | Bump this when your privacy policy or purposes change. Users who
    | answered an older version are asked again (Consent::needsPrompt()).
    |
    */

    'version' => '1',

];
