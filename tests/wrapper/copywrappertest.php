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

use Icewind\Streams\IteratorDirectory;
use OCA\Files_External_Cache\Tests\TestCase;
use OCA\Files_External_Cache\Wrapper\CopyStreamWrapper;
use OCA\Files_External_Cache\Wrapper\OnionDir;

class CopyWrapperTest extends TestCase {
	private function getSourceStream($data) {
		$handle = fopen('php://temp', 'w+');
		fwrite($handle, $data);
		rewind($handle);
		return $handle;
	}

	private function assertStreamContent($stream, $content) {
		rewind($stream);
		$this->assertEquals($content, stream_get_contents($stream));
	}

	public function streamContentProvider() {
		return [
			['foobar'],
			[''],
			[file_get_contents(__FILE__)]
		];
	}

	/**
	 * @param string $content
	 * @dataProvider streamContentProvider
	 */
	public function testBasicCopy($content) {
		$source = $this->getSourceStream($content);
		$target = fopen('php://temp', 'w+');
		$stream = CopyStreamWrapper::wrap($source, $target, function ($success) {
			$this->assertTrue($success);
		});

		stream_get_contents($stream);

		$this->assertStreamContent($target, $content);
		fclose($stream);
	}

	/**
	 * @param string $content
	 * @dataProvider streamContentProvider
	 */
	public function testCopyUnfinished($content) {
		$source = $this->getSourceStream($content);
		$target = fopen('php://temp', 'w+');
		$stream = CopyStreamWrapper::wrap($source, $target, function ($success) use ($source) {
			$this->assertFalse(feof($source), 'Failed asserting source stream is not eof');
			$this->assertFalse($success, 'Failed asserting that stream is not completely copied');
		});

		stream_get_contents($stream, strlen($content) / 2);

		fclose($stream);
	}

	/**
	 * @param string $content
	 * @dataProvider streamContentProvider
	 */
	public function testCopySeekAbsolute($content) {
		$source = $this->getSourceStream($content);
		$target = fopen('php://temp', 'w+');
		$stream = CopyStreamWrapper::wrap($source, $target, function ($success) {
			$this->assertFalse($success);
		});

		fseek($stream, 10);

		stream_get_contents($stream);

		fclose($stream);
	}

	/**
	 * @param string $content
	 * @dataProvider streamContentProvider
	 */
	public function testCopySeekRelativeForeward($content) {
		$source = $this->getSourceStream($content);
		$target = fopen('php://temp', 'w+');
		$stream = CopyStreamWrapper::wrap($source, $target, function ($success) {
			$this->assertFalse($success);
		});

		fseek($stream, 10, SEEK_CUR);

		stream_get_contents($stream);

		fclose($stream);
	}

	/**
	 * @param string $content
	 * @dataProvider streamContentProvider
	 */
	public function testCopySeekRelativeBackwards($content) {
		$source = $this->getSourceStream($content);
		$target = fopen('php://temp', 'w+');
		$stream = CopyStreamWrapper::wrap($source, $target, function ($success) {
			$this->assertTrue($success);
		});

		stream_get_contents($stream, strlen($content) / 2);

		fseek($stream, -3, SEEK_CUR);

		stream_get_contents($stream);

		fclose($stream);
	}

	/**
	 * @expectedException \BadMethodCallException
	 */
	public function testInvalidStreams() {
		CopyStreamWrapper::wrap(null, null, function () {

		});
	}
}
