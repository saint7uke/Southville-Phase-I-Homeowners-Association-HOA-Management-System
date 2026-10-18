<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CertificateTypeName;
use PHPUnit\Framework\TestCase;

final class CertificateTypeNameTest extends TestCase
{
    public function test_keys_ignore_unicode_case_and_whitespace_formatting(): void
    {
        $this->assertSame(
            CertificateTypeName::key("  Certificate\u{00A0}of   Café "),
            CertificateTypeName::key("certificate of Cafe\u{0301}"),
        );
    }

    public function test_storage_normalization_preserves_display_case_and_meaningful_punctuation(): void
    {
        $this->assertSame('HOA Clearance - Special', CertificateTypeName::normalize('  HOA   Clearance - Special  '));
        $this->assertNotSame(
            CertificateTypeName::key('HOA Clearance'),
            CertificateTypeName::key('HOA-Clearance'),
        );
    }
}
