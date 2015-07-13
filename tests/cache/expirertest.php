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
use OCA\Files_External_Cache\Cache\Expirer;
use OCA\Files_External_Cache\Command\Sync;
use OCA\Files_External_Cache\Tests\TestCase;

class ExpirerTest extends TestCase {
	public function testGetFilesEmpty() {
		$storage = new Temporary();
		$expirer = new Expirer($storage, 100);
		$this->assertEquals([], $expirer->getFilesToExpire());
	}

	public function testGetFilesNone() {
		$storage = new Temporary();
		$storage->mkdir('sub');
		$storage->file_put_contents('foo.txt', 'asd');
		$storage->file_put_contents('sub/bar.txt', 'asd');
		$expirer = new Expirer($storage, 100);
		$this->assertEquals([], $expirer->getFilesToExpire());
	}

	public function testGetFilesRoot() {
		$storage = new Temporary();
		$storage->mkdir('sub');
		$storage->file_put_contents('foo.txt', 'asd');
		$storage->file_put_contents('bar.txt', 'foobar');
		$storage->touch('bar.txt', time() - 100);

		$expirer = new Expirer($storage, 6);
		$this->assertEquals(['/bar.txt'], $expirer->getFilesToExpire());

		$storage->touch('foo.txt', time() - 200);

		$expirer = new Expirer($storage, 6);
		$this->assertEquals(['/foo.txt'], $expirer->getFilesToExpire());
	}

	public function testGetFilesSub() {
		$storage = new Temporary();
		$storage->mkdir('sub');
		$storage->file_put_contents('foo.txt', 'asd');
		$storage->file_put_contents('bar.txt', 'foobar');
		$storage->file_put_contents('sub/asd.txt', 'qwerty');
		$storage->touch('bar.txt', time() - 100);
		$storage->touch('sub/asd.txt', time() - 150);

		$expirer = new Expirer($storage, 9);
		$this->assertEquals(['/sub/asd.txt'], $expirer->getFilesToExpire());

		$expirer = new Expirer($storage, 6);
		$this->assertEquals(['/bar.txt', '/sub/asd.txt'], $expirer->getFilesToExpire());
	}

	public function testExpireSub() {
		$storage = new Temporary();
		$storage->mkdir('sub');
		$storage->file_put_contents('foo.txt', 'asd');
		$storage->file_put_contents('bar.txt', 'foobar');
		$storage->file_put_contents('sub/asd.txt', 'qwerty');
		$storage->touch('bar.txt', time() - 100);
		$storage->touch('sub/asd.txt', time() - 150);

		$expirer = new Expirer($storage, 9);
		$expirer->expire();

		$this->assertTrue($storage->file_exists('foo.txt'));
		$this->assertTrue($storage->file_exists('bar.txt'));
		$this->assertFalse($storage->file_exists('sub/asd.txt'));
	}
}
