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

use Icewind\Streams\Directory;

class OnionDir implements Directory {
	/**
	 * @var resource
	 */
	public $context;

	/**
	 * @var resource[]
	 */
	protected $handles;

	protected $activeHandle = 0;

	/**
	 * @var string[]
	 */
	protected $used = [];

	/**
	 * Load the source from the stream context and return the context options
	 *
	 * @param string $name
	 * @return array
	 * @throws \Exception
	 */
	protected function loadContext($name) {
		$context = stream_context_get_options($this->context);
		if (isset($context[$name])) {
			$context = $context[$name];
		} else {
			throw new \BadMethodCallException('Invalid context, "' . $name . '" options not set');
		}
		if (isset($context['handles']) and is_array($context['handles'])) {
			$this->handles = $context['handles'];
		} else {
			throw new \BadMethodCallException('Invalid context, handles not set');
		}
		return $context;
	}

	/**
	 * @param string $path
	 * @param array $options
	 * @return bool
	 */
	public function dir_opendir($path, $options) {
		$this->loadContext('dir');
		return true;
	}

	/**
	 * @return string | boolean
	 */
	public function dir_readdir() {
		$file = readdir($this->handles[$this->activeHandle]);
		if ($file === false) {
			if ($this->activeHandle < (count($this->handles) - 1)) {
				$this->activeHandle++;
				return $this->dir_readdir();
			} else {
				return false;
			}
		}
		if ($file === '.' or $file === '..') {
			return $this->dir_readdir();
		}
		if (array_search($file, $this->used) !== false) {
			return $this->dir_readdir();
		}
		$this->used[] = $file;
		return $file;
	}

	/**
	 * @return bool
	 */
	public function dir_closedir() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function dir_rewinddir() {
		foreach ($this->handles as $handle) {
			rewinddir($handle);
		}
		$this->activeHandle = 0;
		$this->used = [];
		return true;
	}

	/**
	 * Creates a directory handle that combines the files of multiple directory handles
	 *
	 * @param resource[] $handles
	 * @return resource
	 *
	 * @throws \BadMethodCallException
	 */
	public static function wrap(array $handles) {
		$context = stream_context_create(array(
			'dir' => array(
				'handles' => $handles)
		));
		stream_wrapper_register('oniondir', '\OCA\Files_External_Cache\Wrapper\OnionDir');
		$wrapped = opendir('oniondir://', $context);
		stream_wrapper_unregister('oniondir');
		return $wrapped;
	}
}
