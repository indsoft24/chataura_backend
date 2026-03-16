<?php

namespace App\Providers;

use App\Firebase\FirebaseProjectManager;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;
use Kreait\Laravel\Firebase\FirebaseProjectManager as KreaitFirebaseProjectManager;
use League\Flysystem\Filesystem;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Use custom FirebaseProjectManager so project_id from config is applied (avoids "Unable to determine the Firebase Project ID")
        $this->app->singleton(KreaitFirebaseProjectManager::class, FirebaseProjectManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone'));

        Storage::extend('bunnycdn', function ($app, $config) {
            $region = $config['region'] ?? BunnyCDNRegion::DEFAULT;
            $client = new BunnyCDNClient(
                $config['storage_zone'],
                $config['api_key'],
                $region === '' ? BunnyCDNRegion::DEFAULT : $region
            );
            $adapter = new BunnyCDNAdapter($client, $config['pull_zone'] ?? '');
            $flysystem = new Filesystem($adapter);

            return new FilesystemAdapter($flysystem, $adapter, $config);
        });

        // Apply config/mail.php "stream.ssl" (e.g. cafile) to SMTP transport; Laravel does not pass it by default.
        $this->app->make(MailManager::class)->extend('smtp', function ($config) {
            $manager = $this->app->make(MailManager::class);
            $ref = new \ReflectionMethod($manager, 'createSmtpTransport');
            $ref->setAccessible(true);
            $transport = $ref->invoke($manager, $config);

            if (! empty($config['stream']['ssl'] ?? [])) {
                $stream = $transport->getStream();
                if ($stream instanceof SocketStream) {
                    $opts = $stream->getStreamOptions();
                    $opts['ssl'] = array_merge($opts['ssl'] ?? [], $config['stream']['ssl']);
                    $stream->setStreamOptions($opts);
                }
            }

            return $transport;
        });
    }
}
