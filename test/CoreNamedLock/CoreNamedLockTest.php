<?php
declare(strict_types=1);

namespace SetBased\Abc\Test\CoreNamedLock;

use PHPUnit\Framework\TestCase;
use SetBased\Abc\Abc;
use SetBased\Abc\C;
use SetBased\Abc\CompanyResolver\UniCompanyResolver;
use SetBased\Abc\Lock\CoreNamedLock;
use SetBased\Abc\Test\TestDataLayer;

/**
 * Test cases for CoreNamedLock.
 */
class CoreNamedLockTest extends TestCase
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test locking twice (or more) the same named lock is possible.
   */
  public function testDoubleLock(): void
  {
    $lock = new CoreNamedLock();

    $lock->acquireLock(C::LNN_ID_NAMED_LOCK1);
    $lock->acquireLock(C::LNN_ID_NAMED_LOCK1);
    $lock->acquireLock(C::LNN_ID_NAMED_LOCK1);

    self::assertTrue(true);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test lock is exclusive and released on commit.
   */
  public function testExclusiveLock1(): void
  {
    // Start helper process
    $descriptors = [0 => ["pipe", "r"],
                    1 => ["pipe", "w"]];

    $process = proc_open(__DIR__.'/../test-exclusive-lock-helper.php', $descriptors, $pipes);

    // Acquire lock.
    $lock = new CoreNamedLock();
    $lock->acquireLock(C::LNN_ID_NAMED_LOCK1);

    // Tell helper process to acquire lock too.
    fwrite($pipes[0], "\n");

    // Do something.
    sleep(4);

    // Release lock.
    Abc::$DL->commit();

    // Read lock waiting time from child process.
    $time = fgets($pipes[1]);

    self::assertGreaterThanOrEqual(3, $time);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test lock is exclusive and released on rollback.
   */
  public function testExclusiveLock2(): void
  {
    // Start helper process
    $descriptors = [0 => ["pipe", "r"],
                    1 => ["pipe", "w"]];

    $process = proc_open(__DIR__.'/../test-exclusive-lock-helper.php', $descriptors, $pipes);

    // Acquire lock.
    $lock = new CoreNamedLock();
    $lock->acquireLock(C::LNN_ID_NAMED_LOCK1);

    // Tell helper process to acquire lock too.
    fwrite($pipes[0], "\n");

    // Do something.
    sleep(4);

    // Release lock.
    Abc::$DL->rollback();

    // Read lock waiting time from child process.
    $time = fgets($pipes[1]);

    self::assertGreaterThanOrEqual(3, $time);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test locks are company isolated.
   */
  public function testExclusiveLock3(): void
  {
    Abc::$companyResolver = new UniCompanyResolver(C::CMP_ID_SYS);

    // Start helper process
    $descriptors = [0 => ["pipe", "r"],
                    1 => ["pipe", "w"]];

    $process = proc_open(__DIR__.'/../test-exclusive-lock-helper.php', $descriptors, $pipes);

    // Acquire lock.
    $lock = new CoreNamedLock();
    $lock->acquireLock(C::LNN_ID_NAMED_LOCK1);

    // Tell helper process to acquire lock too.
    fwrite($pipes[0], "\n");

    // Do something.
    sleep(4);

    // Release lock.
    Abc::$DL->commit();

    // Read lock waiting time from child process.
    $time = fgets($pipes[1]);

    self::assertEquals(0, $time);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test get ID of named lock.
   */
  public function testGetId1(): void
  {
    $lock = new CoreNamedLock();

    $lock->acquireLock(C::LNN_ID_NAMED_LOCK1);
    $id = $lock->getId();

    self::assertSame(C::LNN_ID_NAMED_LOCK1, $id);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test get ID of named lock without lock.
   */
  public function testGetId2(): void
  {
    $lock = new CoreNamedLock();

    $this->expectException(\LogicException::class);
    $lock->getId();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test get name of named lock.
   */
  public function testGetName1(): void
  {
    $lock = new CoreNamedLock();

    $lock->acquireLock(C::LNN_ID_NAMED_LOCK1);
    $name = $lock->getName();

    self::assertSame('named_lock1', $name);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test get name of named lock without lock.
   */
  public function testGetName2(): void
  {
    $lock = new CoreNamedLock();

    $this->expectException(\LogicException::class);
    $lock->getName();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Test multiple named locks are possible.
   */
  public function testMultipleLocks(): void
  {
    $lock1 = new CoreNamedLock();
    $lock1->acquireLock(C::LNN_ID_NAMED_LOCK1);

    $lock2 = new CoreNamedLock();
    $lock2->acquireLock(C::LNN_ID_NAMED_LOCK2);

    self::assertTrue(true);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Connects to the MySQL server and cleans the BLOB tables.
   */
  protected function setUp(): void
  {
    Abc::$DL              = new TestDataLayer();
    Abc::$companyResolver = new UniCompanyResolver(C::CMP_ID_ABC);

    Abc::$DL->connect('localhost', 'test', 'test', 'test');
    Abc::$DL->begin();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Disconnects from the MySQL server.
   */
  protected function tearDown(): void
  {
    Abc::$DL->commit();
    Abc::$DL->disconnect();
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
