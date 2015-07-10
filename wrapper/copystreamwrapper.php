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

use Icewind\Streams\Wrapper;

/**
 * Stream wrapper that copies data from one stream to another on read
 */
class CopyStreamWrapper extends Wrapper {
	/**
	 * @var resource
	 */
	private $target;

	/**
	 * @var callable
	 */
	private $callback;

	/**
	 * Whether the copying so fat is complete or if we need to stop copying
	 *
	 * @var bool
	 */
	private $aborted = false;

	/**
	 * @param resource $source
	 * @param resource $target
	 * @param callable $callback callback called when the stream is closed to indicate if the complete file was copied
	 * @return resource
	 *
	 * @throws \BadMethodCallException
	 */
	public static function wrap($source, $target, callable $callback) {
		$context = stream_context_create(array(
			'copy' => array(
				'source' => $source,
				'target' => $target,
				'callback' => $callback
			)
		));
		if (!is_resource($source) or !is_resource($target)) {
			throw new \BadMethodCallException('Invalid source or target');
		}
		stream_wrapper_register('copy', '\OCA\Files_External_Cache\Wrapper\CopyStreamWrapper');
		$wrapped = fopen('copy://', 'r+', false, $context);
		stream_wrapper_unregister('copy');
		return $wrapped;
	}

	public function stream_open($path, $mode, $options, &$opened_path) {
		$context = $this->loadContext('copy');

		$this->target = $context['target'];
		$this->callback = $context['callback'];
		return true;
	}

	public function stream_read($count) {
		$result = parent::stream_read($count);
		if ($result !== false and !$this->aborted) {
			fwrite($this->target, $result);
		}
		return $result;
	}

	public function stream_seek($offset, $whence = SEEK_SET) {
		$result = parent::stream_seek($offset, $whence);

		// check if we're seeking ahead of what we've already copied
		if (($whence === SEEK_CUR and $offset > 0) or
			($whence === SEEK_SET and $offset > ftell($this->target)) or
			$this->aborted
		) {
			$this->aborted = true;
		} else {
			fseek($this->target, $offset, $whence);
		}
		return $result;
	}

	public function stream_close() {
		$callback = $this->callback;
		fclose($this->target);
		$callback((!$this->aborted) and feof($this->source));
		return parent::stream_close();
	}
}
