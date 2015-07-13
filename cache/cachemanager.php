<?php
/**
 * @author Robin Appelman <icewind@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Files_External_Cache\Cache;

use OC\Files\Filesystem;
use OC\Files\Mount\MountPoint;
use OC\Files\Storage\Local;
use OC\Files\Storage\Storage;
use OCA\Files_External_Cache\Wrapper\SyncOnionWrapper;
use OCP\Command\IBus;

class CacheManager {
	/**
	 * @var string
	 */
	protected $dataFolder;

	/**
	 * @var \OCP\Command\IBus
	 */
	protected $commandBus;

	/**
	 * @var Storage[]
	 */
	protected $cacheStorages = [];

	/**
	 * @param \OCP\Command\IBus $commandBus
	 * @param string $dataFolder
	 */
	public function __construct(IBus $commandBus, $dataFolder) {
		$this->commandBus = $commandBus;
		$this->dataFolder = $dataFolder;
	}


	/**
	 * @param Storage $backendStorage
	 * @param string $userId
	 * @return Storage
	 */
	protected function getCacheStorage(Storage $backendStorage, $userId) {
		// md5 the id to ensure it's a valid path
		$backendId = md5($backendStorage->getId());
		$cacheFolder = $this->dataFolder . '/' . $userId . '/files_external_cache/' . $backendId;
		if (!isset($this->cacheStorages[$cacheFolder])) {
			if (!is_dir($cacheFolder)) {
				mkdir($cacheFolder, 0755, true);
			}
			$this->cacheStorages[$cacheFolder] = new Local(['datadir' => $cacheFolder]);
		}
		return $this->cacheStorages[$cacheFolder];
	}

	/**
	 * @param string $mountPoint
	 * @param Storage $storage
	 * @param MountPoint $mount
	 * @return Storage
	 */
	public function applyCacheWrapper($mountPoint, Storage $storage, MountPoint $mount) {
		if ($this->shouldApplyCache($mount, $storage)) {
			$userId = $this->getUserIdFromMount($mount);
			$cacheStorage = $this->getCacheStorage($storage, $userId);
			return new SyncOnionWrapper([
				'storages' => [
					$cacheStorage,
					$storage
				],
				'bus' => $this->commandBus,
				'userid' => $userId
			]);
		} else {
			return $storage;
		}
	}

	protected function getUserIdFromMount(MountPoint $mount) {
		$parts = explode('/', $mount->getMountPoint());
		if (count($parts) <= 2) {
			return null;
		}
		return $parts[1];
	}

	protected function shouldApplyCache(MountPoint $mount, Storage $storage) {
		// TODO add mount option
		if (is_null($this->getUserIdFromMount($mount))) {
			return false;
		}
		return !$storage->isLocal();
	}

	public function setupStorageWrapper() {
		Filesystem::addStorageWrapper('files_external_cache', [$this, 'applyCacheWrapper']);
	}
}
