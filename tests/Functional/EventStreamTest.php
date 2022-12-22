<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection\Tests\Functional;

use Chronhub\Testing\OrchestraTestCase;
use Illuminate\Database\Eloquent\Model;
use Chronhub\Store\Connection\EventStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Chronhub\Contracts\Chronicler\EventStreamModel;
use Chronhub\Contracts\Chronicler\EventStreamProvider;

final class EventStreamTest extends OrchestraTestCase
{
    use RefreshDatabase;

    private string $tableName = 'customer';

    private EventStream|EventStreamModel|EventStreamProvider|Model $eventStream;

    public function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../../database');

        $this->eventStream = new EventStream();
    }

    /**
     * @test
     */
    public function it_create_event_stream(): void
    {
        $streamName = 'transaction';

        $category = null;

        $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

        $this->eventStream->createStream($streamName, $this->tableName, $category);

        $this->assertTrue($this->eventStream->hasRealStreamName($streamName));

        /** @var EventStream $model */
        $model = $this->eventStream->newQuery()->find(1);

        $this->assertEquals($this->tableName, $model->tableName());

        $this->assertEquals('transaction', $model->realStreamName());

        $this->assertNull($model->category());

        /** @var EventStream $model */
        $model = $this->eventStream->newQuery()->find(1);

        $this->assertFalse($model->timestamps);

        $this->assertEquals('event_streams', $model->getTable());
    }

    /**
     * @test
     */
    public function it_create_event_stream_with_category(): void
    {
        $streamName = 'add';

        $category = 'transaction';

        $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

        $this->eventStream->createStream($streamName, $this->tableName, $category);

        $this->assertTrue($this->eventStream->hasRealStreamName($streamName));

        /** @var EventStream $model */
        $model = $this->eventStream->newQuery()->find(1);

        $this->assertEquals($this->tableName, $model->tableName());
        $this->assertEquals('add', $model->realStreamName());
        $this->assertEquals('transaction', $model->category());
    }

    /**
     * @test
     */
    public function it_delete_event_stream_by_stream_name(): void
    {
        $streamName = 'transaction';

        $category = null;

        $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

        $this->eventStream->createStream($streamName, $this->tableName, $category);

        $this->assertTrue($this->eventStream->hasRealStreamName($streamName));

        $deleted = $this->eventStream->deleteStream($streamName);

        $this->assertTrue($deleted);
        $this->assertFalse($this->eventStream->hasRealStreamName($streamName));
    }

    /**
     * @test
     */
    public function it_filter_and_order_by_stream_names(): void
    {
        $category = null;
        $streamNames = ['transaction_add', 'transaction_subtract', 'transaction_divide'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

            $this->eventStream->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->eventStream->hasRealStreamName($streamName));
        }

        $expectedStreamNames = ['transaction_add', 'transaction_divide', 'transaction_subtract'];

        $this->assertEquals($expectedStreamNames, $this->eventStream->filterByStreams($streamNames));
    }

    /**
     * @test
     */
    public function it_filter_and_order_by_stream_names_2(): void
    {
        $category = null;
        $streamNames = ['transaction_add', 'transaction_subtract', 'transaction_divide'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

            $this->eventStream->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->eventStream->hasRealStreamName($streamName));
        }

        $streamNames[] = 'foo';
        $streamNames[] = 'bar';

        $expectedStreamNames = ['transaction_add', 'transaction_divide', 'transaction_subtract'];

        $this->assertEquals($expectedStreamNames, $this->eventStream->filterByStreams($streamNames));
    }

    /**
     * @test
     */
    public function it_filter_and_order_by_stream_names_3(): void
    {
        $category = null;
        $streamNames = ['transaction_add', 'transaction_subtract', 'transaction_divide'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

            $this->eventStream->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->eventStream->hasRealStreamName($streamName));
        }

        $this->eventStream->createStream('foo', 'foo_table', $category);
        $this->eventStream->createStream('bar', 'bar_table', $category);

        $expectedStreamNames = ['transaction_add', 'transaction_divide', 'transaction_subtract'];

        $this->assertEquals($expectedStreamNames, $this->eventStream->filterByStreams($streamNames));
    }

    /**
     * @test
     */
    public function it_filter_by_categories(): void
    {
        $category = 'transaction';
        $streamNames = ['add', 'subtract', 'divide'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

            $this->eventStream->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->eventStream->hasRealStreamName($streamName));
        }

        $expectedCategories = ['add', 'divide', 'subtract'];

        $this->assertEquals($expectedCategories, $this->eventStream->filterByCategories(['transaction']));
    }

    /**
     * @test
     */
    public function it_filter_by_categories_2(): void
    {
        $category = 'transaction';
        $streamNames = ['add', 'subtract', 'divide'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

            $this->eventStream->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->eventStream->hasRealStreamName($streamName));
        }

        $this->eventStream->createStream('operation', $this->tableName);

        $expectedCategories = ['add', 'divide', 'subtract'];

        $this->assertEquals($expectedCategories, $this->eventStream->filterByCategories(['transaction']));
    }

    /**
     * @test
     */
    public function it_fetch_all_stream_without_internal_stream_beginning_with_dollar_sign(): void
    {
        $category = 'transaction';
        $streamNames = ['add', 'divide', 'subtract'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

            $this->eventStream->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->eventStream->hasRealStreamName($streamName));
        }

        $this->eventStream->createStream('operation', $this->tableName);

        $this->eventStream->createStream('$all', $this->tableName);

        $expectedCategories = ['add', 'divide', 'subtract'];

        $this->assertEquals($expectedCategories, $this->eventStream->filterByCategories(['transaction']));

        $this->assertEquals($streamNames, $this->eventStream->filterByStreams($streamNames));

        $this->assertEquals([
            'add', 'divide', 'operation', 'subtract',
        ], $this->eventStream->allWithoutInternal());
    }
}
