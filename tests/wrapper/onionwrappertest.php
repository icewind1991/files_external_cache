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

use OC\Files\Storage\Temporary;
use OCA\Files_External_Cache\Tests\TestCase;
use OCA\Files_External_Cache\Wrapper\OnionWrapper;

class OnionWrapperTest extends TestCase {
	/**
	 * files from earlier storages overwrite the later ones
	 */
	public function testOverwriteFile() {
		$storage1 = new Temporary();
		$storage2 = new Temporary();
		$onion = new OnionWrapper(['storages' => [$storage1, $storage2]]);

		$storage1->file_put_contents('foo.txt', 'asd');
		$storage2->file_put_contents('foo.txt', 'bar');
		$storage2->file_put_contents('bar.txt', 'bar');

		$this->assertTrue($onion->file_exists('foo.txt'));
		$this->assertTrue($onion->file_exists('bar.txt'));

		$this->assertEquals('asd', $onion->file_get_contents('foo.txt'));
		$this->assertEquals('bar', $onion->file_get_contents('bar.txt'));
	}

	/**
	 * directories are merged
	 */
	public function testOpenDirOverwrite() {
		$storage1 = new Temporary();
		$storage2 = new Temporary();
		$onion = new OnionWrapper(['storages' => [$storage1, $storage2]]);

		$storage1->file_put_contents('foo.txt', 'asd');
		$storage2->file_put_contents('foo.txt', 'bar');
		$storage2->file_put_contents('bar.txt', 'bar');

		$handle = $onion->opendir('');
		$this->assertHandleContent(['bar.txt', 'foo.txt'], $handle);
	}

	/**
	 * a read fopen happens on the fist storage that has the file
	 */
	public function testFopenRead() {
		$storage1 = new Temporary();
		$storage2 = new Temporary();
		$storage3 = new Temporary();
		$onion = new OnionWrapper(['storages' => [$storage1, $storage2, $storage3]]);

		$storage2->file_put_contents('foo.txt', 'asd');
		$storage3->file_put_contents('foo.txt', 'bar');

		$fh = $onion->fopen('foo.txt', 'r');
		$this->assertEquals('asd', stream_get_contents($fh));
	}

	/**
	 * a write fopen always happens on the first storage
	 */
	public function testFopenWrite() {
		$storage1 = new Temporary();
		$storage2 = new Temporary();
		$storage3 = new Temporary();
		$onion = new OnionWrapper(['storages' => [$storage1, $storage2, $storage3]]);

		$storage2->file_put_contents('foo.txt', 'asd');
		$storage3->file_put_contents('foo.txt', 'bar');

		$fh = $onion->fopen('foo.txt', 'w');
		fwrite($fh, 'foo');
		fclose($fh);
		$this->assertTrue($storage1->file_exists('foo.txt'));
		$this->assertEquals('foo', $storage1->file_get_contents('foo.txt'));
		$this->assertEquals('asd', $storage2->file_get_contents('foo.txt'));
		$this->assertEquals('bar', $storage3->file_get_contents('foo.txt'));
	}

	/**
	 * unlink happens on all storages
	 */
	public function testUnlink() {
		$storage1 = new Temporary();
		$storage2 = new Temporary();
		$storage3 = new Temporary();
		$onion = new OnionWrapper(['storages' => [$storage1, $storage2, $storage3]]);

		$storage2->file_put_contents('foo.txt', 'asd');
		$storage3->file_put_contents('foo.txt', 'bar');

		$onion->unlink('foo.txt');
		$this->assertFalse($storage1->file_exists('foo.txt'));
		$this->assertFalse($storage2->file_exists('foo.txt'));
		$this->assertFalse($storage3->file_exists('foo.txt'));
	}

	/**
	 * Copy only happens on the first storage that has the file
	 */
	public function testCopy() {
		$storage1 = new Temporary();
		$storage2 = new Temporary();
		$storage3 = new Temporary();
		$onion = new OnionWrapper(['storages' => [$storage1, $storage2, $storage3]]);

		$storage2->file_put_contents('foo.txt', 'asd');
		$storage3->file_put_contents('foo.txt', 'bar');

		$onion->copy('foo.txt', 'bar.txt');
		$this->assertFalse($storage1->file_exists('foo.txt'));
		$this->assertTrue($storage2->file_exists('foo.txt'));
		$this->assertTrue($storage3->file_exists('foo.txt'));

		$this->assertFalse($storage1->file_exists('bar.txt'));
		$this->assertTrue($storage2->file_exists('bar.txt'));
		$this->assertFalse($storage3->file_exists('bar.txt'));
	}

	/**
	 * rename happens on all storages that have the file (since it involves an removal)
	 */
	public function testRename() {
		$storage1 = new Temporary();
		$storage2 = new Temporary();
		$storage3 = new Temporary();
		$onion = new OnionWrapper(['storages' => [$storage1, $storage2, $storage3]]);

		$storage2->file_put_contents('foo.txt', 'asd');
		$storage3->file_put_contents('foo.txt', 'bar');

		$onion->rename('foo.txt', 'bar.txt');
		$this->assertFalse($storage1->file_exists('foo.txt'));
		$this->assertFalse($storage2->file_exists('foo.txt'));
		$this->assertFalse($storage3->file_exists('foo.txt'));

		$this->assertFalse($storage1->file_exists('bar.txt'));
		$this->assertTrue($storage2->file_exists('bar.txt'));
		$this->assertTrue($storage3->file_exists('bar.txt'));
	}

	/**
	 * touch happens on all storages
	 */
	public function testTouch() {
		$storage1 = new Temporary();
		$storage2 = new Temporary();
		$storage3 = new Temporary();
		$onion = new OnionWrapper(['storages' => [$storage1, $storage2, $storage3]]);

		$storage2->file_put_contents('foo.txt', 'asd');
		$storage3->file_put_contents('foo.txt', 'bar');

		$onion->touch('foo.txt', 100);
		$this->assertTrue($storage1->file_exists('foo.txt'));
		$this->assertTrue($storage2->file_exists('foo.txt'));
		$this->assertTrue($storage3->file_exists('foo.txt'));

		$this->assertEquals(100, $storage1->filemtime('foo.txt'));
		$this->assertEquals(100, $storage2->filemtime('foo.txt'));
		$this->assertEquals(100, $storage3->filemtime('foo.txt'));
	}

	/**
	 * hasUpdated checks against all storage
	 */
	public function testhasUpdated() {
		$storage1 = new Temporary();
		$storage2 = new Temporary();
		$storage3 = new Temporary();
		$onion = new OnionWrapper(['storages' => [$storage1, $storage2, $storage3]]);

		$storage2->file_put_contents('foo.txt', 'asd');
		$storage3->file_put_contents('foo.txt', 'bar');

		$storage3->touch('foo.txt', 100);
		$this->assertTrue($onion->hasUpdated('foo.txt', 200));
	}

	/**
	 * file_put_contents always happens on the first storage
	 */
	public function testPutContents() {
		$storage1 = new Temporary();
		$storage2 = new Temporary();
		$storage3 = new Temporary();
		$onion = new OnionWrapper(['storages' => [$storage1, $storage2, $storage3]]);

		$storage2->file_put_contents('foo.txt', 'asd');
		$storage3->file_put_contents('foo.txt', 'bar');

		$onion->file_put_contents('bar.txt', 'asd');

		$this->assertTrue($storage1->file_exists('bar.txt'));
		$this->assertFalse($storage2->file_exists('bar.txt'));
		$this->assertFalse($storage3->file_exists('bar.txt'));
	}

	/**
	 * mkdir happens on the first storage
	 */
	public function testMkDir() {
		$storage1 = new Temporary();
		$storage2 = new Temporary();
		$storage3 = new Temporary();
		$onion = new OnionWrapper(['storages' => [$storage1, $storage2, $storage3]]);

		$onion->mkdir('foo');

		$this->assertTrue($storage1->file_exists('foo'));
		$this->assertFalse($storage2->file_exists('foo'));
		$this->assertFalse($storage3->file_exists('foo'));
	}

	/**
	 * rmdir happens on every storage
	 */
	public function testRmDir() {
		$storage1 = new Temporary();
		$storage2 = new Temporary();
		$storage3 = new Temporary();
		$onion = new OnionWrapper(['storages' => [$storage1, $storage2, $storage3]]);

		$storage2->mkdir('foo');
		$storage3->mkdir('foo');

		$onion->rmdir('foo');

		$this->assertFalse($storage1->file_exists('foo'));
		$this->assertFalse($storage2->file_exists('foo'));
		$this->assertFalse($storage3->file_exists('foo'));
	}

	/**
	 * when writing to a folder that doest exist on the first storage we need to create it
	 */
	public function testWriteToFolder() {
		$storage1 = new Temporary();
		$storage2 = new Temporary();
		$storage3 = new Temporary();
		$onion = new OnionWrapper(['storages' => [$storage1, $storage2, $storage3]]);

		$storage2->mkdir('foo');

		$onion->file_put_contents('foo/asd.txt', 'foo');

		$this->assertTrue($storage1->file_exists('foo/asd.txt'));
		$this->assertTrue($storage1->file_exists('foo'));
		$this->assertTrue($storage2->file_exists('foo'));
		$this->assertFalse($storage3->file_exists('foo'));
	}
}
