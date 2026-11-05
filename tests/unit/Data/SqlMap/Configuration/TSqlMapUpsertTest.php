<?php

use Prado\Data\SqlMap\Configuration\TSqlMapInsert;
use Prado\Data\SqlMap\Configuration\TSqlMapInsertOrIgnore;
use Prado\Data\SqlMap\Configuration\TSqlMapUpsert;
use Prado\Data\SqlMap\Statements\TInsertMappedStatement;
use Prado\Data\SqlMap\Statements\TInsertOrIgnoreMappedStatement;
use Prado\Data\SqlMap\Statements\TUpsertMappedStatement;
use Prado\Data\SqlMap\TSqlMapManager;

/**
 * Covers the `<insertOrIgnore>` and `<upsert>` mapping elements without a database.
 */
class TSqlMapUpsertTest extends PHPUnit\Framework\TestCase
{
	private TSqlMapManager $manager;

	protected function setUp(): void
	{
		$this->manager = new TSqlMapManager();
		$this->manager->configureXml(__DIR__ . '/../maps/upsert-config.xml');
	}

	public function testInsertOrIgnoreElementCreatesItsStatement()
	{
		$mapped = $this->manager->getMappedStatement('InsertOrIgnoreItem');
		$this->assertInstanceOf(TInsertOrIgnoreMappedStatement::class, $mapped);
		$this->assertInstanceOf(TInsertMappedStatement::class, $mapped);
		$this->assertInstanceOf(TSqlMapInsertOrIgnore::class, $mapped->getStatement());
		$this->assertInstanceOf(TSqlMapInsert::class, $mapped->getStatement());
	}

	public function testUpsertElementCreatesItsStatement()
	{
		$mapped = $this->manager->getMappedStatement('UpsertItem');
		$this->assertInstanceOf(TUpsertMappedStatement::class, $mapped);
		$this->assertInstanceOf(TInsertMappedStatement::class, $mapped);
		$this->assertInstanceOf(TSqlMapUpsert::class, $mapped->getStatement());
	}

	public function testUpsertAttributesAreColumnLists()
	{
		$statement = $this->manager->getMappedStatement('UpsertItem')->getStatement();
		$this->assertSame(['score', 'note'], $statement->getUpdateColumns());
		$this->assertSame(['name'], $statement->getConflictColumns());
	}

	public function testUpsertAttributesDefaultToNull()
	{
		$statement = $this->manager->getMappedStatement('UpsertDefaults')->getStatement();
		$this->assertNull($statement->getUpdateColumns());
		$this->assertNull($statement->getConflictColumns());
	}

	public function testUpsertColumnListsAcceptArrays()
	{
		$statement = new TSqlMapUpsert();
		$statement->setUpdateColumns(['a', 'b']);
		$statement->setConflictColumns(['c']);
		$this->assertSame(['a', 'b'], $statement->getUpdateColumns());
		$this->assertSame(['c'], $statement->getConflictColumns());
	}

	public function testUpsertSelectKeyIsRegistered()
	{
		$statement = $this->manager->getMappedStatement('UpsertWithKey')->getStatement();
		$this->assertSame('UpsertWithKey.SelectKey', $statement->getSelectKey()->getID());
		$this->assertNotNull($this->manager->getMappedStatement('UpsertWithKey.SelectKey'));
	}

	/**
	 * @dataProvider writeStatements
	 */
	public function testWriteStatementFlushesItsCacheModel(string $statementID)
	{
		$cache = $this->manager->getCacheModel('upsert-cache');
		$cache->set('key', 'value');
		$this->manager->getMappedStatement($statementID)->onExecuteQuery('');
		$this->assertNull($cache->get('key'));
	}

	public static function writeStatements(): array
	{
		return [['InsertOrIgnoreItem'], ['UpsertItem']];
	}
}
