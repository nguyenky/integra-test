<?php

$settings = [
    'sandbox_client_id' => 'AclGGRBlJRs5PAZflgFKNQ8ivX5so-2iCGAQr3akGhoRBj_Ks4Lay2PZxyii',
    'sandbox_secret' => 'EOZHBBA0sTZs3q-cBLxAXZb1uIl2P3dHLAo-AmA0PVZvGxTJHHwcLmmj60oK',
    'sandbox_merchant' => 'ken.calupitan-facilitator@gmail.com',
    'sandbox_buyer' => 'ken.calupitan-buyer@gmail.com',

    'settings' =>
        [
            'mode' => 'live',
            'http.ConnectionTimeOut' => 30,
            'log.LogEnabled' => true,
            'log.FileName' => storage_path() . '/logs/paypal.log',
            'log.LogLevel' => 'WARN'
        ],

    'qeautoparts_client_id' => 'Af1eJRCoTjJTSiZSfl-Jx7n-EuWWM_FPrraiW_QMqBQ6pZ3HWa5gSm-ZwqNn',
    'qeautoparts_secret' => 'EFPP3xBRUWWHj36D_CInJiuPR7Qg01cgndPcL99iCT3OeKnHr7dX6aoqpCfm',

    'europortparts_client_id' => 'ATpOfPUcmeNtXaUDtC7C9igY1LgbTO4eFreDgVustrYlol6d4knRVdsaSj6ZQHE14FXTgrUQKc_TPq2m',
    'europortparts_secret' => 'ECF9U4_6qkgGJx56AwJ_zhPSoqmm_aob-Jn4ZGUNpi11R6fItGrExV57hRAZev-uX-c2JwAoaoYCtMwk',

    'eocparts_client_id' => 'ASv1FjE6W3H-enhAwmkuHQqearpYClPVTptw6H2Z7qqdPz-8HxHh9SArWlA76kR7Fv-E1VFzpNXDFMKn',
    'eocparts_secret' => 'EG9KMvfdSa4-hXUT_5OJ0owU6OIatSCmX6Eujul3Ob-9o4pPslyQg-vhekw6o4EmCj8s8NJWyGARQBNf',

    'b2cautoparts_client_id' => '',
    'b2cautoparts_secret' => '',

    'merchants' =>
        [
            'qeautoparts' =>
                [
                    'email' => 'payments@eocparts.com',
                    'first_name' => 'QE Auto Parts',
                    'address' =>
                        [
                            'line1' => '7845 NW 66th St',
                            'line2' => 'Suite 1',
                            'city' => 'Miami',
                            'country_code' => 'US',
                            'postal_code' => '33166',
                            'state' => 'FL'
                        ],
                    'phone' =>
                        [
                            'country_code' => '001',
                            'national_number' => '8882197710'
                        ],
                    'website' => 'https://qeautoparts.com/'
                ],
            'need4autoparts' =>
                [
                    'email' => 'support@need4autoparts.com',
                    'first_name' => 'Need 4 Auto Parts',
                    'address' =>
                        [
                            'line1' => '7845 NW 66th St',
                            'line2' => 'Suite 1',
                            'city' => 'Miami',
                            'country_code' => 'US',
                            'postal_code' => '33166',
                            'state' => 'FL'
                        ],
                    'phone' =>
                        [
                            'country_code' => '001',
                            'national_number' => '8882197710'
                        ],
                    'website' => 'https://need4autoparts.com/'
                ],
            'eocparts' =>
                [
                    'email' => 'support@eocparts.com',
                    'first_name' => 'EOC Parts',
                    'address' =>
                        [
                            'line1' => '7845 NW 66th St',
                            'line2' => 'Suite 1',
                            'city' => 'Miami',
                            'country_code' => 'US',
                            'postal_code' => '33166',
                            'state' => 'FL'
                        ],
                    'phone' =>
                        [
                            'country_code' => '001',
                            'national_number' => '8882783390'
                        ],
                    'website' => 'https://eocparts.com/'
                ],
            'europortparts' =>
                [
                    'email' => 'support@europortparts.com',
                    'first_name' => 'Euro Port Parts',
                    'address' =>
                        [
                            'line1' => '7845 NW 66th St',
                            'line2' => 'Suite 1',
                            'city' => 'Miami',
                            'country_code' => 'US',
                            'postal_code' => '33166',
                            'state' => 'FL'
                        ],
                    'phone' =>
                        [
                            'country_code' => '001',
                            'national_number' => '8888841090'
                        ],
                    'website' => 'https://europortparts.com/'
                ],
            'b2cautoparts' =>
                [
                    'email' => 'support@b2cautoparts.com',
                    'first_name' => 'B2C Auto Port Parts',
                    'address' =>
                        [
                            'line1' => '7845 NW 66th St',
                            'line2' => 'Suite 1',
                            'city' => 'Miami',
                            'country_code' => 'US',
                            'postal_code' => '33166',
                            'state' => 'FL'
                        ],
                    'phone' =>
                        [
                            'country_code' => '001',
                            'national_number' => '8889809646'
                        ],
                    'website' => 'https://b2cautoparts.com/'
                ],
        ]
];

$settings['url'] = (($settings['settings']['mode'] == 'sandbox') ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com');

return $settings;