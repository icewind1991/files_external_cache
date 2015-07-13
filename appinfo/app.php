<?php

$cacheManager = new \OCA\Files_External_Cache\Cache\CacheManager(
	\OC::$server->getCommandBus(),
	\OC::$server->getConfig()->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data')
);

OCP\Util::connectHook('OC_Filesystem', 'preSetup', $cacheManager, 'setupStorageWrapper');
