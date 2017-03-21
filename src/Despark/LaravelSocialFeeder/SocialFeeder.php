<?php namespace Despark\LaravelSocialFeeder;

use TwitterAPIExchange;

/**
 * Class SocialFeeder
 * @package Despark\LaravelSocialFeeder
 */
class SocialFeeder
{
    /**
     * @return array
     */
    public static function fetchTwitterPosts()
    {
        $url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';

        $client = new TwitterAPIExchange([
            'oauth_access_token'        => config('laravel-social-feeder.twitter.accessToken'),
            'oauth_access_token_secret' => config('laravel-social-feeder.twitter.accessTokenSecret'),
            'consumer_key'              => config('laravel-social-feeder.twitter.consumerKey'),
            'consumer_secret'           => config('laravel-social-feeder.twitter.consumerSecret')
        ]);

        $params = array(
            'screen_name'       => config('laravel-social-feeder.twitter.screen_name'),
            'count'             => config('laravel-social-feeder.twitter.limit'),
            'exclude_replies'   => config('laravel-social-feeder.twitter.exclude_replies', true),
            'include_rts'       => config('laravel-social-feeder.twitter.include_rts', true)
        );

        $lastTwitterPost = \SocialPost::type('twitter')
            ->latest('published_at')
            ->limit('1')
            ->get()
            ->first();

        if ($lastTwitterPost) {
            $params['since_id'] = $lastTwitterPost->social_id;
        }

        try {
            $tweets = $client
                ->setGetfield('?' . http_build_query($params))
                ->buildOauth($url, 'GET')
                ->performRequest();
        } catch (\Exception $e) {
            $tweets = json_encode([]);
        }

        $data = [];

        foreach (json_decode($tweets) as $tweet) {
            if (! is_object($tweet)) {
                continue;
            }

            $newPostData = [
                'type' => 'twitter',
                'social_id' => $tweet->id_str,
                'url' => 'https://twitter.com/'.$params['screen_name'].'/status/'.$tweet->id_str,
                'text' => $tweet->text,
                'show_on_page' => 1,
                'author_name' => $tweet->user->name,
                'author_image_url' => $tweet->user->profile_image_url,
                'published_at' => date('Y-m-d H:i:s', strtotime($tweet->created_at)),
            ];

            array_push($data, $newPostData);
        }

        return $data;
    }

    /**
     * @return array
     */
    public static function fetchFacebookPosts()
    {
        $pageId = config('laravel-social-feeder.facebook.pageName');
        $limit = config('laravel-social-feeder.facebook.limit');

        // Get the name of the logged in user
        $appId = config('laravel-social-feeder.facebook.appId');
        $appSecret = config('laravel-social-feeder.facebook.appSecret');

        $scope = [
            'full_picture',
            'from{name,picture}',
            'message',
            'created_time',
            'id',
            'permalink_url'
        ];

        $url = 'https://graph.facebook.com/' . $pageId . '/feed?' . http_build_query([
            'fields' => implode(',', $scope),
            'limit' => $limit,
            'access_token' => $appId . '|' . $appSecret
        ]);

        // Initializing curl
        $ch = curl_init($url);

        // Configuring curl options
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array('Content-type: application/json')
        );

        // Setting curl options
        curl_setopt_array($ch, $options);

        // Getting results
        $results = curl_exec($ch); // Getting jSON result string
        $results = json_decode($results);
        $results = $results->data;
        $data = [];

        foreach ($results as $post) {
            $message = $post->message ?? null;
            $imageUrl = $post->full_picture ?? null;

            $newPostData = [
                'type' => 'facebook',
                'social_id' => $post->id,
                'url' => $post->permalink_url,
                'text' => $message,
                'image_url' => $imageUrl,
                'show_on_page' => 1,
                'published_at' => date('Y-m-d H:i:s', strtotime($post->created_time)),
            ];

            array_push($data, $newPostData);
        }

        return $data;
    }

    /**
     * @return array
     */
    public static function fetchInstagramPosts()
    {
        $lastInstagramPost = \SocialPost::type('instagram')->latest('published_at')->get()->first();

        $lastInstagramPostTimestamp = $lastInstagramPost
            ? strtotime($lastInstagramPost->published_at)
            : 0;

        $url = 'https://api.instagram.com/v1/users/self/media/recent/?' . http_build_query([
            'access_token' => config('laravel-social-feeder.instagram.accessToken'),
            'count' => config('laravel-social-feeder.instagram.limit')
        ]);

        $json = file_get_contents($url);

        $obj = json_decode($json);
        $postsData = $obj->data;

        $data = [];

        foreach ($postsData as $post) {
            if (!is_null($post->caption)) {
                if ($post->caption->created_time <= $lastInstagramPostTimestamp) {
                    continue;
                }

                $newPostData = [
                    'type' => 'instagram',
                    'social_id' => $post->id,
                    'url' => $post->link,
                    'text' => $post->caption->text,
                    'image_url' => $post->images->standard_resolution->url,
                    'show_on_page' => 1,
                    'author_name' => $post->user->username,
                    'author_image_url' => $post->user->profile_picture,
                    'published_at' => date('Y-m-d H:i:s', $post->caption->created_time),
                ];

                array_push($data, $newPostData);
            }
        }

        return $data;
    }
}
