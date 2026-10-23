<?php
/**
 * Characterization tests for CoversApi::parseOnixTitles() (CoversApi.php:226),
 * the pure ONIX seam extracted by T3 of the product-subtitle feature.
 *
 * The fixture is a hand-authored, minimal synthetic ONIX snippet (three
 * <Product> elements) — deliberately NOT a recorded vendor response, so no
 * credential-bearing URL or real response body can leak into the test tree.
 *
 * CoversApi's constructor calls get_option() and new BiblioApi (both
 * WordPress-coupled), so instances are built with
 * ReflectionClass::newInstanceWithoutConstructor() — the same pattern used by
 * DatabaseGuardCoversTest. parseOnixTitles() never touches $this, so skipping
 * the constructor is safe.
 *
 * Documented Unit-testability exception (feature product-subtitle, T4):
 * the Dilve fill-empty guard ("scan leaves a non-empty _subtitle untouched")
 * lives inside CoversApi::scanSubtitles(), which calls get_post_meta() /
 * update_post_meta() at write time. Those WordPress functions are never
 * executed in the Unit suite, and no new production seam may be added
 * post-review, so that behavior is covered by manual/cron verification only.
 */

namespace Tests\Unit\Covers\Api;

use Inc\Covers\Api\CoversApi;
use ReflectionClass;
use Tests\UnitTestCase;

class CoversApiParseOnixTitlesTest extends UnitTestCase
{
    /**
     * Load the synthetic ONIX fixture and return the <Products> element.
     *
     * @return \SimpleXMLElement
     */
    private function loadFixture(): \SimpleXMLElement
    {
        $xml = simplexml_load_string(
            (string) file_get_contents(__DIR__ . '/../Fixtures/onix-product-titles.xml')
        );
        $this->assertNotFalse($xml, 'Fixture must parse as XML');
        return $xml;
    }

    /**
     * Build a CoversApi without invoking its WordPress-coupled constructor.
     *
     * @return CoversApi
     */
    private function makeApiWithoutConstructor(): CoversApi
    {
        return (new ReflectionClass(CoversApi::class))->newInstanceWithoutConstructor();
    }

    /**
     * Case (a): TitleType 01 with TitleText + Subtitle returns both keys.
     * The same <Product> also proves case (d): the TitleType 01 element is
     * NOT the first <Title> child and must still be found.
     *
     * @return void
     */
    public function testReturnsTitleAndSubtitleForMainTitleType01(): void
    {
        $fixture = $this->loadFixture();
        $api = $this->makeApiWithoutConstructor();

        $titles = $api->parseOnixTitles($fixture->Product[0]);

        $this->assertSame(
            [
                'title' => 'Main Title With Subtitle',
                'subtitle' => 'A Synthetic Subtitle',
            ],
            $titles
        );
    }

    /**
     * Case (b): TitleType 01 with TitleText but no <Subtitle> element
     * returns only the 'title' key (no empty-string subtitle).
     *
     * @return void
     */
    public function testReturnsOnlyTitleKeyWhenSubtitleElementMissing(): void
    {
        $fixture = $this->loadFixture();
        $api = $this->makeApiWithoutConstructor();

        $titles = $api->parseOnixTitles($fixture->Product[1]);

        $this->assertSame(['title' => 'Main Title Without Subtitle'], $titles);
        $this->assertArrayNotHasKey('subtitle', $titles);
    }

    /**
     * Case (c): a <Product> whose only <Title> elements have a non-01
     * TitleType yields an empty array (filtered out).
     *
     * @return void
     */
    public function testReturnsEmptyArrayWhenNoTitleType01(): void
    {
        $fixture = $this->loadFixture();
        $api = $this->makeApiWithoutConstructor();

        $this->assertSame([], $api->parseOnixTitles($fixture->Product[2]));
    }

    /**
     * Case (d): TitleType 01 appearing after a non-01 Title element is
     * still found and its value wins (the fixture's first Product has a
     * TitleType 05 element before the 01 element).
     *
     * @return void
     */
    public function testFindsTitleType01WhenNotFirstTitleElement(): void
    {
        $fixture = $this->loadFixture();
        $api = $this->makeApiWithoutConstructor();

        $titles = $api->parseOnixTitles($fixture->Product[0]);

        // The distinctive-title (05) text must NOT leak into the result.
        $this->assertSame('Main Title With Subtitle', $titles['title']);
        $this->assertNotContains('Distinctive Title (non-main)', $titles);
    }
}
