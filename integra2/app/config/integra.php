<?php

$settings = [
    'imc_web' =>
        [
            'username' => '1002262d',
            'password' => 'tacoma4',
            'account_num' => '7517',
            'store' => '10651'
        ],

    'imc_export' =>
        [
            'username' => '1031080b',
            'password' => 'exp0rt',
            'account_num' => '0001031080',
            'store' => '10651'
        ],

    'imc_export_order' =>
        [
            'username' => '1031080c',
            'password' => 'exp0rt',
            'account_num' => '0001031080',
            'store' => '10651'
        ],

    'ssf_web' =>
        [
            'username' => '2833599',
            'password' => 'tacoma1'
        ],

    'amazon_ad' =>
        [
            'associate_tag' => 'ep0e9-20',
            #'access_key_id' => 'AKIAICU5TC2SONVZ4WDA',
            'access_key_id' => 'AKIAIFEXUHX5UPNCBCJQ',
            #'secret_access_key' => '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',
            'secret_access_key' => 'kzqeqKjKMmW4hfVJoTAjVdh4YO7QGfYOp8wuXXuW',
            'merchant_name' => 'B2C Auto Parts'
        ],

    'amazon_mws' =>
        [
            'access_key_id' => 'AKIAILMIF3RH75U6GSRQ',
            'secret_access_key' => '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',
            'application_name' => 'EOC',
            'application_version' => '1.0',
            'merchant_id' => 'A22LWEMCXESXCG',
            'marketplace_id' => 'ATVPDKIKX0DER'
        ],

    'ssf_request_rma' =>
        [
            'email.host' => 'eocparts.com',
            'email.username' => 'returns@eocparts.com',
            'email.password' => 'returns1',
            'smtp.port' => 465,
            'imap.port' => 993,
            'imap.mailbox' => 'INBOX',
            'sender.email' => 'returns@eocparts.com',
            'sender.name' => 'EOC Parts',
            'recipient.email' => 'dfontana@ssfautoparts.com',
            'recipient.name' => 'Dino'
        ],

    'smtp' =>
        [
            'password' => 'turb0charger',
            'host' => '127.0.0.1',
            'port' => 25,
        ],
    'ebay'=>
        [
            'EBAY_HOST' => 'https://api.ebay.com/',
            'DEV_ID' => '24b32b7f-f7a9-4e99-bfce-b7a84113f5b6',
            'APP_ID' => 'KBCwareT-fa69-4a39-a049-60ca79a5fff2',
            'APP_ID2' => 'EdOrma-eocparts-PRD-859c93353-14e3c756',
            'CERT_ID' => '69128ac4-71d1-475d-ba77-92a6729ad69c',
            'SITE_ID' => '100',
            'EBAY_SELLER' => 'qeautoparts1',
            'EBAY_TOKEN' => 'AgAAAA**AQAAAA**aAAAAA**fsNuWA**nY+sHZ2PrBmdj6wVnY+sEZ2PrA2dj6wCloGgCpaEqQidj6x9nY+seQ**NosBAA**AAMAAA**7+MiDKWT7B1VlFXExufh4Eedx5WzkgWlmTjSN5YaxKXihyfE0dUkla6bpc47+xZH5YzR+E04ZCyinwnCH4iOYXDpgZMnovv34x13OzalirA7MMwwjlx9qT2+3l0m42I9t6j+ZdEhWURMA/47/kbgt5k6baA5cXn4Syy7kiDyzdjLORubXP49K9ip59kJIZ5b8J4DnWslV8fSkkR2DkfiZ6+WlvLBxxv1KuVB59TZvbOARoRugOZAgG0iJHc+2faJhtHVt0m9JR+TmOvoMT6a5y9Mf2W4+shmeK5ena63rZ4p7aeMY0YLYHy1xVkphWTmI8j5qRJEsYIVmOLMCAedYbMKejFKHZOJqNjLtoBd4egz4P0mZkveGmF6PeYAvr/w+V+1fSPpdnU6KMUhiNGTrtWgi/dRBdylC+XhS/OwvbMdYtZXg1eaIbR6eVANcRlQuW93+Wm5YNtqUIIfD5ej6JEoVk8EOMijzq/DUT2Q1Op8sLNvpZWMvSiOeP+ccpxsWdh9aIzRq73N3X8Z3o4T7uudbnaDzeu3QZqzCYWUf0r+wK1uab8WP36NcsvAEafJyacnrpdgRvPLLkFv5A+CQWtb9HddciIaXoCsKMv6RwnrxQrvkdpndCYdgasljB355enjXJDnjP3fwAFMO3vCnbPsdJ44jk6g2oaRkcPsFOA36f4Ddbd9cPJRgdzveZhsXdEphMDmJA5fJ6TX68uFkT0964l6C1IXP+xRN02cioYcrEQlt/n5xwDY3junWBxU',
        ],
        'split_img_dir' => '/var/shared/split_img',
        'logos_dir' => '/var/shared/logos',
        'imc'=>[
            'IMC_HOST'=>'http://ipo.imcparts.net/ipo121',
            'IMC_USERNAME'=> '1002262',
            'IMC_PASSWORD'=> 'suWa3esp',
            'IMC_PASSWORD'=> 'suWa3esp',
        ]
    ];

return $settings;