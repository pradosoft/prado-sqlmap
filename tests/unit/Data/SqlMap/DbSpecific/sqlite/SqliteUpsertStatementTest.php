<?php

use Prado\Data\SqlMap\TSqlMapManager;
use Prado\Data\TDbConnection;

/**
 * Runs `<insertOrIgnore>` and `<upsert>` statements through TSqlMapGateway::insert()
 * on an in-memory SQLite database.
 */
class SqliteUpsertStatementTest extends PHPUnit\Framework\TestCase
{
	private TDbConnection $connection;
	private \Prado\Data\SqlMap\TSqlMapGateway $gateway;

	protected function setUp(): void
	{
		if (!extension_loaded('pdo_sqlite')) {
			$this->markTestSkipped('pdo_sqlite is not available.');
		}
		$this->connection = new TDbConnection('sqlite::memory:');
		$this->connection->setActive(true);
		$this->connection->createCommand(
			'CREATE TABLE upsert_items (item_id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(20) NOT NULL UNIQUE, score INTEGER NOT NULL DEFAULT 0)'
		)->execute();
		$manager = new TSqlMapManager($this->connection);
		$manager->configureXml(__DIR__ . '/../../maps/upsert-config.xml');
		$this->gateway = $manager->getSqlmapGateway();
	}

	protected function tearDown(): void
	{
		$this->connection->setActive(false);
	}

	private function score(string $name): ?int
	{
		$row = $this->gateway->queryForObject('SelectItem', $name);
		return $row === null ? null : (int) $row['score'];
	}

	private function rowCount(): int
	{
		return (int) $this->connection->createCommand('SELECT COUNT(*) FROM upsert_items')->queryScalar();
	}

	public function testInsertOrIgnoreInsertsANewRow()
	{
		$this->gateway->insert('InsertOrIgnoreItem', ['name' => 'alice', 'score' => 10]);
		$this->assertSame(10, $this->score('alice'));
	}

	public function testInsertOrIgnoreLeavesAConflictingRow()
	{
		$this->gateway->insert('InsertOrIgnoreItem', ['name' => 'alice', 'score' => 10]);
		$this->gateway->insert('InsertOrIgnoreItem', ['name' => 'alice', 'score' => 99]);
		$this->assertSame(10, $this->score('alice'));
		$this->assertSame(1, $this->rowCount());
	}

	public function testUpsertInsertsANewRow()
	{
		$this->gateway->insert('UpsertItem', ['name' => 'bob', 'score' => 5]);
		$this->assertSame(5, $this->score('bob'));
	}

	public function testUpsertUpdatesAConflictingRow()
	{
		$this->gateway->insert('UpsertItem', ['name' => 'bob', 'score' => 5]);
		$this->gateway->insert('UpsertItem', ['name' => 'bob', 'score' => 42]);
		$this->assertSame(42, $this->score('bob'));
		$this->assertSame(1, $this->rowCount());
	}

	public function testUpsertSelectKeyReturnsTheKeyOnInsertAndUpdate()
	{
		$first = $this->gateway->insert('UpsertWithKey', ['name' => 'carol', 'score' => 1]);
		$second = $this->gateway->insert('UpsertWithKey', ['name' => 'carol', 'score' => 2]);
		$this->assertGreaterThan(0, (int) $first);
		$this->assertSame((int) $first, (int) $second);
		$this->assertSame(2, $this->score('carol'));
	}
}
