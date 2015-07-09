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
namespace OCA\Files_External_Cache\Command;

use OC\Command\FileAccess;
use OCP\Command\ICommand;

class Sync implements ICommand {
	use FileAccess;

	/**
	 * @var string
	 */
	private $userId;

	/**
	 * @var int
	 */
	private $sourceStorageId;

	/**
	 * @var int
	 */
	private $targetStorageId;

	/**
	 * @var string
	 */
	private $file;

	/**
	 * @var int
	 */
	private $mtime;

	/**
	 * Sync constructor.
	 *
	 * @param string $userId
	 * @param int $sourceStorageId
	 * @param int $targetStorageId
	 * @param string $file
	 * @param int $mtime
	 */
	public function __construct($userId, $sourceStorageId, $targetStorageId, $file, $mtime) {
		$this->userId = $userId;
		$this->sourceStorageId = $sourceStorageId;
		$this->targetStorageId = $targetStorageId;
		$this->file = $file;
		$this->mtime = $mtime;
	}

	public function handle() {
		\OC_Util::setupFS($this->userId);
		$sourceStorage = $this->getStorageById($this->sourceStorageId);
		$targetStorage = $this->getStorageById($this->targetStorageId);

		if (is_null($sourceStorage) or
			is_null($targetStorage) or
			$sourceStorage->filemtime($this->file) > $this->mtime
		) {
			return;
		}

		if (strpos($this->file, '/')) {
			$targetStorage->mkdir(dirname($this->file));
		}
		$targetStorage->copyFromStorage($sourceStorage, $this->file, $this->file);
	}

	private function getStorageById($id) {
		$mountManager = \OC::$server->getMountManager();
		$mounts = $mountManager->findByNumericId($id);
		if (count($mounts) > 0) {
			return $mounts[0]->getStorage();
		} else {
			return null;
		}
	}
}
