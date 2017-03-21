<?php namespace Despark\LaravelSocialFeeder;

use Illuminate\Support\Facades\Log;
use Config;

use Facebook;

class SocialFeeder
{
    public static function fetchTwitterPosts()
    {
        $connection = new \Abraham\TwitterOAuth\TwitterOAuth(
            config('laravel-social-feeder::twitterCredentials.consumerKey'),
            config('laravel-social-feeder::twitterCredentials.consumerSecret'),
            config('laravel-social-feeder::twitterCredentials.accessToken'),
            config('laravel-social-feeder::twitterCredentials.accessTokenSecret')
        );
        $params = array(
            'screen_name' => config('laravel-social-feeder::twitterCredentials.screen_name'),
            'count' => config('laravel-social-feeder::twitterCredentials.limit'),
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
            $tweets = $connection->get('statuses/user_timeline', $params);
        } catch (Exception $e) {
            $tweets = array();
        }

        $outputs = array();

        foreach ($tweets as $tweet) {
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

            array_push($outputs, $newPostData);
        }
        return $outputs;
    }

    public static function fetchFacebookPosts()
    {
        $pageId = config('laravel-social-feeder::facebookCredentials.pageName');
        $limit = config('laravel-social-feeder::facebookCredentials.limit');

        // Get the name of the logged in user
        $appId = config('laravel-social-feeder::facebookCredentials.appId');
        $appSecret = config('laravel-social-feeder::facebookCredentials.appSecret');

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
        $outputs = array();

        foreach ($results as $post) {
            $message = $post->message ?? null;
            $imageUrl = $post->full_picture ?? null;

            $newPostData = array(
                'type' => 'facebook',
                'social_id' => $post->id,
                'url' => $post->permalink_url,
                'text' => $message,
                'image_url' => $imageUrl,
                'show_on_page' => 1,
                'published_at' => date('Y-m-d H:i:s', strtotime($post->created_time)),
            );

            array_push($outputs, $newPostData);
        }

        return $outputs;
    }

    public static function fetchInstagramPosts()
    {
        $lastInstagramPost = \SocialPost::type('instagram')->latest('published_at')->get()->first();
        $lastInstagramPostTimestamp = $lastInstagramPost ? strtotime($lastInstagramPost->published_at) : 0;

        //$clientId = config('laravel-social-feeder::instagramCredentials.clientId');
        $userId = config('laravel-social-feeder::instagramCredentials.userId');
        $accessToken = config('laravel-social-feeder::instagramCredentials.accessToken');
        $limit = config('laravel-social-feeder::instagramCredentials.limit');

        $url = 'https://api.instagram.com/v1/users/'.$userId.'/media/recent?access_token=' . $accessToken . "&count=" . $limit;
        ;
        $json = file_get_contents($url);

        $obj = json_decode($json);

        $postsData = $obj->data;

        $outputs = array();

        foreach ($postsData as $post) {
            if (!is_null($post->caption)) {
                if ($post->caption->created_time <= $lastInstagramPostTimestamp) {
                    continue;
                }

                $newPostData = array(
                    'type' => 'instagram',
                    'social_id' => $post->id,
                    'url' => $post->link,
                    'text' => $post->caption->text,
                    'image_url' => $post->images->standard_resolution->url,
                    'show_on_page' => 1,
                    'author_name' => $post->user->username,
                    'author_image_url' => $post->user->profile_picture,
                    'published_at' => date('Y-m-d H:i:s', $post->caption->created_time),
                );

                array_push($outputs, $newPostData);
            }
        }

        return $outputs;
    }
}
