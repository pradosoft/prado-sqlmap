<?php

use Prado\Data\SqlMap\DataMapper\TSqlMapConfigurationException;
use Prado\Data\SqlMap\DataMapper\TSqlMapException;
use Prado\Exceptions\TException;
use Prado\Test\Unit\PradoUnit;

class TSqlMapExceptionTest extends PHPUnit\Framework\TestCase
{
	public function testOldStyleTranslatesMessageAndParameters()
	{
		$e = new TSqlMapException('sqlmap_contains_no_statement', 'findUser');
		$this->assertSame("Unable to find SQLMap statement 'findUser'.", $e->getMessage());
		$this->assertSame(0, $e->getCode());
		$this->assertSame('sqlmap_contains_no_statement', $e->getErrorCode());
		$this->assertNull($e->getPrevious());
	}

	public function testOldStyleWithoutParameters()
	{
		$e = new TSqlMapException('sqlmap_use_set_to_store_cache');
		$this->assertSame('Use set() to store items in the SQLMap cache; add() is not supported.', $e->getMessage());
	}

	public function testOldStyleKeepsExplicitNullParameter()
	{
		$e = new TSqlMapException('sqlmap_unable_to_create_new_instance', null, 'Handler', 'stmt');
		$this->assertSame("Unable to create a new instance of '' using type hander 'Handler' for SQLMap statement with ID 'stmt'.", $e->getMessage());
	}

	public function testIntegerErrorCode()
	{
		$e = new TSqlMapException(42, 'sqlmap_contains_no_result_map', 'Account');
		$this->assertSame("Unable to find SQLMap result map 'Account'.", $e->getMessage());
		$this->assertSame(42, $e->getCode());
		$this->assertSame('sqlmap_contains_no_result_map', $e->getErrorCode());
	}

	public function testTrailingThrowableIsPrevious()
	{
		$previous = new RuntimeException('inner');
		$e = new TSqlMapException('sqlmap_contains_no_statement', 'findUser', $previous);
		$this->assertSame($previous, $e->getPrevious());
		$this->assertSame("Unable to find SQLMap statement 'findUser'.", $e->getMessage());

		$e = new TSqlMapException(7, 'sqlmap_contains_no_statement', 'findUser', $previous);
		$this->assertSame($previous, $e->getPrevious());
		$this->assertSame(7, $e->getCode());
	}

	public function testSimpleXmlElementParameterExpands()
	{
		$node = new SimpleXMLElement('<select id="findUser" resultClass="User"/>');
		$e = new TSqlMapConfigurationException('sqlmap_undefined_attribute', 'select', 'foo', $node, 'file.xml');
		$this->assertSame(
			"select attribute 'foo' is not defined for <select id=\"findUser\" resultClass=\"User\"> in file file.xml.",
			$e->getMessage()
		);
	}

	public function testMessagesAreIcuPatternsThatRenderEveryArgument()
	{
		$file = dirname(__DIR__, 5) . '/src/Data/SqlMap/DataMapper/messages.txt';
		$checked = 0;
		foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
			if (!preg_match('/^\s*(\w+)\s*=\s*(.*)$/', $line, $m)) {
				continue;
			}
			[, $key, $pattern] = $m;
			preg_match_all('/\{(\d+)\}/', $pattern, $placeholders);
			$args = [];
			foreach ($placeholders[1] as $i) {
				$args[(int) $i] = 'arg' . $i;
			}
			$formatted = \MessageFormatter::formatMessage('en', $pattern, $args);
			$this->assertNotFalse($formatted, "$key is not a valid ICU MessageFormat pattern.");
			foreach ($args as $arg) {
				$this->assertStringContainsString($arg, $formatted, "$key does not render $arg.");
			}
			$this->assertStringNotContainsString("''", $formatted, "$key renders a doubled apostrophe.");
			$checked++;
		}
		$this->assertGreaterThan(0, $checked);
	}

	public function testMessagesNotInFrameworkFileTranslate()
	{
		$snapshot = PradoUnit::snapshotStatic(TException::class);
		try {
			TException::addMessageFile(dirname(__DIR__, 5) . '/src/Data/SqlMap/DataMapper/messages.txt');
			$e = new \Prado\Exceptions\TConfigurationException('sqlmap_configfile_invalid', 'Application.missing');
			$this->assertSame("SQLMap configuration file or namespace 'Application.missing' is invalid or does not exist.", $e->getMessage());
		} finally {
			PradoUnit::restoreStatic(TException::class, $snapshot);
		}
	}
}
