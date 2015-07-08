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
namespace OCA\Files_External_Cache;

use OC\Files\Storage\Common;
use OC\Files\Storage\Storage;
use OCP\Lock\ILockingProvider;

class OnionWrapper extends Common {
	/**
	 * @var \OC\Files\Storage\Storage[] $storage
	 */
	protected $storages;

	protected $cache;

	/**
	 * @param array $parameters
	 */
	public function __construct($parameters) {
		$this->storages = $parameters['storages'];
	}
	
	/**
	 * Get the first storage where a path exists
	 *
	 * @param string $path
	 * @return \OC\Files\Storage\Storage
	 */
	protected function getStorageForPath($path) {
		foreach ($this->storages as $storage) {
			if ($storage->file_exists($path)) {
				return $storage;
			}
		}
		return null;
	}

	/**
	 * @param string $path
	 * @param callable $operation
	 * @return mixed
	 */
	protected function runOnFirstStorage($path, callable $operation) {
		$storage = $this->getStorageForPath($path);
		if (!$storage) {
			return false;
		}
		return $operation($storage, $path);
	}

	/**
	 * @param string $path
	 * @param callable $operation
	 * @return bool
	 */
	protected function runOnAllStorages($path, callable $operation) {
		$result = true;
		foreach ($this->storages as $storage) {
			$newResult = $operation($storage, $path);
			$result = ($result and $newResult);
		}
		return $result;
	}

	protected function bindOperation($name) {
		return function (Storage $storage, $path) use ($name) {
			return call_user_func([$storage, $name], $path);
		};
	}

	/**
	 * {@inheritdoc}
	 */
	public function getId() {
		$ids = array_map(function (Storage $storage) {
			return $storage->getId();
		}, $this->storages);
		return 'onion::' . md5(implode('+', $ids));
	}

	/**
	 * {@inheritdoc}
	 */
	public function mkdir($path) {
		return $this->storages[0]->mkdir($path);
	}

	/**
	 * {@inheritdoc}
	 */
	public function rmdir($path) {
		return $this->runOnAllStorages($path, $this->bindOperation('rmdir'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function opendir($path) {
		$handles = array_map(function (Storage $storage) use ($path) {
			return $storage->opendir($path);
		}, $this->storages);
		$handles = array_filter($handles, function ($handle) {
			return is_resource($handle);
		});
		return OnionDir::wrap($handles);
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_dir($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('is_dir'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_file($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('is_file'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function stat($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('stat'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function filetype($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('filetype'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function filesize($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('filesize'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function isCreatable($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('isCreatable'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function isReadable($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('isReadable'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function isUpdatable($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('isUpdatable'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function isDeletable($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('isDeletable'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function isSharable($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('isSharable'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPermissions($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('getPermissions'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function file_exists($path) {
		return $this->runOnFirstStorage($path, function () {
			return true;
		});
	}

	/**
	 * {@inheritdoc}
	 */
	public function filemtime($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('filemtime'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function file_get_contents($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('file_get_contents'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function file_put_contents($path, $data) {
		$storage = $this->storages[0];
		$storage->mkdir(dirname($path));
		return $storage->file_put_contents($path, $data);
	}

	/**
	 * {@inheritdoc}
	 */
	public function unlink($path) {
		return $this->runOnAllStorages($path, $this->bindOperation('unlink'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function rename($source, $target) {
		return $this->runOnAllStorages($source, function (Storage $storage) use ($source, $target) {
			return $storage->rename($source, $target);
		});
	}

	/**
	 * {@inheritdoc}
	 */
	public function copy($source, $target) {
		return $this->runOnFirstStorage($source, function (Storage $storage) use ($source, $target) {
			return $storage->copy($source, $target);
		});
	}

	/**
	 * {@inheritdoc}
	 */
	public function fopen($path, $mode) {
		if ($mode[0] === 'r') {
			return $this->runOnFirstStorage($path, function (Storage $storage, $path) use ($mode) {
				return $storage->fopen($path, $mode);
			});
		} else {
			$storage = $this->storages[0];
			$storage->mkdir(dirname($path));
			return $storage->fopen($path, $mode);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMimeType($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('getMimeType'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function hash($type, $path, $raw = false) {
		return $this->runOnFirstStorage($path, function (Storage $storage, $path) use ($type, $raw) {
			return $storage->hash($type, $path, $raw);
		});
	}

	/**
	 * {@inheritdoc}
	 */
	public function free_space($path) {
		$storage = $this->storages[count($this->storages) - 1];
		return $storage->free_space($storage);
	}

	/**
	 * {@inheritdoc}
	 */
	public function search($query) {
		return []; //unused
	}

	/**
	 * {@inheritdoc}
	 */
	public function touch($path, $mtime = null) {
		return $this->runOnAllStorages($path, function (Storage $storage, $path) use ($mtime) {
			return $storage->touch($path, $mtime);
		});
	}

	/**
	 * {@inheritdoc}
	 */
	public function getLocalFile($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('getLocalFile'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function getLocalFolder($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('getLocalFolder'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function hasUpdated($path, $time) {
		return array_reduce($this->storages, function ($updated, Storage $storage) use ($path, $time) {
			return $updated or $storage->hasUpdated($path, $time);
		}, false);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getETag($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('getEtag'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function test() {
		return $this->runOnAllStorages('', $this->bindOperation('test'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function isLocal() {
		return $this->runOnAllStorages('', $this->bindOperation('isLocal'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function instanceOfStorage($class) {
		return is_a($this, $class) or $this->runOnAllStorages('', function (Storage $storage) use ($class) {
			return $storage->instanceOfStorage($class);
		});
	}

	/**
	 * {@inheritdoc}
	 */
	public function __call($method, $args) {
		return $this->runOnFirstStorage($args[0], function (Storage $storage) use ($method, $args) {
			return call_user_func_array([$storage, $method], $args);
		});
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDirectDownload($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('getDirectDownload'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function verifyPath($path, $fileName) {
		return $this->runOnAllStorages($path, $this->bindOperation('verifyPath'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function copyFromStorage(\OCP\Files\Storage $sourceStorage, $sourceInternalPath, $targetInternalPath) {
		$storage = $this->storages[0];
		$storage->mkdir(dirname($targetInternalPath));

		$storage->copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
	}

	/**
	 * {@inheritdoc}
	 */
	public function moveFromStorage(\OCP\Files\Storage $sourceStorage, $sourceInternalPath, $targetInternalPath) {
		$storage = $this->storages[0];
		$storage->mkdir(dirname($targetInternalPath));

		$storage->moveFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMetaData($path) {
		return $this->runOnFirstStorage($path, $this->bindOperation('getMetaData'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function acquireLock($path, $type, ILockingProvider $provider) {
		return $this->runOnAllStorages($path, function (Storage $storage, $path) use ($type, $provider) {
			$storage->acquireLock($path, $type, $provider);
		});
	}

	/**
	 * {@inheritdoc}
	 */
	public function releaseLock($path, $type, ILockingProvider $provider) {
		return $this->runOnAllStorages($path, function (Storage $storage, $path) use ($type, $provider) {
			$storage->releaseLock($path, $type, $provider);
		});
	}

	/**
	 * {@inheritdoc}
	 */
	public function changeLock($path, $type, ILockingProvider $provider) {
		return $this->runOnAllStorages($path, function (Storage $storage, $path) use ($type, $provider) {
			$storage->changeLock($path, $type, $provider);
		});
	}
}
