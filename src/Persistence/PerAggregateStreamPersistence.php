<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Persistence;

use Illuminate\Support\Facades\Schema;
use Chronhub\Contracts\Stream\StreamName;
use Illuminate\Database\Schema\Blueprint;
use Chronhub\Contracts\Message\DomainEvent;
use Chronhub\Contracts\Store\StreamPersistence;
use Chronhub\Contracts\Support\Serializer\StreamEventConverter;

final class PerAggregateStreamPersistence implements StreamPersistence
{
    public function __construct(private readonly StreamEventConverter $convertEvent)
    {
    }

    public function tableName(StreamName $streamName): string
    {
        return '_'.$streamName->name();
    }

    public function up(string $tableName): ?callable
    {
        Schema::create($tableName, static function (Blueprint $table): void {
            $table->id('no');
            $table->uuid('event_id');
            $table->string('event_type');
            $table->json('content');
            $table->jsonb('headers');
            $table->uuid('aggregate_id');
            $table->string('aggregate_type');
            $table->bigInteger('aggregate_version')->unique();
            $table->timestampTz('created_at', 6);
        });

        return null;
    }

    public function serializeEvent(DomainEvent $event): array
    {
        return $this->convertEvent->toArray($event, false);
    }

    public function isAutoIncremented(): bool
    {
        return false;
    }

    public function indexName(string $tableName): ?string
    {
        return null;
    }
}
