<?php
/**
 * Regression tests for CoversApiDbManager::getProductsWithoutSubtitle()
 * (feature product-subtitle T4 — advisory finding R3-orderby-missing from
 * review lineage review-97352617f150af09).
 *
 * The candidate query feeds scanSubtitles() in LIMIT batches. Without a
 * deterministic ORDER BY, MySQL may return rows in a storage-dependent
 * order, so permanently-unfillable candidates (e.g. an invalid EAN that
 * Dilve can never resolve) can re-occupy the same batch window forever and
 * starve fillable candidates. The query must order by ID ASC, before LIMIT.
 *
 * CoversApiDbManager.php executes
 * `require_once(ABSPATH . 'wp-admin/includes/image.php')` at file load, so
 * ABSPATH is defined once for the process the same way
 * DatabaseGuardCoversTest does. No WordPress function runs: a fake wpdb
 * captures the generated SQL. The manager is built with
 * newInstanceWithoutConstructor() because its constructor builds a
 * BiblioApi (WordPress-coupled); getProductsWithoutSubtitle() never touches
 * $this.
 */

namespace Tests\Unit\Covers\Api;

use Inc\Covers\Api\CoversApiDbManager;
use ReflectionClass;
use Tests\UnitTestCase;

class CoversApiDbManagerSubtitleQueryTest extends UnitTestCase
{
    /**
     * SQL captured by the fake wpdb::get_results().
     *
     * @var string
     */
    private string $captured_query = '';

    /**
     * Previous $GLOBALS['wpdb'] value, restored in tearDown.
     *
     * @var mixed
     */
    private $previous_wpdb;

    /**
     * Whether $GLOBALS['wpdb'] was set before the test.
     *
     * @var bool
     */
    private bool $had_wpdb;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->had_wpdb = isset($GLOBALS['wpdb']);
        $this->previous_wpdb = $GLOBALS['wpdb'] ?? null;

        if (!defined('ABSPATH')) {
            $wp_root = dirname(__DIR__, 7) . '/';
            if (!is_file($wp_root . 'wp-admin/includes/image.php')) {
                $this->markTestSkipped('WordPress root not found at ' . $wp_root);
            }
            define('ABSPATH', $wp_root);
        }

        // wpdb output-shape constant (wp-includes: define('ARRAY_A', 'ARRAY_A')),
        // referenced unqualified inside the Covers namespace at the
        // get_results() call. A constant, not a function — defining it does
        // not execute WordPress.
        if (!defined('ARRAY_A')) {
            define('ARRAY_A', 'ARRAY_A');
        }
    }

    /**
     * Tear down test fixtures.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->had_wpdb) {
            $GLOBALS['wpdb'] = $this->previous_wpdb;
        } else {
            unset($GLOBALS['wpdb']);
        }

        parent::tearDown();
    }

    /**
     * Record the SQL the method actually sent to wpdb.
     *
     * @param string $query Captured SQL.
     *
     * @return void
     */
    public function recordQuery(string $query): void
    {
        $this->captured_query = $query;
    }

    /**
     * Install a fake wpdb that records queries and returns an empty result
     * set, then build the manager without invoking its constructor.
     *
     * @return CoversApiDbManager
     */
    private function makeManagerCapturingQuery(): CoversApiDbManager
    {
        $recorder = $this;
        $wpdb = new class ($recorder) {
            public string $posts = 'wp_posts';
            public string $postmeta = 'wp_postmeta';
            public string $last_error = '';

            private object $recorder;

            public function __construct(object $recorder)
            {
                $this->recorder = $recorder;
            }

            public function prepare($query, ...$args)
            {
                // Minimal integer-only fake of wpdb::prepare(): substitute
                // %d placeholders in order. Sufficient for the LIMIT clause.
                return preg_replace_callback(
                    '/%d/',
                    static function () use (&$args) {
                        return (string) array_shift($args);
                    },
                    $query
                );
            }

            public function get_results($query, $output = null)
            {
                $this->recorder->recordQuery((string) $query);
                return [];
            }
        };
        $GLOBALS['wpdb'] = $wpdb;

        return (new ReflectionClass(CoversApiDbManager::class))->newInstanceWithoutConstructor();
    }

    /**
     * The unbatched candidate query must carry a deterministic
     * ORDER BY ID ASC (R3-orderby-missing).
     *
     * @return void
     */
    public function testCandidateQueryOrdersByIdAsc(): void
    {
        $manager = $this->makeManagerCapturingQuery();

        $manager->getProductsWithoutSubtitle(-1, 0);

        $this->assertStringContainsString('ORDER BY ID ASC', $this->captured_query);
    }

    /**
     * In the batched path the ORDER BY must precede the appended LIMIT
     * clause — SQL requires ORDER BY before LIMIT, and the batch window
     * must be sliced over a stable order.
     *
     * @return void
     */
    public function testOrderByPrecedesLimitInBatchedQuery(): void
    {
        $manager = $this->makeManagerCapturingQuery();

        $manager->getProductsWithoutSubtitle(100, 0);

        $order_position = stripos($this->captured_query, 'ORDER BY ID ASC');
        $limit_position = stripos($this->captured_query, 'LIMIT');

        $this->assertNotFalse($order_position, 'Batched query must contain ORDER BY ID ASC');
        $this->assertNotFalse($limit_position, 'Batched query must contain LIMIT');
        $this->assertLessThan($limit_position, $order_position, 'ORDER BY must precede LIMIT');
        $this->assertStringContainsString('LIMIT 0, 100', $this->captured_query);
    }
}
