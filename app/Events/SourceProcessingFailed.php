<?php

namespace App\Events;

use App\Models\Source;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SourceProcessingFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $source;

    /**
     * Create a new event instance.
     */
    public function __construct(Source $source)
    {
        $this->source = $source;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chatbot.' . $this->source->chatbot_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'source.processing.failed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'source' => [
                'id' => $this->source->id,
                'title' => $this->source->title,
                'type' => $this->source->type,
                'status' => $this->source->status,
                'url' => $this->source->url,
                'error_message' => $this->source->error_message,
            ],
            'message' => 'Source processing failed: ' . ($this->source->error_message ?? 'Unknown error'),
        ];
    }
}
