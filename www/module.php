<?php
/**
 * Handler for module requests.
 *
 * This web page receives requests for web-pages hosted by modules, and directs them to
 * the RequestHandler in the module.
 *
 * @author Olav Morken, UNINETT AS.
 * @package simpleSAMLphp
 * @version $Id$
 */

require_once('_include.php');

SimpleSAML_Error_Assertion::installHandler();

/* Index pages - filenames to attempt when accessing directories. */
$indexFiles = array('index.php', 'index.html', 'index.htm', 'index.txt');

/* MIME types - key is file extension, value is MIME type. */
$mimeTypes = array(
	'bml' => 'image/x-ms-bmp',
	'css' => 'text/css',
	'gif' => 'image/gif',
	'htm' => 'text/html',
	'html' => 'text/html',
	'shtml' => 'text/html',
	'jpe' => 'image/jpeg',
	'jpeg' => 'image/jpeg',
	'jpg' => 'image/jpeg',
	'js' => 'text/javascript',
	'pdf' => 'application/pdf',
	'png' => 'image/png',
	'svg' => 'image/svg+xml',
	'svgz' => 'image/svg+xml',
	'swf' => 'application/x-shockwave-flash',
	'swfl' => 'application/x-shockwave-flash',
	'txt' => 'text/plain',
	'xht' => 'application/xhtml+xml',
	'xhtml' => 'application/xhtml+xml',
	);

try {

	if (empty($_SERVER['PATH_INFO'])) {
		throw new SimpleSAML_Error_NotFound('No PATH_INFO to module.php');
	}

	$url = $_SERVER['PATH_INFO'];
	assert('substr($url, 0, 1) === "/"');

	$modEnd = strpos($url, '/', 1);
	if ($modEnd === FALSE) {
		/* The path must always be on the form /module/. */
		throw new SimpleSAML_Error_NotFound('The URL must at least contain a module name followed by a slash.');
	}

	$module = substr($url, 1, $modEnd - 1);
	$url = substr($url, $modEnd + 1);
	if ($url === FALSE) {
		$url = '';
	}

	if (!SimpleSAML_Module::isModuleEnabled($module)) {
		throw new SimpleSAML_Error_NotFound('The module \'' . $module .
			'\' was either not found, or wasn\'t enabled.');
	}

	/* Make sure that the request isn't suspicious (contains references to current
	 * directory or parent directory or anything like that. Searching for './' in the
	 * URL will detect both '../' and './'. Searching for '\' will detect attempts to
	 * use Windows-style paths.
	 */
	if (strpos($url, '\\')) {
		throw new SimpleSAML_Error_BadRequest('Requested URL contained a backslash.');
	} elseif (strpos($url, './')) {
		throw new SimpleSAML_Error_BadRequest('Requested URL contained \'./\'.');
	}

	$path = SimpleSAML_Module::getModuleDir($module) . '/www/' . $url;

	if ($path[strlen($path)-1] === '/') {
		/* Path ends with a slash - directory reference. Attempt to find index file
		 * in directory.
		 */
		foreach ($indexFiles as $if) {
			if (file_exists($path . $if)) {
				$path .= $if;
				break;
			}
		}
	}

	if (is_dir($path)) {
		/* Path is a directory - maybe no index file was found in the previous step, or
		 * maybe the path didn't end with a slash. Either way, we don't do directory
		 * listings.
		 */
		throw new SimpleSAML_Error_NotFound('Directory listing not available.');
	}

	if (!file_exists($path)) {
		/* File not found. */
		SimpleSAML_Logger::info('Could not find file \'' . $path . '\'.');
		throw new SimpleSAML_Error_NotFound('The URL wasn\'t found in the module.');
	}

	if (preg_match('#\.php$#', $path)) {
		/* PHP file - attempt to run it. */
		require($path);
		exit();
	}

	/* Some other file type - attempt to serve it. */

	/* Find MIME type for file, based on extension. */
	if (preg_match('#\.([^/]+)$#', $path, $type)) {
		$type = strtolower($type[1]);
		if (array_key_exists($type, $mimeTypes)) {
			$contentType = $mimeTypes[$type];
		} else {
			$contentType = mime_content_type($path);
		}
	} else {
		$contentType = mime_content_type($path);
	}

	$contentLength = sprintf('%u', filesize($path)); /* Force filesize to an unsigned number. */

	header('Content-Type: ' . $contentType);
	header('Content-Length: ' . $contentLength);
	readfile($path);
	exit();

} catch(SimpleSAML_Error_Error $e) {

	$e->show();

} catch(Exception $e) {

	$e = new SimpleSAML_Error_Error('UNHANDLEDEXCEPTION', $e);
	$e->show();

}

?>