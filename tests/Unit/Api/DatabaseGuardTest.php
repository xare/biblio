<?php
/**
 * Tests for DatabaseGuard
 */

namespace Tests\Unit\Api;

use Inc\Biblio\Api\DatabaseGuard;
use Inc\Biblio\Api\LoggerInterface;
use Tests\UnitTestCase;
use Mockery;
use stdClass;

class DatabaseGuardTest extends UnitTestCase
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
     * Fake wpdb object with a public last_error property
     *
     * @var stdClass
     */
    private stdClass $fake_wpdb;

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

        $this->fake_wpdb = new stdClass();
        $this->fake_wpdb->last_error = '';
        $GLOBALS['wpdb'] = $this->fake_wpdb;
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
     * Test result is returned unchanged when last_error is empty
     *
     * @return void
     */
    public function testReturnsResultUnchangedWhenLastErrorIsEmpty(): void
    {
        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldNotReceive('error');
        DatabaseGuard::setLogger($mock_logger);

        $this->assertSame(42, DatabaseGuard::guard(42, 'Select rows'));
    }

    /**
     * Test zero is returned unchanged and does not log when last_error is empty
     *
     * The critical strictness regression: a legitimate 0 (zero rows affected)
     * must NOT be treated as a failure. Only `false === ` may fail.
     *
     * @return void
     */
    public function testReturnsZeroUnchangedAndDoesNotLogWhenLastErrorIsEmpty(): void
    {
        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldNotReceive('error');
        DatabaseGuard::setLogger($mock_logger);

        $this->assertSame(0, DatabaseGuard::guard(0, 'Update rows'));
    }

    /**
     * Test null is returned unchanged and does not log when last_error is empty
     *
     * Legitimate get_var() no-rows case.
     *
     * @return void
     */
    public function testReturnsNullUnchangedAndDoesNotLogWhenLastErrorIsEmpty(): void
    {
        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldNotReceive('error');
        DatabaseGuard::setLogger($mock_logger);

        $this->assertNull(DatabaseGuard::guard(null, 'Get option value'));
    }

    /**
     * Test empty array is returned unchanged and does not log when last_error is empty
     *
     * Legitimate get_results() empty-set case.
     *
     * @return void
     */
    public function testReturnsEmptyArrayUnchangedAndDoesNotLogWhenLastErrorIsEmpty(): void
    {
        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldNotReceive('error');
        DatabaseGuard::setLogger($mock_logger);

        $this->assertSame([], DatabaseGuard::guard([], 'Get results'));
    }

    /**
     * Test false result with empty last_error logs the fallback message once
     *
     * @return void
     */
    public function testReturnsFalseAndLogsFallbackWhenResultIsFalseAndLastErrorIsEmpty(): void
    {
        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldReceive('error')
            ->once()
            ->with(
                'Run query',
                'Query returned false without a database error message.',
                'biblio'
            );
        DatabaseGuard::setLogger($mock_logger);

        $this->assertFalse(DatabaseGuard::guard(false, 'Run query'));
    }

    /**
     * Test null result with last_error set is a failure and logs once
     *
     * The get_var() failure case: null is legitimate only when last_error is empty.
     *
     * @return void
     */
    public function testReturnsFalseAndLogsWhenResultIsNullButLastErrorIsSet(): void
    {
        $this->fake_wpdb->last_error = 'Table wp_books does not exist.';

        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldReceive('error')
            ->once()
            ->with('Get book', 'Table wp_books does not exist.', 'biblio');
        DatabaseGuard::setLogger($mock_logger);

        $this->assertFalse(DatabaseGuard::guard(null, 'Get book'));
    }

    /**
     * Test empty array result with last_error set is a failure and logs once
     *
     * The get_results(ARRAY_A) failure case: [] is legitimate only when
     * last_error is empty.
     *
     * @return void
     */
    public function testReturnsFalseAndLogsWhenResultIsEmptyArrayButLastErrorIsSet(): void
    {
        $this->fake_wpdb->last_error = 'You have an error in your SQL syntax.';

        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldReceive('error')
            ->once()
            ->with('Select books', 'You have an error in your SQL syntax.', 'biblio');
        DatabaseGuard::setLogger($mock_logger);

        $this->assertFalse(DatabaseGuard::guard([], 'Select books'));
    }

    /**
     * Test context passes through as log title and type as routing type
     *
     * @return void
     */
    public function testPassesContextAsLogTitleAndTypeAsRoutingType(): void
    {
        $this->fake_wpdb->last_error = 'Duplicate entry for key primary';

        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldReceive('error')
            ->once()
            ->with('Geslib insert', 'Duplicate entry for key primary', 'geslib');
        DatabaseGuard::setLogger($mock_logger);

        $this->assertFalse(DatabaseGuard::guard(false, 'Geslib insert', 'geslib'));
    }

    /**
     * Test guard does not fatal when the wpdb global is absent
     *
     * @return void
     */
    public function testDoesNotFatalWhenWpdbGlobalIsAbsent(): void
    {
        unset($GLOBALS['wpdb']);

        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldNotReceive('error');
        DatabaseGuard::setLogger($mock_logger);

        $this->assertSame(42, DatabaseGuard::guard(42, 'No wpdb context'));
    }

    /**
     * Test setLogger override is honoured over the previous logger
     *
     * @return void
     */
    public function testSetLoggerOverrideIsHonoured(): void
    {
        $this->fake_wpdb->last_error = 'Deadlock found';

        $first_logger = Mockery::mock(LoggerInterface::class);
        $first_logger->shouldNotReceive('error');
        DatabaseGuard::setLogger($first_logger);

        $second_logger = Mockery::mock(LoggerInterface::class);
        $second_logger->shouldReceive('error')
            ->once()
            ->with('Retry insert', 'Deadlock found', 'biblio');
        DatabaseGuard::setLogger($second_logger);

        $this->assertFalse(DatabaseGuard::guard(false, 'Retry insert'));
    }
}
