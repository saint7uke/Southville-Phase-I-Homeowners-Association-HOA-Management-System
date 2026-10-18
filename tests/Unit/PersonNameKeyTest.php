<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PersonName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PersonNameKeyTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function equivalentNames(): iterable
    {
        yield 'capitalization and whitespace' => ['  juan   dela cruz  '];
        yield 'punctuation' => ['Juan Dela-Cruz'];
        yield 'unicode compatibility' => ['Ｊｕａｎ Ｄｅｌａ Ｃｒｕｚ'];
    }

    #[DataProvider('equivalentNames')]
    public function test_equivalent_names_have_a_stable_key(string $name): void
    {
        $this->assertSame(PersonName::key('Juan Dela Cruz'), PersonName::key($name));
    }

    public function test_suffixes_remain_part_of_the_identity(): void
    {
        $this->assertNotSame(PersonName::key('Juan Dela Cruz'), PersonName::key('Juan Dela Cruz Jr.'));
    }
}
