<?php namespace Despark;

/**
 * Class SocialPost
 * @package Despark
 */
class SocialPost extends \Eloquent
{
    /**
     * @var string
     */
    protected $table = 'social_posts';

    /**
     * @var array
     */
    protected $fillable = ['type', 'title', 'text', 'social_id', 'url', 'image_url', 'show_on_page', 'published_at',];

    /**
     * @param $query
     * @param $type
     *
     * @return mixed
     */
    public function scopeType($query, $type)
    {
        return $query->whereType($type);
    }

    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeVisible($query)
    {
        return $query->where('show_on_page', '=', 1);
    }

    /**
     * @return array
     */
    public function getDates()
    {
        return ['published_at', 'created_at', 'updated_at'];
    }
}
