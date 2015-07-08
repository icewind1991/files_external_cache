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
namespace OCA\Files_External_Cache\Tests;

use Icewind\Streams\IteratorDirectory;
use OCA\Files_External_Cache\OnionDir;

class OnionDirTest extends TestCase {
	public function directoryProvider() {
		return [
			[
				[
					['foo', 'bar'],
					['asd']
				],
				['foo', 'bar', 'asd']
			],
			[
				[
					[],
					['.', '..'],
					['asd']
				],
				['asd']
			],
			[
				[
					['asd'],
					['asd']
				],
				['asd']
			],
			[
				[
					['foo', 'bar'],
					[],
					['foo', 'bar']
				],
				['foo', 'bar']
			]
		];
	}

	/**
	 * @param string[][] $files
	 * @param string[] $expected
	 * @dataProvider directoryProvider
	 */
	public function testBasicOnion($files, $expected) {
		$handles = array_map(function (array $files) {
			return IteratorDirectory::wrap($files);
		}, $files);
		$handle = OnionDir::wrap($handles);
		$this->assertHandleContent($expected, $handle);
	}

	/**
	 * @param string[][] $files
	 * @param string[] $expected
	 * @dataProvider directoryProvider
	 */
	public function testRewindOnion($files, $expected) {
		$handles = array_map(function (array $files) {
			return IteratorDirectory::wrap($files);
		}, $files);
		$handle = OnionDir::wrap($handles);
		readdir($handle);
		$this->assertNotHandleContent($expected, $handle);
		rewinddir($handle);
		$this->assertHandleContent($expected, $handle);
	}

	/**
	 * @expectedException \BadMethodCallException
	 */
	public function testInvalidHandles() {
		$context = stream_context_create(array(
			'dir' => array(
				'handles' => false)
		));
		stream_wrapper_register('oniondir', '\OCA\Files_External_Cache\OnionDir');
		try {
			opendir('oniondir://', $context);
		} catch (\Exception $e) {
			stream_wrapper_unregister('oniondir');
			throw $e;
		}
		stream_wrapper_unregister('oniondir');
	}

	/**
	 * @expectedException \BadMethodCallException
	 */
	public function testNoContenxt() {
		stream_wrapper_register('oniondir', '\OCA\Files_External_Cache\OnionDir');
		try {
			opendir('oniondir://');
		} catch (\Exception $e) {
			stream_wrapper_unregister('oniondir');
			throw $e;
		}

		stream_wrapper_unregister('oniondir');
	}
}
