<?php

namespace App\Providers;

use App\Firebase\FirebaseProjectManager;
use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;
use Kreait\Laravel\Firebase\FirebaseProjectManager as KreaitFirebaseProjectManager;
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
