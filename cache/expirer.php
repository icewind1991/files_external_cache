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

use OC\Files\Storage\Storage;

class Expirer {
	/**
	 * @var Storage
	 */
	protected $storage;

	/**
	 * @var int
	 */
	protected $sizeLimit;

	/**
	 * Expirer constructor.
	 *
	 * @param Storage $storage
	 * @param int $sizeLimit size in bytes
	 */
	public function __construct(Storage $storage, $sizeLimit) {
		$this->storage = $storage;
		$this->sizeLimit = $sizeLimit;
	}

	public function getFilesToExpire() {
		$files = $this->getFilesFromFolder('');
		uasort($files, function ($a, $b) {
			return $a['mtime'] < $b['mtime'];
		});
		$foundSize = 0;
		foreach ($files as $path => $file) {
			if ($file['size'] + $foundSize > $this->sizeLimit) {
				break;
			}
			$foundSize += $file['size'];
			unset($files[$path]);
		}
		return array_keys($files);
	}

	public function expire() {
		$files = $this->getFilesToExpire();
		foreach ($files as $file) {
			$this->storage->unlink($file);
		}
	}

	/**
	 * @param string $path
	 * @return array[] [$path => ['mtime' => $mtime, 'size' => $size]]
	 */
	protected function getFilesFromFolder($path) {
		$handle = $this->storage->opendir($path);
		$result = [];
		while (($file = readdir($handle)) !== false) {
			if ($file !== '.' and $file !== '..') {
				$filePath = $path . '/' . $file;
				if ($this->storage->is_dir($filePath)) {
					$result = array_merge($result, $this->getFilesFromFolder($filePath));
				} else {
					$stat = $this->storage->stat($filePath);
					$result[$filePath] = [
						'mtime' => $stat['mtime'],
						'size' => $stat['size']
					];
				}
			}
		}
		return $result;
	}
}
