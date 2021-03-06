<?php
defined('is_running') or die('Not an entry point...');

/**
 * See gpconfig.php for these configuration options
 *
 */
gp_defined('gpdebug',false);
if( gpdebug ){
	error_reporting(E_ALL);
}
set_error_handler('showError');

gp_defined('gp_restrict_uploads',false);
gp_defined('gpdebugjs',gpdebug);
gp_defined('gp_cookie_cmd',true);
gp_defined('gp_browser_auth',false);
gp_defined('gp_require_encrypt',false);
gp_defined('gp_chmod_file',0666);
gp_defined('gp_chmod_dir',0755);
gp_defined('gp_index_filenames',true);
gp_defined('gp_safe_mode',false);
gp_defined('E_DEPRECATED',8192);			// since php 5.3
gp_defined('E_USER_DEPRECATED',16384);		// since php 5.3
gp_defined('gp_backup_limit',30);
gp_defined('gp_write_lock_time',5);
gp_defined('gp_dir_index',true);
gp_defined('gp_remote_addons',true); //deprecated 4.0.1
gp_defined('gp_remote_plugins',gp_remote_addons);
gp_defined('gp_remote_themes',gp_remote_addons);
gp_defined('gp_remote_update',gp_remote_addons);
gp_defined('gp_unique_addons',false);
gp_defined('gp_data_type','.php');
gp_defined('gp_default_theme','Three_point_5/Shore'); 	//Bootswatch_Flatly/4_Sticky_Footer


//gp_defined('addon_browse_path','http://gpeasy.loc/index.php');
gp_defined('addon_browse_path','http://www.gpeasy.com/index.php');
gp_defined('debug_path','http://www.gpeasy.com/index.php/Debug');

gp_defined('gpversion','4.6rc1');
gp_defined('gp_random',common::RandomString());


@ini_set( 'session.use_only_cookies', '1' );
@ini_set( 'default_charset', 'utf-8' );
@ini_set( 'html_errors', false );

if( function_exists('mb_internal_encoding') ){
	mb_internal_encoding('UTF-8');
}



//see mediawiki/languages/Names.php
$languages = array(
	'af' => 'Afrikaans',
	'ar' => 'العربية',			# Arabic
	'bg' => 'Български',		# Bulgarian
	'ca' => 'Català',
	'cs' => 'Česky',			# Czech
	'da' => 'Dansk',
	'de' => 'Deutsch',
	'el' => 'Ελληνικά',			# Greek
	'en' => 'English',
	'es' => 'Español',
	'et' => 'eesti',			# Estonian
	'fi' => 'Suomi',			# Finnish
	'fo' => 'Føroyskt',			# Faroese
	'fr' => 'Français',
	'gl' => 'Galego',			# Galician
	'hr' => 'hrvatski',			# Croatian
	'hu' => 'Magyar',			# Hungarian
	'it' => 'Italiano',
	'ja' => '日本語',			# Japanese
	'lt' => 'Lietuvių',			# Lithuanian
	'nl' => 'Nederlands',		# Dutch
	'no' => 'Norsk',			# Norwegian
	'pl' => 'Polski',			# Polish
	'pt' => 'Português',
	'pt-br' => 'Português do Brasil',
	'ru' => 'Русский',			# Russian
	'sk' => 'Slovenčina',		# Slovak
	'sl' => 'Slovenščina',		# Slovenian
	'sv' => 'Svenska',			# Swedish
	'tr' => 'Türkçe',			# Turkish
	'uk' => 'Українська',		# Ukrainian
	'zh' => '中文',				# (Zhōng Wén) - Chinese
	);



$gpversion = gpversion; // @deprecated 3.5b2
$addonDataFolder = $addonCodeFolder = false;//deprecated
$addonPathData = $addonPathCode = false;
$wbErrorBuffer = $gp_not_writable = $wbMessageBuffer = array();



/* from wordpress
 * wp-settings.php
 * see also classes.php
 */
// Fix for IIS, which doesn't set REQUEST_URI
if ( empty( $_SERVER['REQUEST_URI'] ) ) {

	// IIS Mod-Rewrite
	if (isset($_SERVER['HTTP_X_ORIGINAL_URL'])) {
		$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URL'];
	}

	// IIS Isapi_Rewrite
	else if (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
		$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_REWRITE_URL'];

	}else{

		// Use ORIG_PATH_INFO if there is no PATH_INFO
		if ( !isset($_SERVER['PATH_INFO']) && isset($_SERVER['ORIG_PATH_INFO']) ){
			$_SERVER['PATH_INFO'] = $_SERVER['ORIG_PATH_INFO'];
		}


		// Some IIS + PHP configurations puts the script-name in the path-info (No need to append it twice)
		if ( isset($_SERVER['PATH_INFO']) ) {
			if( $_SERVER['PATH_INFO'] == $_SERVER['SCRIPT_NAME'] ){
				$_SERVER['REQUEST_URI'] = $_SERVER['PATH_INFO'];
			}else{
				$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'];
			}
		}

		// Append the query string if it exists and isn't null
		if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
			$_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
		}
	}
}

// Set default timezone in PHP 5.
if ( function_exists( 'date_default_timezone_set' ) )
	date_default_timezone_set( 'UTC' );





/**
 * Error Handling
 * Display the error and a debug_backtrace if gpdebug is not false
 * If gpdebug is an email address, send the error message to the address
 * @return false Always returns false so the standard PHP error handler is also used
 *
 */
function showError($errno, $errmsg, $filename, $linenum, $vars){
	global $wbErrorBuffer, $addon_current_id, $page, $addon_current_version, $config, $addonFolderName;
	static $reported = array();
	$report_error = true;


	$errortype = array (
				E_ERROR				=> 'Fatal Error',
				E_WARNING			=> 'Warning',
				E_PARSE				=> 'Parsing Error',
				E_NOTICE 			=> 'Notice',
				E_CORE_ERROR		=> 'Core Error',
				E_CORE_WARNING 		=> 'Core Warning',
				E_COMPILE_ERROR		=> 'Compile Error',
				E_COMPILE_WARNING 	=> 'Compile Warning',
				E_USER_ERROR		=> 'User Error',
				E_USER_WARNING 		=> 'User Warning',
				E_USER_NOTICE		=> 'User Notice',
				E_STRICT			=> 'Strict Notice',
				E_RECOVERABLE_ERROR => 'Recoverable Error',
				E_DEPRECATED		=> 'Deprecated',
				E_USER_DEPRECATED	=> 'User Deprecated',
			 );


	// for functions prepended with @ symbol to suppress errors
	$error_reporting = error_reporting();
	if( $error_reporting === 0 ){
		$report_error = false;

		//make sure the error is logged
		//error_log('PHP '.$errortype[$errno].':  '.$errmsg.' in '.$filename.' on line '.$linenum);

		if( gpdebug === false ){
			return false;
		}
		return false;
	}

	// since we supported php 4.3+, there may be a lot of strict errors
	if( $errno === E_STRICT ){
		return;
	}

	//get the backtrace and function where the error was thrown
	$backtrace = debug_backtrace();

	//remove showError() from backtrace
	if( strtolower($backtrace[0]['function']) == 'showerror' ){
		$backtrace = array_slice($backtrace,1,5);
	}else{
		$backtrace = array_slice($backtrace,0,5);
	}


	//record one error per function and only record the error once per request
	if( isset($backtrace[0]['function']) ){
		$uniq = $filename.$backtrace[0]['function'];
	}else{
		$uniq = $filename.$linenum;
	}
	if( isset($reported[$uniq]) ){
		return false;
	}
	$reported[$uniq] = true;

	//disable showError after 20 errors
	if( count($reported) >= 1 ){
		restore_error_handler();
	}

	if( gpdebug === false ){

		if( !$report_error ){
			return false;
		}


		//if it's an addon error, only report if the addon was installed remotely
		if( isset($addonFolderName) && $addonFolderName ){
			if( !isset($config['addons'][$addonFolderName]['remote_install'])  ){
				return false;
			}

		//if it's a core error, it should be in the include folder
		}elseif( strpos($filename,'/include/') === false ){
			return false;
		}

		//record the error
		$i = count($wbErrorBuffer);
		$args['en'.$i] = $errno;
		$args['el'.$i] = $linenum;
		$args['em'.$i] = substr($errmsg,0,255);
		$args['ef'.$i] = $filename; //filename length checked later
		if( isset($addon_current_id) ){
			$args['ea'.$i] = $addon_current_id;
		}
		if( isset($addon_current_version) && $addon_current_version ){
			$args['ev'.$i] = $addon_current_version;
		}
		if( is_object($page) && !empty($page->title) ){
			$args['ep'.$i] = $page->title;
		}
		$wbErrorBuffer[$uniq] = $args;
		return false;
	}


	$mess = '';
	$mess .= '<fieldset style="padding:1em">';
	$mess .= '<legend>'.$errortype[$errno].' ('.$errno.')</legend> '.$errmsg;
	$mess .= '<br/> &nbsp; &nbsp; <b>in:</b> '.$filename;
	$mess .= '<br/> &nbsp; &nbsp; <b>on line:</b> '.$linenum;
	if( isset($_SERVER['REQUEST_URI']) ){
		$mess .= '<br/> &nbsp; &nbsp; <b>Request:</b> '.$_SERVER['REQUEST_URI'];
	}
	if( isset($_SERVER['REQUEST_METHOD']) ){
		$mess .= '<br/> &nbsp; &nbsp; <b>Method:</b> '.$_SERVER['REQUEST_METHOD'];
	}


	//mysql.. for some addons
	if( function_exists('mysql_errno') && mysql_errno() ){
		$mess .= '<br/> &nbsp; &nbsp; Mysql Error ('.mysql_errno().')'. mysql_error();
	}

	//attempting to entire all data can result in a blank screen
	foreach($backtrace as $i => $trace){
		foreach($trace as $tk => $tv){
			if( is_array($tv) ){
				$backtrace[$i][$tk] = 'array('.count($tv).')';
			}elseif( is_object($tv) ){
				$backtrace[$i][$tk] = 'object '.get_class($tv);
			}
		}
	}

	$mess .= '<div><a href="javascript:void(0)" onclick="var st = this.nextSibling.style; if( st.display==\'block\'){ st.display=\'none\' }else{st.display=\'block\'};return false;">Show Backtrace</a>';
	$mess .= '<div class="nodisplay">';
	$mess .= pre($backtrace);
	$mess .= '</div></div>';
	$mess .= '</p></fieldset>';

	if( gpdebug === true ){
		message($mess);
	}elseif( $report_error ){
		global $gp_mailer;
		includeFile('tool/email_mailer.php');
		if( is_object($gp_mailer) ){
			$gp_mailer->SendEmail(gpdebug, 'debug ', $mess);
		}
	}
	return false;
}


/**
 * Define a constant if it hasn't already been set
 * @param string $var The name of the constant
 * @param mixed $default The value to set the constant if it hasn't been set
 * @since 2.4RC2
 */
function gp_defined($var,$default){
	defined($var) or define($var,$default);
}


/**
 * Fix GPCR if magic_quotes_gpc is on
 * magic_quotes_gpc is deprecated, but still on by default in many versions of php
 *
 */
if( function_exists( 'get_magic_quotes_gpc' ) && version_compare(phpversion(),'5.4','<=') && @get_magic_quotes_gpc() ){
	fix_magic_quotes( $_GET );
	fix_magic_quotes( $_POST );
	fix_magic_quotes( $_COOKIE );
	fix_magic_quotes( $_REQUEST );
}

//If Register Globals
if( common::IniGet('register_globals') ){
	foreach($_REQUEST as $key => $value){
		$key = strtolower($key);
		if( ($key == 'globals') || $key == '_post'){
			die('Hack attempted.');
		}
	}
}

function fix_magic_quotes( &$arr ) {
	$new = array();
	foreach( $arr as $key => $val ) {
		$key = stripslashes($key);

		if( is_array( $val ) ){
			fix_magic_quotes( $val );
		}else{
			$val = stripslashes( $val );
		}
		$new[$key] = $val;
	}
	$arr = $new;
}


/**
 * Store a user message in the buffer
 *
 */
function message(){
	$args = func_get_args(); //for php previous to 5.3
	call_user_func_array('msg',$args);
}

/**
 * @since 4.0
 *
 */
function msg(){
	global $wbMessageBuffer;

	$args = func_get_args();

	if( empty($args[0]) ){
		return;
	}

	if( isset($args[1]) ){
		$wbMessageBuffer[] = '<li>'.call_user_func_array('sprintf',$args).'</li>';
	}elseif( is_array($args[0]) || is_object($args[0]) ){
		$wbMessageBuffer[] = '<li>'.pre($args[0]).'</li>';
	}else{
		$wbMessageBuffer[] = '<li>'.$args[0].'</li>';
	}
}

/**
 * Output the message buffer
 *
 */
function GetMessages( $wrap = true ){
	global $wbMessageBuffer,$gp_not_writable,$langmessage;

	if( common::loggedIn() && count($gp_not_writable) > 0 ){
		$files = '<ul><li>'.implode('</li><li>',$gp_not_writable).'</li></ul>';
		$message = sprintf($langmessage['not_writable'],common::GetUrl('Admin_Status')).$files;
		message($message);
		$gp_not_writable = array();
	}

	$result = $wrap_end = '';

	if( $wrap ){
		$result = "\n<!-- message_start ".gp_random." -->";
		$wrap_end = "<!-- message_end -->\n";
	}
	if( !empty($wbMessageBuffer) ){

		if( gpdebug === false ){
			$wbMessageBuffer = array_unique($wbMessageBuffer);
		}

		$result .= '<div class="messages"><div>';
		$result .= '<a style="" href="#" class="req_script close_message" data-cmd="close_message"></a>';
		$result .= '<ul>';
		$result .= implode('',$wbMessageBuffer);
		$result .= '</ul></div></div>';
	}

	return $result .= common::ErrorBuffer().$wrap_end;
}


/**
 * Include a file relative to the include directory of the current installation
 *
 */
function includeFile( $file ){
	global $dataDir;
	require_once( $dataDir.'/include/'.$file );
}

/**
 * Include a script, unless it has caused a fatal error.
 * Using this function allows gpEasy to handle fatal errors that are thrown by the included php scripts
 *
 * @param string $file The full path of the php file to include
 * @param string $include_variation Which variation or adaptation of php's include() function to use (include,include_once,include_if, include_once_if, require ...)
 * @param array List of global variables to set
 */
function IncludeScript($file, $include_variation = 'include_once', $globals = array() ){

	$exists = file_exists($file);

	//check to see if it exists
	$include_variation = str_replace('_if','',$include_variation,$has_if);
	if( $has_if && !$exists ){
		return;
	}

	//check for fatal errors
	if( gpOutput::FatalNotice( 'include', $file ) ){
		return false;
	}


	//set global variables
	foreach($globals as $global){
		global $$global;
	}


	switch($include_variation){
		case 'include':
			$return = include($file);
		break;
		case 'include_once':
			$return = include_once($file);
		break;
		case 'require':
			$return = require($file);
		break;
		case 'require_once':
			$return = require_once($file);
		break;
	}

	gpOutput::PopCatchable();

	return $return;
}



/**
 * Similar to print_r and var_dump, but it is output buffer handling function safe
 * message( pre(array(array(true))) );
 * message( pre(new tempo()) );
 */
function pre($mixed){
	static $level = 0;
	$output = '';

	$type = gettype($mixed);
	switch($type){
		case 'object':
			$type = get_class($mixed).' object';
			$output = $type.'(...)'."\n"; //recursive object references creates an infinite loop
		break;
		case 'array':
			$output = $type.'('."\n";
			foreach($mixed as $key => $value){
				$level++;
				$output .= str_repeat('   ',$level) . '[' . $key . '] => ' . pre($value) . "\n";
				$level--;
			}
			$output .= str_repeat('   ',$level).')';
		break;
		case 'boolean':
			if( $mixed ){
				$mixed = 'true';
			}else{
				$mixed = 'false';
			}
		default:
			$output = '('.$type.')'.htmlspecialchars($mixed,ENT_COMPAT,'UTF-8',false).'';
		break;
	}

	if( $level == 0 ){
		return '<pre>'.htmlspecialchars($output,ENT_COMPAT,'UTF-8',false).'</pre>';
	}
	return $output;
}
/**
 * @deprecated 2.6
 */
function showArray($mixed){ trigger_error('Deprecated function showArray(). Use pre() instead'); }


/**
 * Modified from Wordpress function win_is_writable()
 * Working for users without requiring trailing slashes as noted in Wordpress
 *
 * Workaround for Windows bug in is_writable() function
 * will work in despite of Windows ACLs bug
 * NOTE: use a trailing slash for folders!!!
 * see http://bugs.php.net/bug.php?id=27609
 * see http://bugs.php.net/bug.php?id=30931
 *
 * @param string $path
 * @return bool
 */
function gp_is_writable( $path ){

	if( is_writable($path) ){
		return true;
	}

	// check tmp file for read/write capabilities
	if( is_dir($path) ){
		$path = rtrim($path,'/').'/' . uniqid( mt_rand() ) . '.tmp';
	}

	$should_delete_tmp_file = !file_exists( $path );
	$f = @fopen( $path, 'a' );
	if ( $f === false ) return false;
	fclose( $f );
	if ( $should_delete_tmp_file ) unlink( $path );
	return true;
}



class common{


	/**
	 * Return the type of response was requested by the client
	 * @since 3.5b2
	 * @return string
	 */
	static function RequestType(){
		if( isset($_REQUEST['gpreq']) ){
			switch($_REQUEST['gpreq']){
				case 'body':
				case 'flush':
				case 'json':
				case 'content':
				case 'admin';
				return $_REQUEST['gpreq'];
			}
		}
		return 'template';
	}


	/**
	 * Send a 304 Not Modified Response to the client if HTTP_IF_NONE_MATCH matched $etag and headers have not already been sent
	 * Othewise, send the etag
	 * @param string $etag The calculated etag for the current page
	 *
	 */
	static function Send304($etag){
		global $config;

		if( !$config['etag_headers'] ) return;

		if( headers_sent() ) return;

		//always send the etag
		header('ETag: "'.$etag.'"');

		if( empty($_SERVER['HTTP_IF_NONE_MATCH'])
			|| trim($_SERVER['HTTP_IF_NONE_MATCH'],'"') != $etag ){
				return;
		}

		//don't use ob_get_level() in while loop to prevent endless loops;
		$level = ob_get_level();
		while( $level > 0 ){
			@ob_end_clean();
			$level--;
		}

		// 304 should not have a response body or Content-Length header
		//header('Not Modified',true,304);
		common::status_header(304,'Not Modified');
		header('Connection: close');
		exit();
	}


	/**
	 * Set HTTP status header.
	 * Modified From Wordpress
	 *
	 * @since 2.3.3
	 * @uses apply_filters() Calls 'status_header' on status header string, HTTP
	 *		HTTP code, HTTP code description, and protocol string as separate
	 *		parameters.
	 *
	 * @param int $header HTTP status code
	 * @param string $text HTTP status
	 * @return unknown
	 */
	static function status_header( $header, $text ) {

		$protocol = '';
		if( isset($_SERVER['SERVER_PROTOCOL']) ){
			$protocol = $_SERVER['SERVER_PROTOCOL'];
		}
		if( 'HTTP/1.1' != $protocol && 'HTTP/1.0' != $protocol ){
			$protocol = 'HTTP/1.0';
		}

		$status_header = "$protocol $header $text";
		return @header( $status_header, true, $header );
	}

	static function GenEtag(){
		global $dirPrefix, $dataDir;
		$etag = '';
		$args = func_get_args();
		$args[] = $dataDir.$dirPrefix;
		foreach($args as $arg){
			if( !ctype_digit($arg) ){
				$arg = crc32( $arg );
				$arg = sprintf("%u\n", $arg );
			}
			$etag .= base_convert( $arg, 10, 36);
		}
		return $etag;
	}


	/**
	 * Generate an etag from the filemtime and filesize of each file
	 * @param array $files
	 *
	 */
	static function FilesEtag( $files ){
		$modified = 0;
		$content_length = 0;
		foreach($files as $file ){
			$content_length += @filesize( $file );
			$modified = max($modified, @filemtime($file) );
		}

		return common::GenEtag( $modified, $content_length );
	}


	static function CheckTheme(){
		global $page;
		if( $page->theme_name === false ){
			$page->SetTheme();
		}
	}

	/**
	 * Return an array of information about the layout
	 * @param string $layout The layout key
	 * @param bool $check_existence Whether or not to check for the existence of the template.php file
	 *
	 */
	static function LayoutInfo( $layout, $check_existence = true ){
		global $gpLayouts,$dataDir;

		if( !isset($gpLayouts[$layout]) ){
			return false;
		}

		$layout_info = $gpLayouts[$layout];
		$layout_info += array('is_addon'=>false);
		$layout_info['theme_name'] = common::DirName($layout_info['theme']);
		$layout_info['theme_color'] = basename($layout_info['theme']);

		$relative = '/themes/';
		if( $layout_info['is_addon'] ){
			$relative = '/data/_themes/';
		}
		$layout_info['path'] = $relative.$layout_info['theme'];

		$layout_info['dir'] = $dataDir.$relative.$layout_info['theme_name'];
		if( $check_existence && !file_exists($layout_info['dir'].'/template.php') ){
			return false;
		}

		return $layout_info;
	}



	/*
	 *
	 *
	 * Entry Functions
	 *
	 *
	 */

	static function EntryPoint($level=0,$expecting='index.php',$sessions=true){

		common::CheckRequest();

		clearstatcache();

		$ob_gzhandler = false;
		if( !common::IniGet('zlib.output_compression') && 'ob_gzhandler' != ini_get('output_handler') ){
			@ob_start( 'ob_gzhandler' ); //ini_get() does not always work for this test
			$ob_gzhandler = true;
		}


		common::SetGlobalPaths($level,$expecting);
		includeFile('tool/display.php');
		includeFile('tool/Files.php');
		includeFile('tool/gpOutput.php');
		includeFile('tool/functions.php');
		includeFile('tool/Plugins.php');
		if( $sessions ){
			ob_start(array('gpOutput','BufferOut'));
		}elseif( !$ob_gzhandler ){
			ob_start();
		}

		common::RequestLevel();
		common::GetConfig();
		common::SetLinkPrefix();
		common::SetCookieArgs();
		if( $sessions ){
			common::sessions();
		}

		spl_autoload_register( array('common','Autoload') );
	}


	/**
	 * Setup SPL Autoloading
	 *
	 */
	static function Autoload($class){
		global $config, $dataDir;

		$parts		= explode('\\',$class);
		$part_0		= array_shift($parts);

		if( !$parts ){
			return;
		}

		//gp namespace
		if( $part_0 === 'gp' ){
			$path	= implode('/',$parts).'.php';
			require_once( $dataDir.'/include/'.$path );
			return;
		}

		//look for addon namespace
		if( $part_0 === 'Addon' ){

			$namespace = array_shift($parts);
			if( !$parts ){
				return;
			}

			foreach($config['addons'] as $addon_key => $addon){
				if( isset($addon['Namespace']) && $addon['Namespace'] == $namespace ){

					gpPlugin::SetDataFolder($addon_key);
					$file			= gpPlugin::$current['code_folder_full'].'/'.implode('/',$parts).'.php';

					if( file_exists($file) ){
						include($file);
					}else{
						trigger_error('Script not found in namespaced autoloader for '.$class);
					}

					gpPlugin::ClearDataFolder();
				}
			}
		}
	}


	/**
	 * Reject Invalid Requests
	 *
	 */
	static function CheckRequest(){

		if( count($_POST) == 0 ){
			return;
		}


		if( !isset($_SERVER['CONTENT_LENGTH']) ){
			header('HTTP/1.1 503 Service Temporarily Unavailable');
			header('Status: 503 Service Temporarily Unavailable');
			header('Retry-After: 300');//300 seconds
			die();
		}


		if( function_exists('getallheaders') ){

			$headers = getallheaders();
			if( !isset($headers['Content-Length']) ){
				header('HTTP/1.1 503 Service Temporarily Unavailable');
				header('Status: 503 Service Temporarily Unavailable');
				header('Retry-After: 300');//300 seconds
				die();
			}

		}
	}

	/**
	 * @deprectated
	 */
	static function gpInstalled(){}

	static function SetGlobalPaths($DirectoriesAway,$expecting){
		global $dataDir, $dirPrefix, $rootDir;

		$rootDir = common::DirName( __FILE__, 2 );

		// dataDir, make sure it contains $expecting. Some servers using cgi do not set this properly
		// required for the Multi-Site plugin
		$dataDir = common::GetEnv('SCRIPT_FILENAME',$expecting);
		if( $dataDir !== false ){
			$dataDir = common::ReduceGlobalPath($dataDir,$DirectoriesAway);
		}else{
			$dataDir = $rootDir;
		}
		if( $dataDir == '/' ){
			$dataDir = '';
		}

		//$dirPrefix
		$dirPrefix = common::GetEnv('SCRIPT_NAME',$expecting);
		if( $dirPrefix === false ){
			$dirPrefix = common::GetEnv('PHP_SELF',$expecting);
		}

		//remove everything after $expecting, $dirPrefix can at times include the PATH_INFO
		$pos = strpos($dirPrefix,$expecting);
		$dirPrefix = substr($dirPrefix,0,$pos+strlen($expecting));

		$dirPrefix = common::ReduceGlobalPath($dirPrefix,$DirectoriesAway);
		if( $dirPrefix == '/' ){
			$dirPrefix = '';
		}
	}

	/**
	 * Convert backslashes to forward slashes
	 *
	 */
	static function WinPath($path){
		return str_replace('\\','/',$path);
	}

	/**
	 * Returns parent directory's path with forward slashes
	 * php's dirname() method may change slashes from / to \
	 *
	 */
	static function DirName( $path, $dirs = 1 ){
		for($i=0;$i<$dirs;$i++){
			$path = dirname($path);
		}
		return common::WinPath( $path );
	}

	/**
	 * Determine if this installation is supressing index.php in urls or not
	 *
	 */
	static function SetLinkPrefix(){
		global $linkPrefix, $dirPrefix, $config;

		$linkPrefix = $dirPrefix;

		// gp_rewrite = 'On' and gp_rewrite = 'gpuniq' are deprecated as of gpEasy 4.1
		// gp_rewrite = bool will still be used internally
		if( isset($_SERVER['gp_rewrite']) ){
			if( $_SERVER['gp_rewrite'] === true || $_SERVER['gp_rewrite'] == 'On' ){
				$_SERVER['gp_rewrite'] = true;
			}elseif( $_SERVER['gp_rewrite'] == @substr($config['gpuniq'],0,7) ){
				$_SERVER['gp_rewrite'] = true;
			}

		}elseif( isset($_REQUEST['gp_rewrite']) ){
			$_SERVER['gp_rewrite'] = true;

		// gp_indexphp is deprecated as of gpEasy 4.1
		}elseif( defined('gp_indexphp') ){

			if( gp_indexphp === false ){
				$_SERVER['gp_rewrite'] = true;
			}

		}

		unset($_GET['gp_rewrite']);
		unset($_REQUEST['gp_rewrite']);

		if( !isset($_SERVER['gp_rewrite']) ){
			$_SERVER['gp_rewrite'] = false;
		}

		if( !$_SERVER['gp_rewrite'] ){
			$linkPrefix .= '/index.php';
		}
	}

	/**
	 * Get the environment variable and make sure it contains an expected value
	 *
	 * @param string $var The key of the requested environment variable
	 * @param string $expected Optional string that is expected as part of the environment variable value
	 *
	 * @return mixed Returns false if $expected is not found, otherwise it returns the environment value.
	 *
	 */
	static function GetEnv($var,$expecting=false){
		$value = false;
		if( isset($_SERVER[$var]) ){
			$value = $_SERVER[$var];
		}else{
			$value = getenv($var);
		}
		if( $expecting && strpos($value,$expecting) === false ){
			return false;
		}
		return $value;
	}

	/**
	 * Get the ini value and return a boolean casted value when appropriate: On, Off, 1, 0, True, False, Yes, No
	 *
	 */
	static function IniGet($key){
		$value = ini_get($key);
		if( empty($value) ){
			return false;
		}

		$lower_value = trim(strtolower($value));
		switch($lower_value){
			case 'true':
			case 'yes':
			case 'on':
			case '1':
			return true;

			case 'false':
			case 'no':
			case 'off':
			case '0':
			return false;
		}

		return $value;
	}


	static function ReduceGlobalPath($path,$DirectoriesAway){
		return common::DirName($path,$DirectoriesAway+1);
	}



	//use dirPrefix to find requested level
	static function RequestLevel(){
		global $dirPrefixRel,$dirPrefix;

		$path = $_SERVER['REQUEST_URI'];

		//strip the query string.. in case it contains "/"
		$pos = mb_strpos($path,'?');
		if( $pos > 0 ){
			$path =  mb_substr($path,0,$pos);
		}

		//dirPrefix will be percent-decoded
		$path = rawurldecode($path); //%20 ...

		if( !empty($dirPrefix) ){
			$pos = mb_strpos($path,$dirPrefix);
			if( $pos !== false ){
				$path = mb_substr($path,$pos+mb_strlen($dirPrefix));
			}
		}

		$path = ltrim($path,'/');
		$count = substr_count($path,'/');
		if( $count == 0 ){
			$dirPrefixRel = '.';
		}else{
			$dirPrefixRel = str_repeat('../',$count);
			$dirPrefixRel = rtrim($dirPrefixRel,'/');//GetDir() arguments always start with /
		}
	}



	/**
	 * Escape ampersands in hyperlink attributes and other html tag attributes
	 *
	 * @param string $str The string value of an html attribute
	 * @return string The escaped string
	 */
	static function Ampersands($str){
		return preg_replace('/&(?![#a-zA-Z0-9]{2,9};)/S','&amp;',$str);
	}


	/**
	 * Similar to htmlspecialchars, but designed for labels
	 * Does not convert existing ampersands "&"
	 *
	 */
	static function LabelSpecialChars($string){
		return str_replace( array('<','>','"',"'"), array('&lt;','&gt;','&quot;','&#39;') , $string);

		/*return str_replace(
				array('<','>','"',"'",'&','&amp;lt;','&amp;gt;','&amp;quot;','&amp;#39;','&amp;amp;')
				, array('&lt;','&gt;','&quot;','&#39;','&amp;','&lt;','&gt;','&quot;','&#39;','&amp;')
				, $str);
		*/

	}


	/**
	 * Return an html hyperlink
	 *
	 * @param string $href The href value relative to the installation root (without index.php)
	 * @param string $label Text or html to be displayed within the hyperlink
	 * @param string $query Optional query to be used with the href
	 * @param string|array $attr Optional string of attributes like title=".." and class=".."
	 * @param mixed $nonce_action If false, no nonce will be added to the query. Given a string, it will be used as the first argument in common::new_nonce()
	 *
	 * @return string The formatted html hyperlink
	 */
	static function Link($href='',$label='',$query='',$attr='',$nonce_action=false){
		return '<a href="'.common::GetUrl($href,$query,true,$nonce_action).'" '.common::LinkAttr($attr,$label).'>'.common::Ampersands($label).'</a>';
	}

	static function LinkAttr($attr='',$label=''){
		$string = '';
		$has_title = false;
		if( is_array($attr) ){
			$attr = array_change_key_case($attr);
			$has_title = isset($attr['title']);
			if( isset($attr['name']) && !isset($attr['data-cmd']) ){
				$attr['data-cmd'] = $attr['name'];
				unset($attr['name']);
			}

			if( isset($attr['data-cmd']) ){
				switch( $attr['data-cmd'] ){
					case 'creq':
					case 'cnreq':
					case 'postlink':
						$attr['data-nonce'] = common::new_nonce('post',true);
					break;
				}
			}
			foreach($attr as $attr_name => $attr_value){
				$string .= ' '.$attr_name.'="'.htmlspecialchars($attr_value,ENT_COMPAT,'UTF-8',false).'"';
			}
		}else{
			$string = $attr;
			if( strpos($attr,'title="') !== false){
				$has_title = true;
			}

			// backwards compatibility hack to be removed in future releases
			// @since 3.6
			if( strpos($string,'name="postlink"') !== false ){
				$string .= ' data-nonce="'.common::new_nonce('post',true).'"';

			// @since 4.1
			}elseif( strpos($string,'name="cnreq"') !== false || strpos($string,'name="creq"') !== false ){
				$string .= ' data-nonce="'.common::new_nonce('post',true).'"';
			}

		}

		if( !$has_title && !empty($label) ){
			$string .= ' title="'.common::Ampersands(strip_tags($label)).'" ';
		}

		return trim($string);
	}

	/**
	 * Return an html hyperlink for a page
	 *
	 * @param string $title The title of the page
	 * @return string The formatted html hyperlink
	 */
	static function Link_Page($title=''){
		global $config, $gp_index;

		if( empty($title) && !empty($config['homepath']) ){
			$title = $config['homepath'];
		}

		$label = common::GetLabel($title);

		return common::Link($title,$label);
	}


	static function GetUrl($href='',$query='',$ampersands=true,$nonce_action=false){
		global $linkPrefix, $config;

		$filtered = gpPlugin::Filter('GetUrl',array(array($href,$query)));
		if( is_array($filtered) ){
			list($href,$query) = $filtered;
		}

		$href = common::SpecialHref($href);


		//home page link
		if( isset($config['homepath']) && $href == $config['homepath'] ){
			$href = $linkPrefix;
			if( !$_SERVER['gp_rewrite'] ){
				$href = common::DirName($href);
			}
			$href = rtrim($href,'/').'/';
		}else{
			$href = $linkPrefix.'/'.ltrim($href,'/');
		}

		$query = common::QueryEncode($query,$ampersands);

		if( $nonce_action ){
			$nonce = common::new_nonce($nonce_action);
			if( !empty($query) ){
				$query .= '&amp;'; //in the cases where $ampersands is false, nonces are not used
			}
			$query .= '_gpnonce='.$nonce;
		}
		if( !empty($query) ){
			$query = '?'.ltrim($query,'?');
		}

		return common::HrefEncode($href,$ampersands).$query;
	}

	//translate special pages from key to title
	static function SpecialHref($href){
		global $gp_index;

		$href2 = '';
		$pos = mb_strpos($href,'/');
		if( $pos !== false ){
			$href2 = mb_substr($href,$pos);
			$href = mb_substr($href,0,$pos);
		}

		$lower = mb_strtolower($href);
		if( !isset($gp_index[$href])
				&& strpos($lower,'special_') === 0
				&& $index_title = common::IndexToTitle($lower)
				){
					$href = $index_title;
		}

		return $href.$href2;
	}

	/**
	 * RawUrlEncode but keeps the following characters: &, /, \
	 * Slash is needed for hierarchical links
	 * In case you'd like to learn about percent encoding: http://www.blooberry.com/indexdot/html/topics/urlencoding.htm
	 *
	 */
	static function HrefEncode($href,$ampersands=true){
		$ampersand = '&';
		if( $ampersands ){
			$ampersand = '&amp;';
		}
		$href = rawurlencode($href);
		return str_replace( array('%26amp%3B','%26','%2F','%5C'),array($ampersand,$ampersand,'/','\\'),$href);
	}

	/**
	 * RawUrlEncode parts of the query string ( characters except & and = )
	 *
	 */
	static function QueryEncode($query,$ampersands = true){

		if( empty($query) ){
			return '';
		}

		$query = str_replace('+','%20',$query);//in case urlencode() was used instead of rawurlencode()
		if( strpos($query,'&amp;') !== false ){
			$parts = explode('&amp;',$query);
		}else{
			$parts = explode('&',$query);
		}

		$ampersand = $query = '';
		foreach($parts as $part){
			if( strpos($part,'=') ){
				list($key,$value) = explode('=',$part,2);
				$query .= $ampersand.rawurlencode(rawurldecode($key)).'='.rawurlencode(rawurldecode($value));
			}else{
				$query .= $ampersand.rawurlencode(rawurldecode($part));
			}
			if( $ampersands ){
				$ampersand = '&amp;';
			}else{
				$ampersand = '&';
			}
		}
		return $query;
	}

	static function AbsoluteLink($href,$label,$query='',$attr=''){

		if( strpos($attr,'title="') === false){
			$attr .= ' title="'.htmlspecialchars(strip_tags($label)).'" ';
		}

		return '<a href="'.common::AbsoluteUrl($href,$query).'" '.$attr.'>'.common::Ampersands($label).'</a>';
	}

	static function AbsoluteUrl($href='',$query='',$with_schema=true,$ampersands=true){

		if( isset($_SERVER['HTTP_HOST']) ){
			$server = $_SERVER['HTTP_HOST'];
		}elseif( isset($_SERVER['SERVER_NAME']) ){
			$server = $_SERVER['SERVER_NAME'];
		}else{
			return common::GetUrl($href,$query,$ampersands);
		}

		$schema = '';
		if( $with_schema ){
			$schema = ( isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
		}

		return $schema.$server.common::GetUrl($href,$query,$ampersands);
	}

	/**
	 * Get the full path of a physical file on the server
	 * The query string component of a path should not be included but will be protected from being encoded
	 *
	 */
	static function GetDir($dir='',$ampersands = false){
		global $dirPrefix;

		$query = '';
		$pos = mb_strpos($dir,'?');
		if( $pos !== false ){
			$query = mb_substr($dir,$pos);
			$dir = mb_substr($dir,0,$pos);
		}
		$dir = $dirPrefix.'/'.ltrim($dir,'/');
		return common::HrefEncode($dir,$ampersands).$query;
	}


	/**
	 * Get the label for a page from it's index
	 * @param string $index
	 * @param bool $amp Whether or not to escape ampersand characters
	 */
	static function GetLabelIndex($index=false,$amp=false){
		global $gp_titles,$langmessage;

		$info = array();
		if( isset($gp_titles[$index]) ){
			$info = $gp_titles[$index];
		}

		if( isset($info['label']) ){
			$return = $info['label'];

		}elseif( isset($info['lang_index']) ){
			$return = $langmessage[$info['lang_index']];

		}else{
			$return = common::IndexToTitle($index);
			$return = gpFiles::CleanLabel($return);
		}
		if( $amp ){
			return str_replace('&','&amp;',$return);
		}
		return $return;
	}

	/**
	 * Get the label for a page from it's title
	 * @param string $title
	 * @param bool $amp Whether or not to escape ampersand characters
	 */
	static function GetLabel($title=false){
		global $gp_titles, $gp_index, $langmessage;

		$return = false;
		if( isset($gp_index[$title]) ){
			$id = $gp_index[$title];
			$info =& $gp_titles[$id];

			if( isset($info['label']) ){
				$return = $info['label'];

			}elseif( isset($info['lang_index']) ){

				$return = $langmessage[$info['lang_index']];
			}
		}

		if( $return === false ){
			$return = gpFiles::CleanLabel($title);
		}

		return $return;
	}

	/**
	 * Get the browser title for a page
	 * @param string $title
	 *
	 */
	static function GetBrowserTitle($title){
		global $gp_titles, $gp_index;

		if( !isset($gp_index[$title]) ){
			return false;
		}

		$index = $gp_index[$title];
		$title_info = $gp_titles[$index];

		if( isset($title_info['browser_title']) ){
			return $title_info['browser_title'];
		}

		$label = common::GetLabel($title);

		return strip_tags($label);
	}


	/**
	 * Add js and css components to the current web page
	 *
	 * @static
	 * @since 2.0b1
	 * @param string $names A comma separated list of ui components to include. Avail since gpEasy 3.5.
	 */
	static function LoadComponents( $names = ''){
		gpOutput::$components .= ','.$names.',';
		gpOutput::$components = str_replace(',,',',',gpOutput::$components);
	}


	/**
	 * Add gallery js and css to the <head> section of a page
	 *
	 */
	static function ShowingGallery(){
		global $page;
		static $showing = false;
		if( $showing ) return;
		$showing = true;

		common::AddColorBox();
		$css = gpPlugin::OneFilter('Gallery_Style');
		if( $css === false  ){
			$page->css_user[] = '/include/css/default_gallery.css';
			return;
		}
		$page->head .= "\n".'<link type="text/css" media="screen" rel="stylesheet" href="'.$css.'" />';
	}

	/**
	 * Add js and css elements to the <head> section of a page
	 *
	 */
	static function AddColorBox(){
		global $page, $config, $langmessage;
		static $init = false;

		if( $init ){
			return;
		}
		$init = true;

		$list = array('previous'=>$langmessage['Previous'],'next'=>$langmessage['Next'],'close'=>$langmessage['Close'],'caption'=>$langmessage['caption'],'current'=>sprintf($langmessage['Image_of'],'{current}','{total}')); //'Start Slideshow'=>'slideshowStart','Stop Slideshow'=>'slideshowStop'
		$page->head_script .= "\nvar colorbox_lang = ".common::JsonEncode($list).';';

		common::LoadComponents( 'colorbox' );
	}

	/**
	 * Set the $config array from /data/_site/config.php
	 *
	 */
	static function GetConfig(){
		global $config, $gp_hooks;


		$config = gpFiles::Get('_site/config');

		if( !is_array($config) || !array_key_exists('gpversion',$config) ){
			common::stop();
		}


		//make sure defaults are set
		$config += array(
				'maximgarea'		=> '691200',
				'maxthumbsize'		=> '100',
				'check_uploads'		=> false,
				'colorbox_style'	=> 'example1',
				'combinecss'		=> true,
				'combinejs'			=> true,
				'etag_headers'		=> true,
				'customlang'		=> array(),
				'showgplink'		=> true,
				'showsitemap'		=> true,
				'showlogin'			=> true,
				'auto_redir'		=> 90,			//2.5
				'history_limit'		=> min(gp_backup_limit,30),
				'resize_images'		=> true,		//3.5
				'jquery'			=> 'local',
				'addons'			=> array(),
				'themes'			=> array(),
				'gadgets'			=> array(),
				'passhash'			=> 'sha1',
				'hooks'				=> array(),
				'space_char'		=> '_',			//4.6
				);

		//shahash deprecated 4.0
		if( isset($config['shahash']) && !$config['shahash'] ){
			$config['passhash'] = 'md5';
		}


		// default gadgets
		$config['gadgets'] += array(
								'Contact' 		=> array('script'=>'/include/special/special_contact.php','class'=>'special_contact_gadget'),
								'Search'		=> array('script'=>'/include/special/special_search.php','method'=>array('special_gpsearch','gadget')), //3.5
								);

		foreach($config['hooks'] as $hook => $hook_info){
			if( isset($gp_hooks[$hook]) ){
				$gp_hooks[$hook] += $hook_info;
			}else{
				$gp_hooks[$hook] = $hook_info;
			}
		}

		common::GetLangFile();
		common::GetPagesPHP();


		//upgrade?
		if( version_compare($config['gpversion'],'2.3.4','<') ){
			includeFile('tool/upgrade.php');
			new gpupgrade();
		}
	}


	/**
	 * Stop loading gpEasy
	 * Check to see if gpEasy has already been installed
	 *
	 */
	static function stop(){
		global $dataDir;

		if( !gpFiles::Exists($dataDir.'/data/_site/config.php') ){

			if( file_exists($dataDir.'/include/install/install.php') ){
				common::SetLinkPrefix();
				includeFile('install/install.php');
				die();
			}
		}

		die('<p>Notice: The site configuration did not load properly.</p>'
			.'<p>If you are the site administrator, you can troubleshoot the problem turning debugging "on" or bypass it by enabling gpEasy safe mode.</p>'
			.'<p>More information is available in the <a href="http://docs.gpeasy.com/Main/Troubleshooting">gpEasy documentation</a>.</p>'
			.common::ErrorBuffer(true,false)
			);
	}


	/**
	 * Set global variables ( $gp_index, $gp_titles, $gp_menu and $gpLayouts ) from _site/pages.php
	 *
	 */
	static function GetPagesPHP(){
		global $gp_index, $gp_titles, $gp_menu, $gpLayouts, $config;
		$gp_index = array();


		$pages		= gpFiles::Get('_site/pages');


		//update for < 2.0a3
		if( array_key_exists('gpmenu',$pages)
			&& array_key_exists('gptitles',$pages)
			&& !array_key_exists('gp_titles',$pages)
			&& !array_key_exists('gp_menu',$pages) ){

			foreach($pages['gptitles'] as $title => $info){
				$index = common::NewFileIndex();
				$gp_index[$title] = $index;
				$gp_titles[$index] = $info;
			}

			foreach($pages['gpmenu'] as $title => $level){
				$index = $gp_index[$title];
				$gp_menu[$index] = array('level' => $level);
			}
			return;
		}

		$gpLayouts		= $pages['gpLayouts'];
		$gp_index		= $pages['gp_index'];
		$gp_titles		= $pages['gp_titles'];
		$gp_menu		= $pages['gp_menu'];

		if( !is_array($gp_menu) ){
			common::stop();
		}

		//update for 3.5,
		if( !isset($gp_titles['special_gpsearch']) ){
			$gp_titles['special_gpsearch'] = array();
			$gp_titles['special_gpsearch']['label'] = 'Search';
			$gp_titles['special_gpsearch']['type'] = 'special';
			$gp_index['Search'] = 'special_gpsearch'; //may overwrite special_search settings
		}

		//fix the gpmenu
		if( version_compare(gpFiles::$last_version,'3.0b1','<') ){
			$gp_menu = gpOutput::FixMenu($gp_menu);

			// fix gp_titles for gpEasy 3.0+
			// just make sure any ampersands in the label are escaped
			foreach($gp_titles as $key => $value){
				if( isset($gp_titles[$key]['label']) ){
					$gp_titles[$key]['label'] = common::GetLabelIndex($key,true);
				}
			}
		}

		//title related configuration settings
		if( empty($config['homepath_key']) ){
			$config['homepath_key'] = key($gp_menu);
		}
		$config['homepath'] = common::IndexToTitle($config['homepath_key']);

	}


	/**
	 * Generate a new file index
	 * skip indexes that are just numeric
	 */
	static function NewFileIndex(){
		global $gp_index, $gp_titles, $dataDir, $config;

		$num_index = 0;

		/*prevent reusing old indexes */
		if( count($gp_index) > 0 ){
			$max = count($gp_index);
			$title = end($gp_index);
			for($i = $max; $i > 0; $i--){
				$last_index = current($gp_index);
				$type = common::SpecialOrAdmin($title);
				if( $type === 'special' ){
					$title = prev($gp_index);
					continue;
				}
				$i = 0;
			}
			reset($gp_index);
			$num_index = base_convert($last_index,36,10);
			$num_index++;
		}

		do{
			$index = base_convert($num_index,10,36);
			$num_index++;


			//check backup dir
			$backup_dir = $dataDir.'/data/_backup/pages/'.$index;
			if( file_exists($backup_dir) ){
				$index = false;
				continue;
			}

			//check for page directory
			$draft_file	= $dataDir.'/data/_pages/'.substr($config['gpuniq'],0,7).'_'.$index;
			if( file_exists($draft_file) ){
				$index = false;
				continue;
			}

		}while( !$index || is_numeric($index) || isset($gp_titles[$index]) );

		return $index;
	}


	/**
	 * Return the title of file using the index
	 * Will return false for titles that are external links
	 * @param string $index The index of the file
	 */
	static function IndexToTitle($index){
		global $gp_index;
		return array_search($index,$gp_index);
	}



	/**
	 * Traverse the $menu upwards looking for the parents of the a title given by it's index
	 * @param string $index The data index of the child title
	 * @return array
	 *
	 */
	static function Parents($index,$menu){
		$parents = array();

		if( !isset($menu[$index]) || !isset($menu[$index]['level']) ){
			return $parents;
		}

		$checkLevel = $menu[$index]['level'];
		$menu_ids = array_keys($menu);
		$key = array_search($index,$menu_ids);
		for($i = ($key-1); $i >= 0; $i--){
			$id = $menu_ids[$i];

			//check the level
			$level = $menu[$id]['level'];
			if( $level >= $checkLevel ){
				continue;
			}
			$checkLevel = $level;

			$parents[] = $id;

			//no need to go further
			if( $level == 0 ){
				return $parents;
			}
		}
		return $parents;
	}

	/**
	 * Traverse the $menu and gather all the descendants of a title given by it's $index
	 * @param string $index The data index of the child title
	 * @param array $menu The menu to use to check for descendants
	 * @param bool $children_only Option to return a list of children instead of all descendants. Since gpEasy 4.3
	 * @return array
	 */
	static function Descendants( $index, $menu, $children_only = false){

		$titles = array();

		if( !isset($menu[$index]) || !isset($menu[$index]['level']) ){
			return $titles;
		}

		$start_level = $menu[$index]['level'];
		$menu_ids = array_keys($menu);
		$key = array_search($index,$menu_ids);
		for($i = $key+1; $i < count($menu); $i++){
			$id = $menu_ids[$i];
			$level = $menu[$id]['level'];

			if( $level <= $start_level ){
				return $titles;
			}

			if( !$children_only ){
				$titles[] = $id;
			}elseif( $level == $start_level +1 ){
				$titles[] = $id;
			}
		}
		return $titles;

	}


	/**
	 * Return the configuration value or default if it's not set
	 *
	 * @since 1.7
	 *
	 * @param string $key The key to the $config array
	 * @param mixed $default The value to return if $config[$key] is not set
	 * @return mixed
	 */
	static function ConfigValue($key,$default=false){
		global $config;
		if( !isset($config[$key]) ){
			return $default;
		}
		return $config[$key];
	}

	/**
	 * Generate a random alphanumeric string of variable length
	 *
	 * @param int $len length of string to return
	 * @param bool $cases Whether or not to use upper and lowercase characters
	 */
	static function RandomString($len = 40, $cases = true ){

		$string = 'abcdefghijklmnopqrstuvwxyz1234567890';
		if( $cases ){
			$string .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		}

		$string = str_repeat($string,round($len/2));
		$string = str_shuffle( $string );
		$start = mt_rand(1, (strlen($string)-$len));

		return substr($string,$start,$len);
	}

	/**
	 * Include the main.inc language file for $language
	 * Language files were renamed to main.inc for version 2.0.2
	 *
	 */
	static function GetLangFile($file='main.inc',$language=false){
		global $dataDir, $config, $langmessage;


		$language	= $language ? $language : $config['language'];
		$path		= $dataDir.'/include/languages/'.$language.'.main.inc';

		if( !file_exists($path) ){
			$path	= $dataDir.'/include/languages/en.main.inc'; //default to en
		}

		include($path);
	}


	/**
	 * Determine if the $title is a special or admin page
	 * @param string $title
	 * @return mixed 'admin','special' or false
	 */
	static function SpecialOrAdmin($title){
		global $gp_index,$gp_titles;

		$lower_title = strtolower($title);

		if( $lower_title === 'admin' ){
			return 'admin';
		}elseif( strpos($lower_title,'admin_') === 0 ){
			return 'admin';
		}

		if( strpos($lower_title,'special_') === 0 ){
			return 'special';
		}


		$parts = explode('/',$title);
		do{
			$title = implode('/',$parts);
			if( isset($gp_index[$title]) ){
				$key = $gp_index[$title];
				$info = $gp_titles[$key];
				if( $info['type'] == 'special' ){
					return 'special';
				}
			}
			array_pop($parts);
		}while( count($parts) );

		return false;
	}


	/**
	 * Return the name of the page being requested based on $_SERVER['REQUEST_URI']
	 * May also redirect the request
	 *
	 * @return string The title to display based on the request uri
	 *
	 */
	static function WhichPage(){
		global $config, $gp_menu;

		$path = common::CleanRequest($_SERVER['REQUEST_URI']);
		$path = preg_replace('#[[:cntrl:]]#u','', $path);// remove control characters

		$pos = mb_strpos($path,'?');
		if( $pos !== false ){
			$path = mb_substr($path,0,$pos);
		}

		$path = gpPlugin::Filter('WhichPage',array($path));

		//redirect if an "external link" is the first entry of the main menu
		if( empty($path) && isset($gp_menu[$config['homepath_key']]) ){
			$homepath_info = $gp_menu[$config['homepath_key']];
			if( isset($homepath_info['url']) ){
				common::Redirect($homepath_info['url'],302);
			}
		}

		if( empty($path) ){
			return $config['homepath'];
		}

		if( isset($config['homepath']) && $path == $config['homepath'] ){
			$args = $_GET;
			common::Redirect(common::GetUrl('',http_build_query($_GET),false));
		}

		return $path;
	}


	/**
	 * Redirect the request to $path with http $code
	 * @static
	 * @param string $path url to redirect to
	 * @param string $code http redirect code: 301 or 302
	 *
	 */
	static function Redirect($path,$code = 302){
		global $wbMessageBuffer, $gpAdmin;

		//store any messages for display after the redirect
		if( common::LoggedIn() && count($wbMessageBuffer) ){
			$gpAdmin['message_buffer'] = $wbMessageBuffer;
		}


		//prevent a cache from creating an infinite redirect
		Header( 'Last-Modified: ' . gmdate( 'D, j M Y H:i:s' ) . ' GMT' );
		Header( 'Expires: ' . gmdate( 'D, j M Y H:i:s', time() ) . ' GMT' );
		Header( 'Cache-Control: no-store, no-cache, must-revalidate' ); // HTTP/1.1
		Header( 'Cache-Control: post-check=0, pre-check=0', false );
		Header( 'Pragma: no-cache' ); // HTTP/1.0

		switch((int)$code){
			case 301:
				common::status_header(301,'Moved Permanently');
			break;
			case 302:
				common::status_header(302,'Found');
			break;
		}

		header('Location: '.$path);
		die();
	}


	/**
	 * Remove $dirPrefix and index.php from a path to get the page title
	 *
	 * @param string $path A full relative url like /install_dir/index.php/request_title
	 * @param string The request_title portion of $path
	 *
	 */
	static function CleanRequest($path){
		global $dirPrefix;

		//use dirPrefix to find requested title
		$path = rawurldecode($path); //%20 ...

		if( !empty($dirPrefix) ){
			$pos = strpos($path,$dirPrefix);
			if( $pos !== false ){
				$path = substr($path,$pos+strlen($dirPrefix));
			}
		}


		//remove /index.php/
		$pos = strpos($path,'/index.php');
		if( $pos === 0 ){
			$path = substr($path,11);
		}

		$path = ltrim($path,'/');

		return $path;
	}


	/**
	 * Handle admin login/logout/session_start if admin session parameters exist
	 *
	 */
	static function sessions(){

		//alternate sessions
		if( defined('gpcom_sessions') ){
			include(gpcom_sessions);
		}

		$cmd = '';
		if( isset($_GET['cmd']) && $_GET['cmd'] == 'logout' ){
			$cmd = 'logout';
		}elseif( isset($_POST['cmd']) && $_POST['cmd'] == 'login' ){
			$cmd = $_POST['cmd'];
		}elseif( count($_COOKIE) ){
			foreach($_COOKIE as $key => $value){
				if( strpos($key,'gpEasy_') === 0 ){
					$cmd = 'start';
					break;
				}
			}
		}

		if( empty($cmd) ){
			return;
		}

		includeFile('tool/sessions.php');
		gpsession::Init();
	}


	/**
	 * Return true if an administrator is logged in
	 * @return bool
	 */
	static function LoggedIn(){
		global $gpAdmin;

		$loggedin = false;
		if( isset($gpAdmin) && is_array($gpAdmin) ){
			$loggedin = true;
		}

		return gpPlugin::Filter('LoggedIn',array($loggedin));
	}

	static function new_nonce($action = 'none', $anon = false, $factor = 43200 ){
		global $gpAdmin;

		$nonce = $action;
		if( !$anon && !empty($gpAdmin['username']) ){
			$nonce .= $gpAdmin['username'];
		}

		return common::nonce_hash($nonce, 0, $factor );
	}


	/**
	 * Verify a nonce ($check_nonce)
	 *
	 * @param string $action Should be the same $action that is passed to new_nonce()
	 * @param mixed $check_nonce The user submitted nonce or false if $_REQUEST['_gpnonce'] can be used
	 * @param bool $anon True if the nonce is being used for anonymous users
	 * @param int $factor Determines the length of time the generated nonce will be valid. The default 43200 will result in a 24hr period of time.
	 * @return mixed Return false if the $check_nonce did not pass. 1 or 2 if it passes.
	 *
	 */
	static function verify_nonce($action = 'none', $check_nonce = false, $anon = false, $factor = 43200 ){
		global $gpAdmin;

		if( $check_nonce === false ){
			$check_nonce =& $_REQUEST['_gpnonce'];
		}

		if( empty($check_nonce) ){
			return false;
		}

		$nonce = $action;
		if( !$anon ){
			if( empty($gpAdmin['username']) ){
				return false;
			}
			$nonce .= $gpAdmin['username'];
		}

		// Nonce generated 0-12 hours ago
		if( common::nonce_hash( $nonce, 0, $factor ) == $check_nonce ){
			return 1;
		}

		// Nonce generated 12-24 hours ago
		if( common::nonce_hash( $nonce, 1, $factor ) == $check_nonce ){
			return 2;
		}

		// Invalid nonce
		return false;
	}

	/**
	 * Generate a nonce hash
	 *
	 * @param string $nonce
	 * @param int $tick_offset
	 * @param int $factor Determines the length of time the generated nonce will be valid. The default 43200 will result in a 24hr period of time.
	 *
	 */
	static function nonce_hash( $nonce, $tick_offset=0, $factor = 43200 ){
		global $config;
		$nonce_tick = ceil( time() / $factor ) - $tick_offset;
		return substr( md5($nonce.$config['gpuniq'].$nonce_tick), -12, 10);
	}

	/**
	 * Return the command sent by the user
	 * Don't use $_REQUEST here because SetCookieArgs() uses $_GET
	 *
	 */
	static function GetCommand($type='cmd'){
		global $gpAdmin;

		if( is_array($gpAdmin) && isset($gpAdmin['locked']) && $gpAdmin['locked'] ){
			return false;
		}

		if( isset($_POST[$type]) ){
			return $_POST[$type];
		}

		if( isset($_GET[$type]) ){
			return $_GET[$type];
		}
		return false;
	}


	/**
	 * Used for receiving arguments from javascript without having to put variables in the $_GET request
	 * nice for things that shouldn't be repeated!
	 */
	static function SetCookieArgs(){
		static $done = false;

		if( $done || !gp_cookie_cmd ){
			return;
		}

		self::RawCookies();

		//get cookie arguments
		if( empty($_COOKIE['cookie_cmd']) ){
			return;
		}
		$test = $_COOKIE['cookie_cmd'];
		if( $test{0} === '?' ){
			$test = substr($test,1);
		}

		parse_str($test,$cookie_args);
		if( !$cookie_args ){
			return;
		}


		//parse_str will overwrite values in $_GET/$_REQUEST
		$_GET = $cookie_args + $_GET;
		$_REQUEST = $cookie_args + $_REQUEST;

		//for requests with verification, we'll set $_POST
		if( !empty($_GET['verified']) ){
			$_POST = $cookie_args + $_POST;
		}

		$done = true;
	}


	/**
	 * Fix the $_COOKIE array if RAW_HTTP_COOKIE is set
	 * Some servers encrypt cookie values before sending them to the client
	 * Since cookies set by the client (with JavaScript) are not encrypted, the values won't be set in $_COOOKIE
	 *
	 */
	static function RawCookies(){
		if( empty($_SERVER['RAW_HTTP_COOKIE']) ){
			return;
		}
		$csplit = explode(';', $_SERVER['RAW_HTTP_COOKIE']);
		foreach( $csplit as $pair ){
			if( !strpos($pair,'=') ){
				continue;
			}
			list($key,$value) = explode( '=', $pair );
			$key = rawurldecode(trim($key));
			if( !array_key_exists($key,$_COOKIE) ){
				$_COOKIE[$key] = rawurldecode(trim($value));
			}
		}
	}

	/**
	 * Output Javascript code to set variable defaults
	 *
	 */
	static function JsStart(){

		//default gpEasy Variables
		echo 'var gplinks={},gpinputs={},gpresponse={}'
				.',isadmin=false'
				.',gpBase="'.rtrim(common::GetDir(''),'/').'"'
				.',post_nonce=""'
				.',req_type="'.strtolower(htmlspecialchars($_SERVER['REQUEST_METHOD'])).'";'
				."\n";
	}


	/**
	 * Return the hash of $arg using the appropriate hashing function for the installation
	 *
	 * @param string $arg The string to be hashed
	 * @param string $algo The hashing algorithm to be used
	 * @param int $loops The number of times to loop the $arg through the algorithm
	 *
	 */
	static function hash( $arg, $algo='sha512', $loops = 1000){
		$arg = trim($arg);

		switch($algo){

			//md5
			case 'md5':
			trigger_error('md5 should not be used, please reset your password');
			return md5($arg);

			//sha1
			case 'sha1':
			return sha1($arg);
		}


		//sha512: looped with dynamic salt
		for( $i=0; $i<$loops; $i++ ){

			$ints = preg_replace('#[a-f]#','',$arg);
			$salt_start = (int)substr($ints,0,1);
			$salt_len = (int)substr($ints,2,1);
			$salt = substr($arg,$salt_start,$salt_len);
			$arg = hash($algo,$arg.$salt);
		}

		return $arg;
	}

	static function AjaxWarning(){
		global $page,$langmessage;
		$page->ajaxReplace[] = array(0=>'admin_box_data',1=>'',2=>$langmessage['OOPS_Start_over']);
	}


	static function IdUrl($request_cmd='cv'){
		global $config, $dataDir, $gpLayouts;

		//command
		$args['cmd'] = $request_cmd;

		$_SERVER += array('SERVER_SOFTWARE'=>'');


		//checkin
		$args['mdu']		= substr(md5($config['gpuniq']),0,20);
		$args['site']		= common::AbsoluteUrl(''); //keep full path for backwards compat
		$args['gpv']		= gpversion;
		$args['php']		= phpversion();
		$args['se']			= $_SERVER['SERVER_SOFTWARE'];
		$args['data']		= $dataDir;
		//$args['zlib'] = (int)function_exists('gzcompress');


		//service provider
		if( defined('service_provider_id') && is_numeric(service_provider_id) ){
			$args['provider'] = service_provider_id;
		}

		//testing
		if( defined('gp_unit_testing') ){
			$args['gp_unit_testing'] = 1;
		}

		//plugins
		$addon_ids = array();
		if( isset($config['addons']) && is_array($config['addons']) ){
			self::AddonIds($addon_ids, $config['addons']);
		}

		//themes
		if( isset($config['themes']) && is_array($config['themes']) ){
			self::AddonIds($addon_ids, $config['themes']);
		}

		//layouts
		if( is_array($gpLayouts) ){
			foreach($gpLayouts as $layout_info){
				if( !isset($layout_info['addon_id']) ){
					continue;
				}
				$addon_ids[] = $layout_info['addon_id'];
			}
		}

		$addon_ids		= array_unique($addon_ids);
		$args['as']		= implode('-',$addon_ids);

		return addon_browse_path.'/Resources?' . http_build_query($args,'','&');
	}


	static function AddonIds( &$addon_ids, $array ){

		foreach($array as $addon_info){
			if( !isset($addon_info['id']) ){
				continue;
			}
			$addon_id = $addon_info['id'];
			if( isset($addon_info['order']) ){
				$addon_id .= '.'.$addon_info['order'];
			}
			$addon_ids[] = $addon_id;
		}
	}


	/**
	 * Used to send error reports without affecting the display of a page
	 *
	 */
	static function IdReq($img_path,$jquery = true){

		//using jquery asynchronously doesn't affect page loading
		//error function defined to prevent the default error function in main.js from firing
		if( $jquery ){
			echo '<script type="text/javascript" style="display:none !important">';
			echo '$.ajax('.json_encode($img_path).',{error:function(){}, dataType: "jsonp"});';
			echo '</script>';
			return;
		}

		return '<img src="'.common::Ampersands($img_path).'" height="1" width="1" alt="" style="border:0 none !important;height:1px !important;width:1px !important;padding:0 !important;margin:0 !important;"/>';
	}


	/**
	 * Return a debug message with link to online debug info
	 *
	 */
	static function Debug($lang_key, $debug = array()){
		global $langmessage, $dataDir;


		//add backtrace info
		$backtrace = debug_backtrace();
		while( count($backtrace) > 0 && !empty($backtrace[0]['function']) && $backtrace[0]['function'] == 'Debug' ){
			array_shift($backtrace);
		}

		$debug['trace']			= array_intersect_key($backtrace[0], array('file'=>'','line'=>'','function'=>'','class'=>''));

		if( !empty($debug['trace']['file']) && strpos($debug['trace']['file'],$dataDir) === 0 ){
			$debug['trace']['file'] = substr($debug['trace']['file'], strlen($dataDir) );
		}


		//add php and gpeasy info
		$debug['lang_key']		= $lang_key;
		$debug['phpversion']	= phpversion();
		$debug['gpversion']		= gpversion;
		$debug['Rewrite']		= $_SERVER['gp_rewrite'];
		$debug['Server']		= isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '';


		//create string
		$debug	= json_encode($debug);
		$debug	= base64_encode($debug);
		$debug	= trim($debug,'=');
		$debug	= strtr($debug, '+/', '-_');

		$label	= isset($langmessage[$lang_key]) ? $langmessage[$lang_key] : $lang_key;

		return ' <span>'.$label.' <a href="'.debug_path.'?data='.$debug.'" target="_blank">More Info...</a></span>';
	}


	//only include error buffer when admin is logged in
	static function ErrorBuffer($check_user = true, $jquery = true){
		global $wbErrorBuffer, $config, $dataDir, $rootDir;

		if( count($wbErrorBuffer) == 0 ) return;

		if( isset($config['Report_Errors']) && !$config['Report_Errors'] ) return;

		if( $check_user && !common::LoggedIn() ) return;

		$dataDir_len = strlen($dataDir);
		$rootDir_len = strlen($rootDir);
		$img_path = common::IdUrl('er');
		$i = 0;

		foreach($wbErrorBuffer as $error){

			//remove $dataDir or $rootDir from the filename
			$file_name = common::WinPath($error['ef'.$i]);
			if( $dataDir_len > 1 && strpos($file_name,$dataDir) === 0 ){
				$file_name = substr($file_name,$dataDir_len);
			}elseif( $rootDir_len > 1 && strpos($file_name,$rootDir) === 0 ){
				$file_name = substr($file_name,$rootDir_len);
			}
			$error['ef'.$i] = substr($file_name,-100);

			$new_path = $img_path.'&'.http_build_query($error,'','&');

			//maximum length of 2000 characters
			if( strlen($new_path) > 2000 ){
				break;
			}
			$img_path = $new_path;
			$i++;
		}

		return common::IdReq($img_path, $jquery);
	}


	/**
	 * Test if function exists.  Also handles case where function is disabled via Suhosin.
	 * Modified from: http://dev.piwik.org/trac/browser/trunk/plugins/Installation/Controller.php
	 *
	 * @param string $function Function name
	 * @return bool True if function exists (not disabled); False otherwise.
	 */
	static function function_exists($function){
		$function = strtolower($function);

		// eval() is a language construct
		if( $function == 'eval' ){
			// does not check suhosin.executor.eval.whitelist (or blacklist)
			if( extension_loaded('suhosin') && common::IniGet('suhosin.executor.disable_eval') ){
				return false;
			}
			return true;
		}

		if( !function_exists($function) ){
			return false;
		}

		$blacklist = @ini_get('disable_functions');
		if( extension_loaded('suhosin') ){
			$blacklist .= ','.@ini_get('suhosin.executor.func.blacklist');
		}

		$blacklist = explode(',', $blacklist);
		$blacklist = array_map('trim',$blacklist);
		$blacklist = array_map('strtolower',$blacklist);
		if( in_array($function, $blacklist) ){
			return false;
		}

		return true;
	}

	/**
	 * A more functional JSON Encode function for gpEasy than php's json_encode
	 * @param mixed $data
	 *
	 */
	static function JsonEncode($data){
		static $search = array('\\','"',"\n","\r","\t",'<script','</script>');
		static $repl = array('\\\\','\"','\n','\r','\t','<"+"script','<"+"/script>');

		$type = gettype($data);
		switch( $type ){
			case 'NULL':
			return 'null';

			case 'boolean':
			return ($data ? 'true' : 'false');

			case 'integer':
			case 'double':
			case 'float':
			return $data;

			case 'string':
			return '"'.str_replace($search,$repl,$data).'"';

			case 'object':
				$data = get_object_vars($data);
			case 'array':
				$output_index_count = 0;
				$output_indexed = array();
				$output_associative = array();
				foreach( $data as $key => $value ){
					$output_indexed[] = common::JsonEncode($value);
					$output_associative[] = common::JsonEncode($key) . ':' . common::JsonEncode($value);
					if( $output_index_count !== NULL && $output_index_count++ !== $key ){
						$output_index_count = NULL;
					}
				}
				if ($output_index_count !== NULL) {
					return '[' . implode(',', $output_indexed) . ']';
				} else {
					return '{' . implode(',', $output_associative) . '}';
				}
			default:
			return ''; // Not supported
		}
	}

	/**
	 * Date format funciton, uses formatting similar to php's strftime function
	 * http://php.net/manual/en/function.strftime.php
	 *
	 */
	static function Date($format='',$time=false){
		if( empty($format) ){
			return '';
		}

		if( !$time ){
			$time = time();
		}

		$match_count = preg_match_all('#%+[^\s]#',$format,$matches,PREG_OFFSET_CAPTURE);
		if( $match_count ){
			$matches = array_reverse($matches[0]);
			foreach($matches as $match){
				$len = strlen($match[0]);
				if( $len%2 ){
					$replacement = strftime($match[0],$time);
				}else{
					$piece = substr($match[0],-2,2);
					switch($piece){
						case '%e':
							$replacement = strftime( substr($match[0],0,-2),$time).ltrim(strftime('%d',$time),'0');
						break;
						default:
							$replacement = strftime($match[0],$time);
						break;
					}
				}
				$format = substr_replace($format,$replacement,$match[1],strlen($match[0]));
			}
		}
		return $format;
	}



	/**
	 * Get an image's thumbnail path
	 *
	 */
	static function ThumbnailPath($img){

		//already thumbnail path
		if( strpos($img,'/data/_uploaded/image/thumbnails') !== false ){
			return $img;
		}

		$dir_part = '/data/_uploaded/';
		$pos = strpos($img,$dir_part);
		if( $pos === false ){
			return $img;
		}

		return substr_replace($img,'/data/_uploaded/image/thumbnails/',$pos, strlen($dir_part) ).'.jpg';
	}


	/**
	 * Generate a checksum for the $array
	 *
	 */
	static function ArrayHash($array){
		return md5(json_encode($array) );
	}


	/**
	 * Convert a string representation of a byte value to an number
	 * @param string $value
	 * @return int
	 */
	static function getByteValue($value){

		if( is_numeric($value) ){
			return (int)$value;
		}

		$lastChar = strtolower(substr($value,-1));
		$num = (int)substr($value,0,-1);

		switch($lastChar){

			case 'g':
				$num *= 1024;
			case 'm':
				$num *= 1024;
			case 'k':
				$num *= 1024;
			break;
		}

		return $num;
	}

	/**
	 * @deprecated 3.0
	 * use gp_edit::UseCK();
	 */
	static function UseFCK($contents,$name='gpcontent'){
		trigger_error('Deprecated Function');
		includeFile('tool/editing.php');
		gp_edit::UseCK($contents,$name);
	}

	/**
	 * @deprecated 3.0
	 * Use gp_edit::UseCK();
	 */
	static function UseCK($contents,$name='gpcontent',$options=array()){
		trigger_error('Deprecated Function');
		includeFile('tool/editing.php');
		gp_edit::UseCK($contents,$name,$options);
	}

}
