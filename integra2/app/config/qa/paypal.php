<?php

$settings = [
    'settings' =>
        [
            'mode' => 'sandbox',
            'log.LogLevel' => 'FINE'
        ],
];

$settings['url'] = (($settings['settings']['mode'] == 'sandbox') ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com');

return $settings;