<?php
/**
 * Atomik Framework
 *
 * @package Atomik
 * @subpackage Cache
 * @author Maxime Bouroumeau-Fuseau
 * @copyright 2008 (c) Maxime Bouroumeau-Fuseau
 * @license http://www.opensource.org/licenses/mit-license.php
 * @link http://www.atomikframework.com
 */
 
/* default configuration */
Atomik::setDefault(array(
    'cache' => array(
    
    	/* enable/disable the cache */
    	'enable' 				=> false,
    	
    	/* directory where to stored cached file */
    	'dir'				=> Atomik::get('atomik/paths/root') . 'cache/',
    	
    	/* requests to cache */
    	'requests' 		=> array(),
    	
    	/* default time for how long the cached file are used */
    	'default_time' 	=> 3600
    
    )	
));

/**
 * Cache plugin
 *
 * Cache the whole output or a request.
 *
 * To enable the cache, sets the cache config key to true.
 *
 * Specify which requests to cache using the cache_requests
 * config key. cache_requests value must be an array where its keys
 * are the request and its values the time in second the cached version will
 * be used.
 * Example:
 *
 * array(
 *    'index' => 60,
 *    'view'  => 3600
 * )
 *
 * The /index request will be catched for 60 seconds and the /view request
 * for one hour.
 *
 * Other possible time value:
 *   0 = use default time
 *  -1 = infinite
 *
 * The cache is regenerated without taking in consideration time when the
 * action or the template associated to the request are modified.
 *
 * @package Atomik
 * @subpackage Cache
 */
class CachePlugin
{
    /**
     * Check if this request is in cache and if not starts output buffering
     */
    function onAtomikDispatchBefore()
    {
    	/* checks if the cache is enabled */
    	if (Atomik::get('cache/enable', false) === false) {
    		return;
    	}
    	
    	/* filename of the cached file associated to this uri */
    	$cacheFilename = Atomik::get('cache/dir') . md5($_SERVER['REQUEST_URI']) . '.php';
    	Atomik::set('cache/filename', $cacheFilename);
    	
    	/* rebuilds the cache_requests array */
    	$defaultTime = Atomik::get('cache/default_time', 3600);
    	$requests = array();
    	foreach (Atomik::get('cache_requests') as $request => $time) {
    		if ($time == 0) {
    			$requests[$request] = $defaultTime;
    		} else if ($time > 0) {
    			$requests[$request] = $time;
    		}
    	}
    	Atomik::set('cache/requests', $requests);
    	
    	if (file_exists($cacheFilename)) {
    		$request = Atomik::get('request');
    		
    		/* last modified time */
    		$cacheTime = filemtime($cacheFilename);
    		$actionTime = filemtime(Atomik::get('atomik/paths/actions') . $request . '.php');
    		$templateTime = filemtime(Atomik::get('atomik/paths/templates') . $request . '.php');
    		
    		/* checks if the action or the template have been modified */
    		if ($cacheTime < $actionTime || $cacheTime < $templateTime) {
    			/* invalidates the cache */
    			@unlink($cacheFilename);
    			ob_start();
    			return;
    		}
    		
    		/* checks if there is a cache limit */
    		$diff = time() - $cacheTime;
    		if ($diff > $requests[$request]) {
    			/* invalidates the cache */
    			@unlink($cacheFilename);
    			ob_start();
    			return;
    		}
    		
    		/* cache still valid, output the cache content */
    		readfile($cacheFilename);
    		
    		exit;
    	}
    	
    	/* starts output buffering */
    	ob_start();
    }
    
    /**
     * Stops output buffering and stores output in cache
     *
     * @param bool $succes Core end success
     */
    function onAtomikEnd($success)
    {
    	/* checks if we cache this request */
    	if (!$success || Atomik::get('cache/enable', false) === false) {
    		return;
    	}
    	
    	/* gets the output and print it */
    	$output = ob_get_clean();
    	echo $output;
    	
    	$cacheFilename = Atomik::get('cache/filename');
    	$request = Atomik::get('request');
    	
    	/* checks if the current url is cacheable */
    	$requests = Atomik::get('cache/requests');
    	if (isset($requests[$request])) {
    		/* saves output to file */
    		@file_put_contents($cacheFilename, $output);
    	}
    }
    
    /**
     * Creates the cache directory when the init command is used.
     * Needs the console plugin
     *
     * @param array $args
     */
    function onConsoleInit($args)
    {
    	$directory = Atomik::get('cache/dir');
    	
    	/* creates cache directory */
    	ConsolePlugin::mkdir($directory, 1);
    	
    	/* sets permissions to 777 */
    	ConsolePlugin::println('Setting permissions for cache directory', 1);
    	if (!@chmod($directory, 0777)) {
    		ConsolePlugin::fail();
    	}
    	ConsolePlugin::success();
    }
}
    