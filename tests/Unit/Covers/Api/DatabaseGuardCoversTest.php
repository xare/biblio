<?php
/**
 * Characterization tests for the DatabaseGuard sweep over the Covers DB managers.
 *
 * Before the sweep, every `try { $wpdb->... } catch (\Exception)` in these
 * managers was dead code (wpdb never throws): insertLinesData returned a
 * stale $wpdb->insert_id on a failed insert (feeding set_url_origin(int)
 * with the wrong row), and the `: bool` setters returned true even when the
 * update failed. These tests prove the core wins: a failing $wpdb call now
 * returns false and logs through the injected logger, and a healthy
 * 0-affected-rows update still returns true.
 */

namespace Tests\Unit\Covers\Api;

use Inc\Biblio\Api\DatabaseGuard;
use Inc\Biblio\Api\LoggerInterface;
use Inc\Covers\Api\CoversApiDbLinesManager;
use Inc\Covers\Api\CoversApiDbLogManager;
use Mockery;
use ReflectionClass;
use Tests\UnitTestCase;

class DatabaseGuardCoversTest extends UnitTestCase
{
    /**
     * Previous $GLOBALS['wpdb'] value, restored in tearDown
     *
     * @var mixed
     */
    private $previous_wpdb;

    /**
     * Whether $GLOBALS['wpdb'] was set before the test
     *
     * @var bool
     */
    private bool $had_wpdb;

    /**
     * Set up test fixtures
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->had_wpdb = isset($GLOBALS['wpdb']);
        $this->previous_wpdb = $GLOBALS['wpdb'] ?? null;

        // CoversApiDbManager.php executes
        // `require_once(ABSPATH . 'wp-admin/includes/image.php')` at file
        // load, so the class cannot be autoloaded without ABSPATH. The real
        // WordPress root (the directory containing wp-content, seven levels
        // above this test file) is detected and
        // ABSPATH is defined once for the process. If it is not there, the
        // test is skipped honestly instead of faking a green.
        if ( ! defined( 'ABSPATH' ) ) {
            $wp_root = dirname( __DIR__, 7 ) . '/';
            if ( ! is_file( $wp_root . 'wp-admin/includes/image.php' ) ) {
                $this->markTestSkipped( 'WordPress root not found at ' . $wp_root );
            }
            define( 'ABSPATH', $wp_root );
        }
    }

    /**
     * Tear down test fixtures
     *
     * @return void
     */
    protected function tearDown(): void
    {
        DatabaseGuard::setLogger( null );

        if ( $this->had_wpdb ) {
            $GLOBALS['wpdb'] = $this->previous_wpdb;
        } else {
            unset( $GLOBALS['wpdb'] );
        }

        parent::tearDown();
    }

    /**
     * Build a manager instance without invoking its constructor.
     *
     * The constructors call `new BiblioApi` → `new FileLogger` → `wp_mkdir_p()`,
     * none of which exist in the unit-test environment. The swept methods under
     * test never touch $this->biblioApi on the paths exercised here
     * (DatabaseGuard routes all failure logging through the injected mock),
     * so skipping the constructor is safe and keeps the test from writing to
     * the real logs/ directory.
     *
     * @param class-string $class Fully-qualified manager class name.
     *
     * @return object
     */
    private function makeManagerWithoutConstructor( string $class ): object
    {
        return ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor();
    }

    /**
     * Install a fake $wpdb whose get_var reports no existing line (healthy,
     * empty result) and whose insert fails like real wpdb does: return false
     * and populate last_error.
     *
     * @param string $last_error Error message wpdb would have stored.
     *
     * @return void
     */
    private function installFailingInsertWpdb( string $last_error = 'Duplicate entry for key PRIMARY' ): void
    {
        $wpdb = new class {
            public string $prefix = 'wp_';
            public string $last_error = '';

            public function get_var( $query )
            {
                return null; // no existing line for the ISBN; healthy
            }

            public function insert( $table, $data, $format = null )
            {
                $this->last_error = 'Duplicate entry for key PRIMARY';
                return false;
            }
        };
        $wpdb->last_error = $last_error;
        $GLOBALS['wpdb'] = $wpdb;
    }

    /**
     * Install a fake $wpdb whose update reports a healthy $affected_rows with
     * an empty last_error — the state real wpdb leaves after a successful
     * statement that touched 0 rows (nothing matched, values identical).
     *
     * @param int $affected_rows Row count the fake update returns.
     *
     * @return void
     */
    private function installHealthyRowcountWpdb( int $affected_rows = 0 ): void
    {
        $wpdb = new class( $affected_rows ) {
            public string $prefix = 'wp_';
            public string $last_error = '';

            private int $affected_rows;

            public function __construct( int $affected_rows )
            {
                $this->affected_rows = $affected_rows;
            }

            public function update( $table, $data, $where, $format = null, $where_format = null )
            {
                return $this->affected_rows;
            }
        };
        $GLOBALS['wpdb'] = $wpdb;
    }

    /**
     * Install a fake $wpdb whose update fails like real wpdb does: return
     * false and populate last_error.
     *
     * @param string $last_error Error message wpdb would have stored.
     *
     * @return void
     */
    private function installFailingUpdateWpdb( string $last_error = 'Duplicate entry for key PRIMARY' ): void
    {
        $wpdb = new class {
            public string $prefix = 'wp_';
            public string $last_error = '';

            public function update( $table, $data, $where, $format = null, $where_format = null )
            {
                $this->last_error = 'Duplicate entry for key PRIMARY';
                return false;
            }
        };
        $wpdb->last_error = $last_error;
        $GLOBALS['wpdb'] = $wpdb;
    }

    /**
     * Stale-insert_id fix (feature-doc hazard 4): insertLinesData returned
     * $wpdb->insert_id unconditionally before the sweep, i.e. a stale
     * previous id when the insert failed. A failed insert must now return
     * false and log an ERROR line.
     *
     * @return void
     */
    public function testInsertLinesDataReturnsFalseAndLogsOnDbFailure(): void
    {
        $this->installFailingInsertWpdb();

        $mock_logger = Mockery::mock( LoggerInterface::class );
        $mock_logger->shouldReceive( 'error' )
            ->once()
            ->with(
                'Inc\Covers\Api\CoversApiDbLinesManager::insertLinesData',
                'Duplicate entry for key PRIMARY',
                'covers'
            );
        DatabaseGuard::setLogger( $mock_logger );

        $manager = $this->makeManagerWithoutConstructor( CoversApiDbLinesManager::class );

        $this->assertFalse(
            $manager->insertLinesData( 1, '9788412345678', '/tmp/portadas/9788412345678.jpg' )
        );
    }

    /**
     * Rule A+ regression: set_url_origin() when the row already holds the
     * same url_origin. $wpdb->update legitimately returns 0 affected rows on
     * a healthy database; the method must return true, not coerce 0 -> false
     * through its `: bool` declaration.
     *
     * @return void
     */
    public function testSetUrlOriginReturnsTrueWhenNoRowsUpdated(): void
    {
        $this->installHealthyRowcountWpdb( 0 );

        $mock_logger = Mockery::mock( LoggerInterface::class );
        $mock_logger->shouldNotReceive( 'error' );
        DatabaseGuard::setLogger( $mock_logger );

        $manager = $this->makeManagerWithoutConstructor( CoversApiDbLinesManager::class );

        $this->assertTrue( $manager->set_url_origin( 1, 'https://example.org/cover.jpg' ) );
    }

    /**
     * A genuine update failure still propagates: guard() returns false and
     * logs exactly one ERROR line; set_url_origin() must return false.
     *
     * @return void
     */
    public function testSetUrlOriginReturnsFalseAndLogsOnDbFailure(): void
    {
        $this->installFailingUpdateWpdb();

        $mock_logger = Mockery::mock( LoggerInterface::class );
        $mock_logger->shouldReceive( 'error' )
            ->once()
            ->with(
                'Inc\Covers\Api\CoversApiDbLinesManager::set_url_origin',
                'Duplicate entry for key PRIMARY',
                'covers'
            );
        DatabaseGuard::setLogger( $mock_logger );

        $manager = $this->makeManagerWithoutConstructor( CoversApiDbLinesManager::class );

        $this->assertFalse( $manager->set_url_origin( 1, 'https://example.org/cover.jpg' ) );
    }

    /**
     * Rule A+ regression for the log manager: setLogStatus() on a status
     * transition that matches 0 rows must return true (healthy database),
     * not false.
     *
     * @return void
     */
    public function testSetLogStatusReturnsTrueWhenNoRowsUpdated(): void
    {
        $this->installHealthyRowcountWpdb( 0 );

        $mock_logger = Mockery::mock( LoggerInterface::class );
        $mock_logger->shouldNotReceive( 'error' );
        DatabaseGuard::setLogger( $mock_logger );

        $manager = $this->makeManagerWithoutConstructor( CoversApiDbLogManager::class );

        $this->assertTrue( $manager->setLogStatus( 1, 'processing' ) );
    }

    /**
     * setLogStatus returned true unconditionally before the sweep; a failed
     * update must now return false and log an ERROR line.
     *
     * @return void
     */
    public function testSetLogStatusReturnsFalseAndLogsOnDbFailure(): void
    {
        $this->installFailingUpdateWpdb();

        $mock_logger = Mockery::mock( LoggerInterface::class );
        $mock_logger->shouldReceive( 'error' )
            ->once()
            ->with(
                'Inc\Covers\Api\CoversApiDbLogManager::setLogStatus',
                'Duplicate entry for key PRIMARY',
                'covers'
            );
        DatabaseGuard::setLogger( $mock_logger );

        $manager = $this->makeManagerWithoutConstructor( CoversApiDbLogManager::class );

        $this->assertFalse( $manager->setLogStatus( 1, 'processing' ) );
    }
}
