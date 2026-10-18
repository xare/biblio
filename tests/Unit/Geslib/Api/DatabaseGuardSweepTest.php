<?php
/**
 * Characterization tests for the DatabaseGuard sweep over the Geslib DB managers.
 *
 * Before the sweep, every `try { $wpdb->... } catch (\Exception)` in these
 * managers was dead code (wpdb never throws): the methods returned true on
 * real DB failures and no log line was ever written. These tests prove the
 * core win: a failing $wpdb call now returns false and logs through the
 * injected logger.
 */

namespace Tests\Unit\Geslib\Api;

use Inc\Biblio\Api\DatabaseGuard;
use Inc\Biblio\Api\LoggerInterface;
use Inc\Geslib\Api\GeslibApiDbLinesManager;
use Inc\Geslib\Api\GeslibApiDbLogManager;
use Inc\Geslib\Api\GeslibApiDbQueueManager;
use Mockery;
use ReflectionClass;
use Tests\UnitTestCase;

class DatabaseGuardSweepTest extends UnitTestCase
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
    }

    /**
     * Tear down test fixtures
     *
     * @return void
     */
    protected function tearDown(): void
    {
        DatabaseGuard::setLogger(null);

        if ($this->had_wpdb) {
            $GLOBALS['wpdb'] = $this->previous_wpdb;
        } else {
            unset($GLOBALS['wpdb']);
        }

        parent::tearDown();
    }

    /**
     * Build a manager instance without invoking its constructor.
     *
     * The constructors call `new BiblioApi` → `new FileLogger` → `wp_mkdir_p()`,
     * and BiblioApi also requires the ABSPATH constant — none of which exist in
     * the unit-test environment. The swept methods under test never touch
     * $this->biblioApi (DatabaseGuard routes all failure logging through the
     * injected mock), so skipping the constructor is safe and keeps the test
     * from writing to the real logs/ directory.
     *
     * @param class-string $class Fully-qualified manager class name.
     *
     * @return object
     */
    private function makeManagerWithoutConstructor(string $class): object
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }

    /**
     * Install a fake $wpdb whose write/read methods fail like real wpdb does:
     * return false and populate last_error.
     *
     * @param string $last_error Error message wpdb would have stored.
     *
     * @return void
     */
    private function installFailingWpdb(string $last_error = 'Duplicate entry for key PRIMARY'): void
    {
        $wpdb = new class {
            public string $prefix = 'wp_';
            public string $last_error = '';

            public function insert($table, $data, $format = null)
            {
                $this->last_error = 'Duplicate entry for key PRIMARY';
                return false;
            }

            public function update($table, $data, $where, $format = null, $where_format = null)
            {
                $this->last_error = 'Duplicate entry for key PRIMARY';
                return false;
            }

            public function delete($table, $where, $where_format = null)
            {
                $this->last_error = 'Duplicate entry for key PRIMARY';
                return false;
            }

            public function get_results($query, $output = null)
            {
                $this->last_error = 'WordPress database error: syntax error';
                return false;
            }
        };
        $wpdb->last_error = $last_error;
        $GLOBALS['wpdb'] = $wpdb;
    }

    /**
     * Install a fake $wpdb whose calls succeed.
     *
     * @return void
     */
    private function installSucceedingWpdb(): void
    {
        $wpdb = new class {
            public string $prefix = 'wp_';
            public string $last_error = '';

            public function insert($table, $data, $format = null)
            {
                return 1;
            }
        };
        $GLOBALS['wpdb'] = $wpdb;
    }

    /**
     * Install a fake $wpdb whose row-count methods (delete/update/query)
     * report a healthy $affected_rows with an empty last_error — the state
     * real wpdb leaves after a successful statement that touched 0 rows
     * (empty table, identical values, nothing matched).
     *
     * @param int $affected_rows Row count the fake delete/update/query return.
     *
     * @return void
     */
    private function installHealthyRowcountWpdb(int $affected_rows = 0): void
    {
        $wpdb = new class($affected_rows) {
            public string $prefix = 'wp_';
            public string $last_error = '';

            private int $affected_rows;

            public function __construct(int $affected_rows)
            {
                $this->affected_rows = $affected_rows;
            }

            public function insert($table, $data, $format = null)
            {
                return 1;
            }

            public function update($table, $data, $where, $format = null, $where_format = null)
            {
                return $this->affected_rows;
            }

            public function delete($table, $where, $where_format = null)
            {
                return $this->affected_rows;
            }

            public function query($query)
            {
                return $this->affected_rows;
            }
        };
        $GLOBALS['wpdb'] = $wpdb;
    }

    /**
     * insertData returned true unconditionally before the sweep; a failed
     * insert must now return false and log an ERROR line.
     *
     * @return void
     */
    public function testInsertDataReturnsFalseAndLogsOnDbFailure(): void
    {
        $this->installFailingWpdb();

        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldReceive('error')
            ->once()
            ->with(
                'Inc\Geslib\Api\GeslibApiDbLinesManager::insertData',
                'Duplicate entry for key PRIMARY',
                'geslib'
            );
        DatabaseGuard::setLogger($mock_logger);

        $manager = $this->makeManagerWithoutConstructor(GeslibApiDbLinesManager::class);

        $this->assertFalse(
            $manager->insertData(['geslib_id' => 42], 'insert', 1, 'product')
        );
    }

    /**
     * deleteItemFromQueue returned true unconditionally before the sweep; a
     * failed delete must now return false and log an ERROR line.
     *
     * @return void
     */
    public function testDeleteItemFromQueueReturnsFalseAndLogsOnDbFailure(): void
    {
        $this->installFailingWpdb();

        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldReceive('error')
            ->once()
            ->with(
                'Inc\Geslib\Api\GeslibApiDbQueueManager::deleteItemFromQueue',
                'Duplicate entry for key PRIMARY',
                'geslib'
            );
        DatabaseGuard::setLogger($mock_logger);

        $manager = $this->makeManagerWithoutConstructor(GeslibApiDbQueueManager::class);

        $this->assertFalse($manager->deleteItemFromQueue('build_content', 1, 42));
    }

    /**
     * setLogStatus returned true unconditionally before the sweep; a failed
     * update must now return false and log an ERROR line.
     *
     * @return void
     */
    public function testSetLogStatusReturnsFalseAndLogsOnDbFailure(): void
    {
        $this->installFailingWpdb();

        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldReceive('error')
            ->once()
            ->with(
                'Inc\Geslib\Api\GeslibApiDbLogManager::setLogStatus',
                'Duplicate entry for key PRIMARY',
                'geslib'
            );
        DatabaseGuard::setLogger($mock_logger);

        $manager = $this->makeManagerWithoutConstructor(GeslibApiDbLogManager::class);

        $this->assertFalse($manager->setLogStatus(1, 'processed'));
    }

    /**
     * fetchLoggedFilesFromDb is a Rule B site: the guard logs the failure but
     * the method must keep returning its pre-existing failure value ([]) so
     * the `: array` consumer (listFilesInFolder) never sees false.
     *
     * @return void
     */
    public function testFetchLoggedFilesFromDbReturnsEmptyArrayAndLogsOnDbFailure(): void
    {
        $this->installFailingWpdb('WordPress database error: syntax error');

        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldReceive('error')
            ->once()
            ->with(
                'Inc\Geslib\Api\GeslibApiDbLogManager::fetchLoggedFilesFromDb',
                'WordPress database error: syntax error',
                'geslib'
            );
        DatabaseGuard::setLogger($mock_logger);

        $manager = $this->makeManagerWithoutConstructor(GeslibApiDbLogManager::class);

        $this->assertSame([], $manager->fetchLoggedFilesFromDb());
    }

    /**
     * Success path is unchanged: a healthy insert still returns true and the
     * logger is never called.
     *
     * @return void
     */
    public function testInsertDataReturnsTrueOnSuccess(): void
    {
        $this->installSucceedingWpdb();

        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldNotReceive('error');
        DatabaseGuard::setLogger($mock_logger);

        $manager = $this->makeManagerWithoutConstructor(GeslibApiDbLinesManager::class);

        $this->assertTrue(
            $manager->insertData(['geslib_id' => 42], 'insert', 1, 'product')
        );
    }

    /**
     * Regression: truncateGeslibLines() on an EMPTY (but healthy) queue table.
     *
     * $wpdb->delete legitimately returns 0 when no rows matched. guard()
     * treats that as success and returns 0 unchanged; the method must not
     * coerce 0 -> false through its `: bool` declaration, otherwise the admin
     * form (GeslibStoreProductsFormController:::342) shows a false ERROR
     * notice on a perfectly healthy, merely-empty queue.
     *
     * @return void
     */
    public function testTruncateGeslibLinesReturnsTrueWhenNoRowsMatched(): void
    {
        $this->installHealthyRowcountWpdb(0);

        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldNotReceive('error');
        DatabaseGuard::setLogger($mock_logger);

        $manager = $this->makeManagerWithoutConstructor(GeslibApiDbLinesManager::class);

        $this->assertTrue($manager->truncateGeslibLines());
    }

    /**
     * A genuine delete failure still propagates: guard() returns false and
     * logs exactly one ERROR line; the method must return false.
     *
     * @return void
     */
    public function testTruncateGeslibLinesReturnsFalseAndLogsOnDbFailure(): void
    {
        $this->installFailingWpdb();

        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldReceive('error')
            ->once()
            ->with(
                'Inc\Geslib\Api\GeslibApiDbLinesManager::truncateGeslibLines',
                'Duplicate entry for key PRIMARY',
                'geslib'
            );
        DatabaseGuard::setLogger($mock_logger);

        $manager = $this->makeManagerWithoutConstructor(GeslibApiDbLinesManager::class);

        $this->assertFalse($manager->truncateGeslibLines());
    }

    /**
     * updateGeslibLines() on a row whose values are already identical:
     * $wpdb->update returns 0 affected rows, which is success. The method
     * must return true, not coerce 0 -> false.
     *
     * @return void
     */
    public function testUpdateGeslibLinesReturnsTrueWhenNoRowsUpdated(): void
    {
        $this->installHealthyRowcountWpdb(0);

        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldNotReceive('error');
        DatabaseGuard::setLogger($mock_logger);

        $manager = $this->makeManagerWithoutConstructor(GeslibApiDbLinesManager::class);

        $this->assertTrue($manager->updateGeslibLines(42, 'product', '[]'));
    }

    /**
     * A genuine update failure still propagates: guard() returns false and
     * logs exactly one ERROR line; updateGeslibLines() must return false.
     *
     * @return void
     */
    public function testUpdateGeslibLinesReturnsFalseAndLogsOnDbFailure(): void
    {
        $this->installFailingWpdb();

        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldReceive('error')
            ->once()
            ->with(
                'Inc\Geslib\Api\GeslibApiDbLinesManager::updateGeslibLines',
                'Duplicate entry for key PRIMARY',
                'geslib'
            );
        DatabaseGuard::setLogger($mock_logger);

        $manager = $this->makeManagerWithoutConstructor(GeslibApiDbLinesManager::class);

        $this->assertFalse($manager->updateGeslibLines(42, 'product', '[]'));
    }

    /**
     * deleteItemFromQueue() when the row is already gone (0 rows deleted):
     * healthy database, so the method must return true. Complements
     * testDeleteItemFromQueueReturnsFalseAndLogsOnDbFailure, which proves
     * the real-failure propagation.
     *
     * @return void
     */
    public function testDeleteItemFromQueueReturnsTrueWhenNoRowsDeleted(): void
    {
        $this->installHealthyRowcountWpdb(0);

        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldNotReceive('error');
        DatabaseGuard::setLogger($mock_logger);

        $manager = $this->makeManagerWithoutConstructor(GeslibApiDbQueueManager::class);

        $this->assertTrue($manager->deleteItemFromQueue('build_content', 1, 42));
    }
}
