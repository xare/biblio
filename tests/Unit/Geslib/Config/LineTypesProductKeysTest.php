<?php
/**
 * Characterization tests for the GP4 wire-column → JSON-key mapping of the
 * product-subtitle feature (product-subtitle T4).
 *
 * LineTypes::PRODUCT_KEYS is a pure class constant (no WordPress function,
 * reachable through Composer autoload alone), so it is the Unit-reachable
 * seam of the GP4 pipeline: readLine() explodes the pipe-delimited line and
 * pops the empty field left by the mandatory trailing '|', then
 * processGP4() array_combine()s the remaining 62 fields with PRODUCT_KEYS;
 * GeslibApiDbProductsManager later reads the combined array as
 * $content['subtitulo'] (storeProduct:99 / batchStoreProducts:711).
 *
 * Documented Unit-testability exception (feature product-subtitle, T4):
 * the clear-on-empty meta write itself (delete_post_meta / $wpdb->delete of
 * the _subtitle row inside GeslibApiDbProductsManager) executes WordPress
 * functions and cannot run in the Unit suite; no new production seam may be
 * added post-review. This test therefore pins the pure mapping contract
 * (wire column 26 == PRODUCT_KEYS[26] == JSON key 'subtitulo', 62 fields)
 * that both writers depend on.
 */

namespace Tests\Unit\Geslib\Config;

use Inc\Geslib\Config\LineTypes;
use Tests\UnitTestCase;

class LineTypesProductKeysTest extends UnitTestCase
{
    /**
     * A GP4 product line carries exactly 62 fields; array_combine() throws
     * on any mismatch, so the key count is load-bearing.
     *
     * @return void
     */
    public function testProductKeysHasExactlySixtyTwoFields(): void
    {
        $this->assertCount(62, LineTypes::PRODUCT_KEYS);
    }

    /**
     * The subtitle wire column is 0-based index 26 of the GP4 line.
     *
     * @return void
     */
    public function testSubtitleIsWireColumnTwentySix(): void
    {
        $this->assertSame('subtitulo', LineTypes::PRODUCT_KEYS[26]);
    }

    /**
     * End-to-end pure mapping: a synthetic 62-field GP4 line (with the
     * mandatory trailing '|') combines, via the readLine()+processGP4()
     * explode/pop/array_combine chain, so the wire value at column 26 lands
     * under the JSON key 'subtitulo'.
     *
     * @return void
     */
    public function testSyntheticGp4LineCombinesToSubtituloKey(): void
    {
        $fields = [];
        for ($i = 0; $i < 62; $i++) {
            $fields[] = 'v' . $i;
        }
        $fields[26] = 'Synthetic Subtitle Value';

        // Mirror GeslibApiLines::readLine(): explode on '|', then pop the
        // empty trailing element left by the mandatory trailing pipe.
        $line = implode('|', $fields) . '|';
        $data = explode('|', $line);
        array_pop($data);

        $this->assertCount(62, $data, 'Trailing pipe must yield exactly 62 fields after the pop');

        $combined = array_combine(LineTypes::PRODUCT_KEYS, $data);
        $this->assertIsArray($combined);
        $this->assertSame('Synthetic Subtitle Value', $combined['subtitulo']);
    }
}
