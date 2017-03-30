<?php

return [
    'twitter' => [
        'consumerKey' => env('SOCIAL_TWITTER_CONSUMER_KEY'),
        'consumerSecret' => env('SOCIAL_TWITTER_CONSUMER_SECRET'),
        'accessToken' => env('SOCIAL_TWITTER_ACCESS_TOKEN'),
        'accessTokenSecret' => env('SOCIAL_TWITTER_ACCESS_TOKEN_SECRET'),

        'screen_name' => '',
        'limit' => 10,
        'exclude_replies' => true,
        'include_rts' => true,
    ],

    'facebook' => [
        'accessToken' => env('SOCIAL_FACEBOOK_ACCESS_TOKEN'),
        'pageName' => env('SOCIAL_FACEBOOK_PAGE_NAME'),
        'limit' => 10,
    ],

    'instagram' => [
        'accessToken' => env('SOCIAL_INSTAGRAM_ACCESS_TOKEN'),
        'limit' => 10,
    ],
];
