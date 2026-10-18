<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\TimeGreeting;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TimeGreetingTest extends TestCase
{
    #[DataProvider('greetingHours')]
    public function test_it_returns_the_expected_greeting_for_each_hour(int $hour, string $expected): void
    {
        self::assertSame($expected, TimeGreeting::forHour($hour));
    }

    /** @return array<string, array{int, string}> */
    public static function greetingHours(): array
    {
        return [
            'late night' => [0, 'Good evening'],
            'early morning' => [4, 'Good evening'],
            'morning start' => [5, 'Good morning'],
            'morning end' => [11, 'Good morning'],
            'afternoon start' => [12, 'Good afternoon'],
            'afternoon end' => [16, 'Good afternoon'],
            'evening start' => [17, 'Good evening'],
            'late evening' => [23, 'Good evening'],
        ];
    }
}
