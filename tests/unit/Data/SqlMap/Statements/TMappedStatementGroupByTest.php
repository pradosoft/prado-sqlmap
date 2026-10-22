<?php

use Prado\Data\SqlMap\TSqlMapManager;

class TMappedStatementGroupByUser
{
	public $Id;
	public $Name;
	public $Roles = [];
}

class TMappedStatementGroupByRole
{
	public $Name;
}

class TMappedStatementGroupByConnection
{
	public function setActive($value)
	{
	}
}

class TMappedStatementGroupByCommand
{
	public function __construct(private array $rows)
	{
	}

	public function query()
	{
		return $this->rows;
	}
}

class TMappedStatementGroupByTest extends PHPUnit\Framework\TestCase
{
	private function query(array $rows): array
	{
		$manager = new TSqlMapManager();
		$manager->configureXml(__DIR__ . '/../maps/group-by.xml');
		$statement = $manager->getMappedStatement('GetUsersWithRoles');
		return $statement->runQueryForList(
			new TMappedStatementGroupByConnection(),
			null,
			new TMappedStatementGroupByCommand($rows),
			null
		);
	}

	private function roleNames(TMappedStatementGroupByUser $user): array
	{
		return array_map(fn ($role) => $role->Name, $user->Roles);
	}

	public function testLeftJoinWithoutMatchGivesEmptyCollection()
	{
		$users = $this->query([
			['user_id' => 1, 'user_name' => 'ann', 'role_name' => 'admin'],
			['user_id' => 1, 'user_name' => 'ann', 'role_name' => 'user'],
			['user_id' => 2, 'user_name' => 'bob', 'role_name' => null],
			['user_id' => 3, 'user_name' => 'cy', 'role_name' => 'user'],
		]);

		$this->assertSame(['ann', 'bob', 'cy'], array_map(fn ($user) => $user->Name, $users));
		$this->assertSame(['admin', 'user'], $this->roleNames($users[0]));
		$this->assertSame([], $users[1]->Roles);
		$this->assertSame(['user'], $this->roleNames($users[2]));
	}

	public function testLeftJoinWithoutAnyMatch()
	{
		$users = $this->query([
			['user_id' => 1, 'user_name' => 'ann', 'role_name' => null],
			['user_id' => 2, 'user_name' => 'bob', 'role_name' => null],
		]);

		$this->assertSame(['ann', 'bob'], array_map(fn ($user) => $user->Name, $users));
		$this->assertSame([], $users[0]->Roles);
		$this->assertSame([], $users[1]->Roles);
	}

	public function testColumnCaseFolding()
	{
		$users = $this->query([
			['USER_ID' => 1, 'USER_NAME' => 'ann', 'ROLE_NAME' => 'admin'],
			['USER_ID' => 2, 'USER_NAME' => 'bob', 'ROLE_NAME' => null],
		]);

		$this->assertSame(['admin'], $this->roleNames($users[0]));
		$this->assertSame([], $users[1]->Roles);
	}
}
