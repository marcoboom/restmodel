<?php

return [

    'default' => 'mailchimp',

    'connections'   =>  [

        'mailchimp' => [
            'url' => 'https://us9.api.mailchimp.com',
            'version' => '3.0',
            'options' => [
                'auth'  =>  ['mailchimp', '40c77377fbf4546ghf2b35a95f33d5-us9']
            ]
        ]
    ]
];
