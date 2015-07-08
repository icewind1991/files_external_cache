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

use OC\Files\Storage\Temporary;
use OCA\Files_External_Cache\OnionWrapper;
use Test\Files\Storage\Storage;

/**
 * Basic storage tests to ensure it behaves as a wrapper
 */
class StorageTest extends Storage {
	protected function setUp() {
		parent::setUp();

		$baseStorage = new Temporary();
		$this->instance = new OnionWrapper(['storages' => [$baseStorage]]);
	}
}
