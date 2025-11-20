<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastSettings extends Model
{
    protected $fillable = [
        'driver',
        'enabled',
        'pusher_app_id',
        'pusher_key',
        'pusher_secret',
        'pusher_cluster',
        'reverb_app_id',
        'reverb_key',
        'reverb_secret',
        'reverb_host',
        'reverb_port',
        'reverb_scheme',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'reverb_port' => 'integer',
    ];

    /**
     * Get the single instance of broadcast settings
     */
    public static function getInstance(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            [
                'driver' => 'log',
                'enabled' => false,
                'pusher_cluster' => 'mt1',
                'reverb_host' => 'localhost',
                'reverb_port' => 8080,
                'reverb_scheme' => 'http',
            ]
        );
    }

    /**
     * Check if broadcasting is enabled
     */
    public function isBroadcastingEnabled(): bool
    {
        return $this->enabled && in_array($this->driver, ['pusher', 'reverb']);
    }

    /**
     * Get broadcast configuration array
     */
    public function getBroadcastConfig(): array
    {
        if ($this->driver === 'pusher') {
            return [
                'driver' => 'pusher',
                'key' => $this->pusher_key,
                'secret' => $this->pusher_secret,
                'app_id' => $this->pusher_app_id,
                'options' => [
                    'cluster' => $this->pusher_cluster,
                    'useTLS' => true,
                ],
            ];
        }

        if ($this->driver === 'reverb') {
            return [
                'driver' => 'reverb',
                'key' => $this->reverb_key,
                'secret' => $this->reverb_secret,
                'app_id' => $this->reverb_app_id,
                'options' => [
                    'host' => $this->reverb_host,
                    'port' => $this->reverb_port,
                    'scheme' => $this->reverb_scheme,
                ],
            ];
        }

        return ['driver' => 'log'];
    }
}
