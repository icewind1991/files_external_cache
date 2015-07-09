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
namespace OCA\Files_External_Cache\Tests\Command;

use OC\Files\Mount\MountPoint;
use OC\Files\Storage\Temporary;
use OCA\Files_External_Cache\Command\Sync;
use OCA\Files_External_Cache\Tests\TestCase;

class SyncTest extends TestCase {
	public function setUp() {
		parent::setUp();
		$this->userProvider->createUser('test', '');
	}

	public function tearDown() {
		parent::tearDown();
	}

	private function getMountProvider($storages) {
		$mounts = [];
		foreach ($storages as $mountPoint => $storage) {
			$mounts[] = new MountPoint($storage, $mountPoint);
		}
		$provider = $this->getMock('\OCP\Files\Config\IMountProvider');
		$provider->expects($this->any())
			->method('getMountsForUser')
			->will($this->returnValue($mounts));
		return $provider;
	}

	private function getSyncCommand($storage1, $storage2, $path) {
		$mountProvider = $this->getMountProvider([
			$this->getUniqueID() => $storage1,
			$this->getUniqueID() => $storage2
		]);
		\OC::$server->getMountProviderCollection()->registerProvider($mountProvider);

		return new Sync('test',
			$storage1->getStorageCache()->getNumericId(),
			$storage2->getStorageCache()->getNumericId(),
			$path,
			$storage1->filemtime($path)
		);
	}

	public function testBasicSync() {
		$storage1 = new Temporary();
		$storage2 = new Temporary();
		$storage1->file_put_contents('foo.txt', 'asd');
		$syncCommand = $this->getSyncCommand($storage1, $storage2, 'foo.txt');
		$syncCommand->handle();
		$this->assertTrue($storage2->file_exists('foo.txt'));
	}

	public function testSyncNewFolder() {
		$storage1 = new Temporary();
		$storage2 = new Temporary();
		$storage1->mkdir('asd/bar');
		$storage1->file_put_contents('asd/bar/foo.txt', 'asd');
		$syncCommand = $this->getSyncCommand($storage1, $storage2, 'asd/bar/foo.txt');
		$syncCommand->handle();
		$this->assertTrue($storage2->file_exists('asd/bar/foo.txt'));
	}

	public function testSyncChangedMTime() {
		$storage1 = new Temporary();
		$storage2 = new Temporary();
		$storage1->file_put_contents('foo.txt', 'asd');
		$storage1->touch('foo.txt', time() - 100);
		$syncCommand = $this->getSyncCommand($storage1, $storage2, 'foo.txt');
		$storage1->touch('foo.txt', time());
		$syncCommand->handle();
		$this->assertFalse($storage2->file_exists('foo.txt'));
	}

	public function testSyncStorageNotFound() {
		$storage1 = new Temporary();
		$syncCommand = new Sync('test',
			$storage1->getStorageCache()->getNumericId(),
			9999999,
			'foo.txt',
			time()
		);
		$syncCommand->handle();
		$this->assertTrue(true);
	}
}
