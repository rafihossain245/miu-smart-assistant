<?php

namespace App\Events;

use App\Models\Source;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SourceCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $source;

    /**
     * Create a new event instance.
     */
    public function __construct(Source $source)
    {
        $this->source = $source->load('chatbot');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chatbot.' . $this->source->chatbot_id)
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'source.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'source' => [
                'id' => $this->source->id,
                'chatbot_id' => $this->source->chatbot_id,
                'type' => $this->source->type,
                'title' => $this->source->title,
                'url' => $this->source->url,
                'content' => $this->source->content ? substr($this->source->content, 0, 100) . '...' : null,
                'status' => $this->source->status,
                'chunk_index' => $this->source->chunk_index,
                'total_chunks' => $this->source->total_chunks,
                'parent_source_id' => $this->source->parent_source_id,
                'created_at' => $this->source->created_at?->toISOString(),
                'updated_at' => $this->source->updated_at?->toISOString(),
            ]
        ];
    }
}