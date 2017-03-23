<?php namespace Despark\LaravelSocialFeeder;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use App;

class LaravelSocialFeederServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/config.php' => config_path('laravel-social-feeder.php'),
        ], 'config');

//        $this->publishes([
//            __DIR__ . '/../../migrations/' => database_path('migrations')
//        ], 'migrations');

        $this->loadMigrationsFrom(__DIR__.'/../../migrations');

        App::register('SammyK\LaravelFacebookSdk\LaravelFacebookSdkServiceProvider');

        AliasLoader::getInstance()->alias('SocialFeeder', 'Despark\LaravelSocialFeeder\SocialFeeder');
        AliasLoader::getInstance()->alias('Facebook', 'SammyK\LaravelFacebookSdk\FacebookFacade');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
