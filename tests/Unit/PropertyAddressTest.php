<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PropertyAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PropertyAddressTest extends TestCase
{
    /** @return iterable<string, array{array<string, string>, array<string, string>}> */
    public static function equivalentAddresses(): iterable
    {
        $canonical = [
            'phase' => 'Southville Phase I',
            'block' => '7',
            'lot' => '8',
            'house_number' => '12',
            'street' => 'Narra Street',
        ];

        yield 'capitalization and spacing' => [$canonical, [
            'phase' => ' southville   phase i ',
            'block' => ' 7 ',
            'lot' => '8',
            'house_number' => '12',
            'street' => ' narra   street ',
        ]];

        yield 'property prefixes' => [$canonical, [
            'phase' => 'Southville Phase I',
            'block' => 'Blk. 7',
            'lot' => 'Lot #8',
            'house_number' => 'No. 12',
            'street' => 'Narra St.',
        ]];

        yield 'short prefixes and punctuation' => [$canonical, [
            'phase' => 'SOUTHVILLE-PHASE I',
            'block' => 'B-7',
            'lot' => 'L 8',
            'house_number' => 'House #12',
            'street' => 'NARRA ST',
        ]];

        yield 'unicode compatibility characters' => [$canonical, [
            'phase' => 'Ｓｏｕｔｈｖｉｌｌｅ Ｐｈａｓｅ Ｉ',
            'block' => '７',
            'lot' => '８',
            'house_number' => '１２',
            'street' => 'Ｎａｒｒａ Ｓｔｒｅｅｔ',
        ]];
    }

    #[DataProvider('equivalentAddresses')]
    public function test_equivalent_address_formats_produce_the_same_key(array $first, array $second): void
    {
        $this->assertSame(PropertyAddress::key($first), PropertyAddress::key($second));
    }

    public function test_a_materially_different_address_produces_a_different_key(): void
    {
        $address = ['phase' => 'Southville Phase I', 'block' => '7', 'lot' => '8', 'house_number' => '12', 'street' => 'Narra Street'];

        $this->assertNotSame(PropertyAddress::key($address), PropertyAddress::key([...$address, 'lot' => '9']));
    }
}
