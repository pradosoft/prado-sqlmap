<?php

require_once(__DIR__ . '/BaseCase.php');

class AccountWithOrders extends Account
{
	private $_orders = [];

	public function setOrders($orders)
	{
		$this->_orders = $orders;
	}

	public function getOrders()
	{
		return $this->_orders;
	}
}

abstract class GroupByTest extends BaseCase
{
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::initSqlMap();
	}

	public function testAccountWithOrders()
	{
		$this->initScript('account-init.sql');
		$accounts = self::$sqlmap->queryForList("getAccountWithOrders");
		$this->assertSame(5, count($accounts));
		foreach ($accounts as $account) {
			$this->assertSame(2, count($account->getOrders()));
		}
	}

	/**
	 * Orders 1, 2 and 3 belong to accounts 1, 4 and 3. The LEFT JOIN limited to
	 * those orders leaves accounts 2 and 5 with null in every order column.
	 */
	public function testAccountWithoutOrdersHasEmptyCollection()
	{
		$this->initScript('account-init.sql');
		$accounts = self::$sqlmap->queryForList("getAccountWithEarlyOrders");
		$this->assertSame(5, count($accounts));

		$orders = [];
		foreach ($accounts as $account) {
			$orders[(int) $account->getId()] = array_map(fn ($order) => (int) $order->getId(), $account->getOrders());
		}
		ksort($orders);
		$this->assertSame([1 => [1], 2 => [], 3 => [3], 4 => [2], 5 => []], $orders);
	}
}
