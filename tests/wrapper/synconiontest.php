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
namespace OCA\Files_External_Cache\Tests\Wrapper;

use OC\Files\Mount\MountPoint;
use OC\Files\Storage\Temporary;
use OCA\Files_External_Cache\Tests\TestCase;
use OCA\Files_External_Cache\Wrapper\SyncOnionWrapper;
use OCP\Files\Storage;
use OCP\IUser;

class SyncOnionTest extends TestCase {
	/**
	 * @var Storage
	 */
	private $storage1;

	/**
	 * @var Storage
	 */
	private $storage2;

	/**
	 * @var Storage
	 */
	private $onion;

	public function setUp() {
		parent::setUp();
		$this->storage1 = new Temporary();
		$this->storage2 = new Temporary();

		$mounts = [
			new MountPoint($this->storage1, '/foo'),
			new MountPoint($this->storage2, '/bar')
		];

		$userId = $this->getUniqueID();

		$provider = $this->getMock('\OCP\Files\Config\IMountProvider');
		$provider->expects($this->any())
			->method('getMountsForUser')
			->will($this->returnCallback(function (IUser $user) use ($mounts, $userId) {
				if ($user->getUID() === $userId) {
					return $mounts;
				} else {
					return [];
				}
			}));

		\OC::$server->getMountProviderCollection()->registerProvider($provider);

		$this->userProvider->createUser($userId, '');

		$this->onion = new SyncOnionWrapper([
			'storages' => [$this->storage1, $this->storage2],
			'userid' => $userId,
			'bus' => \OC::$server->getCommandBus()
		]);
	}

	public function testSyncFilePutContents() {
		$this->onion->file_put_contents('foo.txt', 'bar');
		$this->assertFalse($this->storage2->file_exists('foo.txt'));
		$this->runCommands();
		$this->assertTrue($this->storage2->file_exists('foo.txt'));
		$this->assertEquals('bar', $this->storage2->file_get_contents('foo.txt'));
	}

	public function testSyncCopy() {
		$this->onion->file_put_contents('foo.txt', 'bar');
		$this->runCommands();
		$this->onion->copy('foo.txt', 'bar.txt');
		$this->assertFalse($this->storage2->file_exists('bar.txt'));
		$this->runCommands();
		$this->assertTrue($this->storage2->file_exists('bar.txt'));
		$this->assertEquals('bar', $this->storage2->file_get_contents('bar.txt'));
	}

	public function testCopyFromStorage() {
		$storage = new Temporary();
		$storage->file_put_contents('asd.txt', 'bar');
		$this->onion->copyFromStorage($storage, 'asd.txt', 'bar.txt');
		$this->assertFalse($this->storage2->file_exists('bar.txt'));
		$this->runCommands();
		$this->assertTrue($this->storage2->file_exists('bar.txt'));
		$this->assertEquals('bar', $this->storage2->file_get_contents('bar.txt'));
	}

	public function testMoveFromStorage() {
		$storage = new Temporary();
		$storage->file_put_contents('asd.txt', 'bar');
		$this->onion->moveFromStorage($storage, 'asd.txt', 'bar.txt');
		$this->assertFalse($this->storage2->file_exists('bar.txt'));
		$this->runCommands();
		$this->assertTrue($this->storage2->file_exists('bar.txt'));
		$this->assertEquals('bar', $this->storage2->file_get_contents('bar.txt'));
	}

	public function testSyncFopen() {
		$fh = $this->onion->fopen('foo.txt', 'w');
		fwrite($fh, 'foo');
		$this->runCommands();
		$this->assertFalse($this->storage2->file_exists('foo.txt'));
		fclose($fh);
		$this->runCommands();
		$this->assertTrue($this->storage2->file_exists('foo.txt'));
		$this->assertEquals('foo', $this->storage2->file_get_contents('foo.txt'));
	}

	public function testSyncFopenNotExists() {
		$fh = $this->onion->fopen('foo.txt', 'r');
		$this->assertFalse($fh);
	}

	public function testSyncFopenReadFull() {
		$this->storage2->file_put_contents('foo.txt', 'bar');
		$this->assertFalse($this->storage1->file_exists('foo.txt'));
		$fh = $this->onion->fopen('foo.txt', 'r');
		$this->assertEquals('bar', stream_get_contents($fh));
		fclose($fh);
		$this->assertTrue($this->storage1->file_exists('foo.txt'));
		$this->assertEquals('bar', $this->storage1->file_get_contents('foo.txt'));
	}

	public function testSyncFileGetContent() {
		$this->storage2->file_put_contents('foo.txt', 'bar');
		$this->assertFalse($this->storage1->file_exists('foo.txt'));
		$this->assertEquals('bar', $this->onion->file_get_contents('foo.txt'));
		$this->assertTrue($this->storage1->file_exists('foo.txt'));
		$this->assertEquals('bar', $this->storage1->file_get_contents('foo.txt'));
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testInvalidStorageCount() {
		$this->onion = new SyncOnionWrapper([
			'storages' => [$this->storage1],
			'userid' => '',
			'bus' => \OC::$server->getCommandBus()
		]);
	}
}
