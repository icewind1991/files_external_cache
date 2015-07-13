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
namespace OCA\Files_External_Cache\Wrapper;

use Icewind\Streams\CallbackWrapper;
use OCA\Files_External_Cache\Command\Sync;
use OC\Files\Storage\Storage;

class SyncOnionWrapper extends OnionWrapper {
	/**
	 * @var \OCP\Command\IBus
	 */
	protected $commandBus;

	/**
	 * @var string
	 */
	protected $userId;

	/**
	 * @var int[]
	 */
	protected $storageIds;

	public function __construct($params) {
		if (count($params['storages']) !== 2) {
			throw new \InvalidArgumentException('sync onion wrapper only works with 2 storages');
		}
		parent::__construct($params);
		$this->commandBus = $params['bus'];
		$this->userId = $params['userid'];
		$this->storageIds = array_map(function (Storage $storage) {
			return $storage->getStorageCache()->getNumericId();
		}, $this->storages);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getId() {
		return $this->storages[1]->getId();
	}

	/**
	 * @return Storage
	 */
	public function getBackendStorage() {
		return $this->storages[1];
	}

	/**
	 * @return Storage
	 */
	public function getCacheStorage() {
		return $this->storages[0];
	}

	/**
	 * Add a sync command to the command bus for the target path
	 *
	 * @param string $path
	 */
	protected function scheduleSync($path) {
		$command = new Sync(
			$this->userId,
			$this->storageIds[0],
			$this->storageIds[1],
			$path,
			$this->storages[0]->filemtime($path)
		);
		$this->commandBus->push($command);
	}

	/**
	 * {@inheritdoc}
	 */
	public function file_put_contents($path, $data) {
		$result = parent::file_put_contents($path, $data);
		if ($result) {
			$this->scheduleSync($path);
		}
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function file_get_contents($path) {
		if (!$this->storages[0]->file_exists($path)) {
			$this->storages[0]->copyFromStorage($this->storages[1], $path, $path);
		}
		return $this->storages[0]->file_get_contents($path);
	}

	/**
	 * {@inheritdoc}
	 */
	public function copy($source, $target) {
		$result = parent::copy($source, $target);
		if ($result) {
			$this->scheduleSync($target);
		}
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function rename($source, $target) {
		$result = parent::rename($source, $target);
		if ($result and !$this->storages[1]->file_exists($target)) {
			$this->scheduleSync($target);
		}
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function fopen($path, $mode) {
		$fh = parent::fopen($path, $mode);
		if ($mode[0] !== 'r' and is_resource($fh)) {
			// upload the file on write
			return CallbackWrapper::wrap($fh, null, null, function () use ($path) {
				$this->scheduleSync($path);
			});
		} elseif ($fh and !$this->storages[0]->file_exists($path)) {
			// copy the file if needed
			$this->storages[0]->mkdir(dirname($path));
			$target = $this->storages[0]->fopen($path, 'w');
			return CopyStreamWrapper::wrap($fh, $target, function ($success) use ($path) {
				if (!$success) {
					$this->storages[0]->unlink($path);
				}
			});
		} else {
			return $fh;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function copyFromStorage(\OCP\Files\Storage $sourceStorage, $sourceInternalPath, $targetInternalPath) {
		$result = parent::copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
		$this->scheduleSync($targetInternalPath);
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function moveFromStorage(\OCP\Files\Storage $sourceStorage, $sourceInternalPath, $targetInternalPath) {
		$result = parent::moveFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
		$this->scheduleSync($targetInternalPath);
		return $result;
	}
}
