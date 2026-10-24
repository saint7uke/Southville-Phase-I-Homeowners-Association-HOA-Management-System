<?php

declare(strict_types=1);

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicUiPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_removes_only_the_requested_footer_text_and_trailing_separator(): void
    {
        foreach (['en', 'fil'] as $locale) {
            $response = $this->withSession(['locale' => $locale])->get('/login')->assertOk();
            $dom = $this->html($response->getContent());
            $navigation = '//nav[contains(@class,"hoa-portal-auth-links")]';
            $this->assertSame(2, $dom->query($navigation.'/a')->length);
            $this->assertSame(1, $dom->query($navigation.'/span[@aria-hidden="true"]')->length);
            $this->assertSame(route('home'), $dom->query($navigation.'/*[last()]')->item(0)->getAttribute('href'));
            $this->assertStringNotContainsString('For homeowners, staff, and administrators', $response->getContent());
            $this->assertStringNotContainsString('Para sa mga residente, kawani, at administrador', $response->getContent());
            $response->assertSee('action="'.route('login.store').'"', false)->assertSee('name="email"', false)->assertSee('name="password"', false);
        }
    }

    public function test_registration_uses_the_official_decorative_logo_in_the_existing_badge(): void
    {
        foreach (['en', 'fil'] as $locale) {
            $response = $this->withSession(['locale' => $locale])->get('/homeowner/register')->assertOk();
            $dom = $this->html($response->getContent());
            $badge = '//a[@class="hoa-auth-brand"]/span[contains(@class,"hoa-brand-mark")]';
            $this->assertSame('', trim($dom->query($badge)->item(0)->textContent));
            $this->assertSame('true', $dom->query($badge)->item(0)->getAttribute('aria-hidden'));
            $image = $dom->query($badge.'/img')->item(0);
            $this->assertSame(asset('images/HOA.png'), $image->getAttribute('src'));
            $this->assertSame('', $image->getAttribute('alt'));
            $this->assertSame('48', $image->getAttribute('width'));
            $this->assertSame('48', $image->getAttribute('height'));
            $response->assertSee('data-review-registration', false)->assertSee('name="phase_suffix"', false)->assertDontSee('name="house_number"', false);
        }
        $this->assertFileExists(public_path('images/HOA.png'));
    }

    private function html(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
