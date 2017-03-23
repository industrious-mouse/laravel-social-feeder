<?php namespace Despark\LaravelSocialFeeder;

use Despark\SocialPost;
use TwitterAPIExchange;

/**
 * Class SocialFeeder
 * @package Despark\LaravelSocialFeeder
 */
class SocialFeeder
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @param array $config
     */
    public function __construct($config = [])
    {
        $this->config = array_merge_recursive(config('laravel-social-feeder'), $config);
    }

    /**
     * @return array
     */
    public function fetchTwitterPosts()
    {
        $access_token = $this->getConfigValue('twitter.accessToken');
        $access_token_secret = $this->getConfigValue('twitter.accessTokenSecret');
        $consumer_secret = $this->getConfigValue('twitter.consumerSecret');
        $consumer_key = $this->getConfigValue('twitter.consumerKey');

        if(!$access_token || !$access_token_secret || !$consumer_secret || !$consumer_key)
        {
            return [];
        }

        $url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';

        $params = [
            'screen_name'       => $this->getConfigValue('twitter.screen_name'),
            'count'             => $this->getConfigValue('twitter.limit'),
            'exclude_replies'   => $this->getConfigValue('twitter.exclude_replies'),
            'include_rts'       => $this->getConfigValue('twitter.include_rts')
        ];

        $lastTwitterPost = SocialPost::type('twitter')
            ->latest('published_at')
            ->limit('1')
            ->get()
            ->first();

        if ($lastTwitterPost) {
            $params['since_id'] = $lastTwitterPost->social_id;
        }

        try {
            $client = new TwitterAPIExchange([
                'oauth_access_token'        => $access_token,
                'oauth_access_token_secret' => $access_token_secret,
                'consumer_key'              => $consumer_key,
                'consumer_secret'           => $consumer_secret
            ]);

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
                'url' => 'https://twitter.com/statuses/' . $tweet->id_str,
                'text' => $tweet->text,
                'show_on_page' => 1,
                'image_url' => isset($tweet->entities->media) && is_array($tweet->entities->media) ? $tweet->entities->media[0]->media_url : null,
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
    public function fetchFacebookPosts()
    {
        $pageId = $this->getConfigValue('facebook.pageName');
        $limit = $this->getConfigValue('facebook.limit');

        // Get the name of the logged in user
        $appId = $this->getConfigValue('facebook.appId');
        $appSecret = $this->getConfigValue('facebook.appSecret');

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
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => ['Content-type: application/json']
        ];

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
    public function fetchInstagramPosts()
    {
        $access_token = $this->getConfigValue('instagram.accessToken');

        if(!$access_token)
        {
            return [];
        }

        $lastInstagramPost = \SocialPost::type('instagram')->latest('published_at')->get()->first();

        $lastInstagramPostTimestamp = $lastInstagramPost
            ? strtotime($lastInstagramPost->published_at)
            : 0;


        $url = 'https://api.instagram.com/v1/users/self/media/recent/?' . http_build_query([
            'access_token' => $access_token,
            'count' => $this->getConfigValue('instagram.limit')
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

    /**
     * @param array $config
     */
    public function setConfig($config = [])
    {
        $config = (array) $config;

        foreach($config as $key => $value)
        {
            $this->config[$key] = array_merge($this->config[$key], $value);
        }
    }

    /**
     * Gets a config value from the array (with dotted notation)
     *
     * @param $field
     * @return mixed
     */
    private function getConfigValue($field)
    {
        return array_get($this->config, $field);
    }
}
