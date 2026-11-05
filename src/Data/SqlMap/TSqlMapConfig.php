<?php

/**
 * TSqlMapConfig class file.
 *
 * @author Wei Zhuo <weizhuo[at]gmail[dot]com>
 * @link https://github.com/pradosoft/prado
 * @license https://github.com/pradosoft/prado/blob/master/LICENSE
 */

namespace Prado\Data\SqlMap;

use Prado\Data\TDataSourceConfig;
use Prado\Exceptions\TConfigurationException;
use Prado\Prado;
use Prado\TApplicationMode;
use Prado\TPropertyValue;

/**
 * TSqlMapConfig is an application module that configures a {@see TSqlMapManager}
 * and exposes a {@see TSqlMapGateway} via {@see getClient}.
 *
 * ## Connection — two approaches
 *
 * **1. Reference an existing TDataSourceConfig module via `ConnectionID`.**
 * The named module must be declared before this one in application.xml:
 * ```xml
 * <modules>
 *     <module id="db" class="Prado\Data\TDataSourceConfig">
 *         <database ConnectionString="mysql:host=localhost;dbname=mydb"
 *             Username="dbuser" Password="dbpass" />
 *     </module>
 *     <module id="sqlmap" class="Prado\Data\SqlMap\TSqlMapConfig"
 *         ConnectionID="db" ConfigFile="Application.Config.sqlmap" EnableCache="true" />
 * </modules>
 * ```
 *
 * **2. Inline `<database>` — declare the connection directly on this module.**
 * The `<database>` child element sets {@see \Prado\Data\TDbConnection} properties
 * and is handled by the parent {@see \Prado\Data\TDataSourceConfig}:
 * ```xml
 * <modules>
 *     <module id="sqlmap" class="Prado\Data\SqlMap\TSqlMapConfig"
 *         ConfigFile="Application.Config.sqlmap" EnableCache="true">
 *         <database ConnectionString="mysql:host=localhost;dbname=mydb"
 *             Username="dbuser" Password="dbpass" />
 *     </module>
 * </modules>
 * ```
 *
 * PHP configuration style (inline database):
 * ```php
 * return [
 *     'modules' => [
 *         'sqlmap' => [
 *             'class' => 'Prado\Data\SqlMap\TSqlMapConfig',
 *             'properties' => [
 *                 'ConfigFile' => 'Application.Config.sqlmap',
 *                 'EnableCache' => 'true',
 *             ],
 *             'database' => [
 *                 'ConnectionString' => 'mysql:host=localhost;dbname=mydb',
 *                 'Username' => 'dbuser',
 *                 'Password' => 'dbpass',
 *             ],
 *         ],
 *     ],
 * ];
 * ```
 *
 * ## Using the gateway
 * ```php
 * $sqlmap = $this->Application->Modules['sqlmap']->Client;
 * $account = $sqlmap->queryForObject('GetAccount', $id);
 * ```
 *
 * Set {@see setEnableCache EnableCache} to `true` to serialise the parsed
 * {@see TSqlMapManager} into the application cache (keyed by module ID +
 * config file path) so that XML parsing is skipped on subsequent requests.
 * Cache entries are invalidated automatically when the config files change
 * unless the application is running in {@see \Prado\TApplicationMode::Performance}
 * mode. A cached manager whose {@see TSqlMapManager::getVersion() Version} is not
 * {@see TSqlMapManager::VERSION} was written by another release of this package;
 * it is discarded and the manager is rebuilt from the config files.
 *
 * @author Wei Zhuo <weizho[at]gmail[dot]com>
 * @since 3.1
 * see https://github.com/mybatis/
 */
class TSqlMapConfig extends TDataSourceConfig
{
	private $_configFile;
	private $_sqlmap;
	private $_enableCache = false;

	/**
	 * File extension of external configuration file
	 */
	public const CONFIG_FILE_EXT = '.xml';

	/**
	 * @return string module ID + configuration file path.
	 */
	private function getCacheKey()
	{
		return $this->getID() . $this->getConfigFile();
	}

	/**
	 * Deletes the configuration cache.
	 */
	public function clearCache()
	{
		$cache = $this->getApplication()->getCache();
		if ($cache !== null) {
			$cache->delete($this->getCacheKey());
		}
	}

	/**
	 * Create and configure the data mapper using sqlmap configuration file.
	 * Or if cache is enabled and manager already cached load from cache.
	 * If cache is enabled, the data mapper instance is cached.
	 *
	 * @return TSqlMapManager SqlMap manager instance
	 * @since 3.1.7
	 */
	public function getSqlMapManager()
	{
		if (($manager = $this->loadCachedSqlMapManager()) === null) {
			$manager = new TSqlMapManager($this->getDbConnection());
			if (strlen($file = $this->getConfigFile()) > 0) {
				$manager->configureXml($file);
				$this->cacheSqlMapManager($manager);
			}
		} elseif ($this->getConnectionID() !== '') {
			$manager->setDbConnection($this->getDbConnection());
		}
		return $manager;
	}

	/**
	 * Saves the current SqlMap manager to cache.
	 * @param mixed $manager
	 * @return bool true if SqlMap manager was cached, false otherwise.
	 */
	protected function cacheSqlMapManager($manager)
	{
		if ($this->getEnableCache()) {
			$cache = $this->getApplication()->getCache();
			if ($cache !== null) {
				$dependencies = null;
				if ($this->getApplication()->getMode() !== TApplicationMode::Performance) {
					$dependencies = $manager->getCacheDependencies();
				}
				return $cache->set($this->getCacheKey(), $manager, 0, $dependencies);
			}
		}
		return false;
	}

	/**
	 * Loads SqlMap manager from cache. A cached manager built by another version
	 * of this package is ignored, so the caller rebuilds it and replaces the entry.
	 * @return ?TSqlMapManager SqlMap manager instance if load was successful, null otherwise.
	 */
	protected function loadCachedSqlMapManager()
	{
		if ($this->getEnableCache()) {
			$cache = $this->getApplication()->getCache();
			if ($cache !== null) {
				$manager = $cache->get($this->getCacheKey());
				if ($manager instanceof TSqlMapManager && $manager->getVersion() === TSqlMapManager::VERSION) {
					return $manager;
				}
			}
		}
		return null;
	}

	/**
	 * @return string SqlMap configuration file.
	 */
	public function getConfigFile()
	{
		return $this->_configFile;
	}

	/**
	 * @param string $value external configuration file in namespace format. The file
	 * extension must be '.xml'.
	 * @throws TConfigurationException if the file is invalid.
	 */
	public function setConfigFile($value)
	{
		if (is_file($value)) {
			$this->_configFile = $value;
		} else {
			$file = Prado::getPathOfNamespace($value, self::CONFIG_FILE_EXT);
			if ($file === null || !is_file($file)) {
				throw new TConfigurationException('sqlmap_configfile_invalid', $value);
			} else {
				$this->_configFile = $file;
			}
		}
	}

	/**
	 * Set true to cache sqlmap instances.
	 * @param bool $value true to cache sqlmap instance.
	 */
	public function setEnableCache($value)
	{
		$this->_enableCache = TPropertyValue::ensureBoolean($value);
	}

	/**
	 * @return bool true if configuration should be cached, false otherwise.
	 */
	public function getEnableCache()
	{
		return $this->_enableCache;
	}

	/**
	 * @return TSqlMapGateway SqlMap gateway instance.
	 */
	protected function createSqlMapGateway()
	{
		return $this->getSqlMapManager()->getSqlmapGateway();
	}

	/**
	 * Initialize the sqlmap if necessary, returns the TSqlMapGateway instance.
	 * @return TSqlMapGateway SqlMap gateway instance.
	 */
	public function getClient()
	{
		if ($this->_sqlmap === null) {
			$this->_sqlmap = $this->createSqlMapGateway();
		}
		return $this->_sqlmap;
	}
}
