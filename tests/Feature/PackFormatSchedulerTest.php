<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class PackFormatSchedulerTest extends TestCase
{
    public function test_pack_format_sync_is_scheduled_daily_without_overlapping(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();

        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'pack-formats:sync'));

        $this->assertCount(1, $events);

        $event = $events->first();

        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame('0 0 * * *', $event->expression);
    }
}
