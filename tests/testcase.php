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

abstract class TestCase extends \Test\TestCase {
	/**
	 * @var \OC_User_Dummy
	 */
	protected $userProvider;

	public function setUp() {
		parent::setUp();
		$this->userProvider = new \OC_User_Dummy();
		\OC::$server->getUserManager()->registerBackend($this->userProvider);
	}

	public function tearDown() {
		\OC::$server->getUserManager()->removeBackend($this->userProvider);
	}

	/**
	 * @param resource $handle
	 * @return string[]
	 */
	protected function getHandleContent($handle) {
		$result = [];
		while (($file = readdir($handle)) !== false) {
			$result[] = $file;
		}
		sort($result);
		return $result;
	}

	/**
	 * @param string[] $expected
	 * @param resource $handle
	 */
	protected function assertHandleContent(array $expected, $handle) {
		sort($expected);
		$this->assertEquals($expected, $this->getHandleContent($handle));
	}

	/**
	 * @param string[] $expected
	 * @param resource $handle
	 */
	protected function assertNotHandleContent(array $expected, $handle) {
		sort($expected);
		$this->assertNotEquals($expected, $this->getHandleContent($handle));
	}
}
