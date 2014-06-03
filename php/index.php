<?php

	error_reporting(0);
	set_time_limit(0);

	$has_json = true;

	if (!function_exists('json_encode')) {
		$has_json = false;
	    function json_encode($data) {
	        switch ($type = gettype($data)) {
	            case 'NULL':
	                return 'null';
	            case 'boolean':
	                return ($data ? 'true' : 'false');
	            case 'integer':
	            case 'double':
	            case 'float':
	                return $data;
	            case 'string':
	                return '"' . addslashes($data) . '"';
	            case 'object':
	                $data = get_object_vars($data);
	            case 'array':
	                $output_index_count = 0;
	                $output_indexed = array();
	                $output_associative = array();
	                foreach ($data as $key => $value) {
	                    $output_indexed[] = json_encode($value);
	                    $output_associative[] = json_encode($key) . ':' . json_encode($value);
	                    if ($output_index_count !== NULL && $output_index_count++ !== $key) {
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
	}

	function delete_files($path, $del_dir = FALSE, $level = 0)
	{
		// Trim the trailing slash
		$path = rtrim($path, DIRECTORY_SEPARATOR);

		if ( ! $current_dir = @opendir($path))
		{
			return FALSE;
		}

		while (FALSE !== ($filename = @readdir($current_dir)))
		{
			if ($filename != "." and $filename != "..")
			{
				if (is_dir($path.DIRECTORY_SEPARATOR.$filename))
				{
					// Ignore empty folders
					if (substr($filename, 0, 1) != '.')
					{
						delete_files($path.DIRECTORY_SEPARATOR.$filename, $del_dir, $level + 1);
					}
				}
				else
				{
					unlink($path.DIRECTORY_SEPARATOR.$filename);
				}
			}
		}
		@closedir($current_dir);

		if ($del_dir == TRUE AND $level > 0)
		{
			return @rmdir($path);
		}

		return TRUE;
	}

	function download($f, $to, $test = false)
	{
		$error = false;
		if (in_array('curl', get_loaded_extensions())) {
			$cp = curl_init($f);
			$fp = fopen($to, "w+");
			if (!$fp) {
				curl_close($cp);
				$error = 'perms';
			} else {
				curl_setopt($cp, CURLOPT_FILE, $fp);
				curl_setopt($cp, CURLOPT_CONNECTTIMEOUT, 10);
				curl_setopt($cp, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($cp, CURLOPT_SSL_VERIFYPEER, false);
				if (curl_exec($cp) === false)
				{
					fclose($fp);
					@unlink($to);
					$error = curl_error($cp);
				}
				else
				{
					fclose($fp);
				}
				curl_close($cp);

			}
		}
		else
		{
			$error = 'extensions';
		}

		return array( file_exists($to) && filesize($to) > 0, $error);
	}

	function extract_callback($p_event, &$p_header) {
		$current_dir = dirname(__FILE__);
		$current_perms = substr(sprintf('%o', fileperms($current_dir)), -4);
		if ($current_perms < 755)
		{
			$current_perms = '0755';
		}
		chmod($current_dir . DIRECTORY_SEPARATOR . $p_header['filename'], octdec($current_perms));
		return 1;
	}

	function test_database($fullhost, $user, $password, $name, $prefix)
	{
		$host = $fullhost;
		$tmp_table = $prefix . 'applications';
		$db_error = false;
		$create = $alter = false;
		$data = array();

		if (function_exists('mysqli_connect'))
		{
			$data['driver'] = 'mysqli';

			$host_bits = explode(':', $host);
			if (count($host_bits) > 1)
			{
				$host = $host_bits[0];
				if (is_numeric($host_bits[1]))
				{
					$host = $fullhost;
					$socket = null;
				}
				else
				{
					$socket = $host_bits[1];
				}
			}
			else
			{
				$socket = null;
			}
			$link = mysqli_connect($host, $user, $password, $name, null, $socket);

			if (!$link)
			{
				$data['error'] = mysqli_connect_error();
				$link = mysqli_connect('localhost', $user, $password, $name);
				if ($link)
				{
					$host = 'localhost';
					unset($data['error']);
				}
			}

			if ($link)
			{
				$info = mysqli_get_server_info($link);

				$create = mysqli_query($link, "CREATE TABLE $tmp_table (a INT)");

				if ($create)
				{
					$alter = mysqli_query($link, "ALTER TABLE $tmp_table ADD b INT(10)");

					if (!$alter)
					{
						$db_error = mysqli_error($link);
					}
				}
				else
				{
					$db_error = mysqli_error($link);
				}

				if ($create && !mysqli_query($link, "DROP TABLE $tmp_table"))
				{
					$db_error = mysqli_error($link);
				}
			}
		}
		else
		{
			$data['driver'] = 'mysql';

			$link = mysql_connect($host, $user, $password);

			if (!$link)
			{
				$data['error'] = mysql_error($link);
				$link = mysql_connect('localhost', $user, $password);
				if ($link)
				{
					$host = 'localhost';
					unset($data['error']);
				}
			}

			if ($link && !mysql_select_db($name, $link))
			{
				$data['error'] = mysql_error($link);
			}
			else
			{
				$info = mysql_get_server_info();

				$create = mysql_query("CREATE TABLE $tmp_table (a INT)");

				if ($create)
				{
					$alter = mysql_query("ALTER TABLE $tmp_table ADD b INT(10)");

					if (!$alter)
					{
						$db_error = mysql_error($link);
					}
				}
				else
				{
					$db_error = mysql_error($link);
				}

				if ($create && !mysql_query("DROP TABLE $tmp_table"))
				{
					$db_error = mysql_error($link);
				}
			}
		}

		if (isset($info))
		{
			// Strip a-log and other stuff from end of MySQL version info
			$info = preg_replace('/[a-z\-]/', '', $info);
			if (version_compare($info, '4.1.0', '<'))
			{
				$data['error'] = "Koken requires MySQL 4.1 or higher. You're server is running MySQL $info. Contact your host to see if a more up to date version is available.";
			}
			else if ($db_error)
			{
				if (strpos($db_error, 'already exists') !== -1)
				{
					$data['error'] = "This database already contains an installation of Koken. If you'd like to install another copy in this database, click the \"Advanced options\" link below and change the table prefix.";
				}
				else
				{
					$data['error'] = 'This MySQL user has insufficient permissions. Koken requires SELECT, UPDATE, CREATE, DELETE, ALTER and DROP permissions on this database. MySQL returned this error: ' . $db_error;
				}
			}
		}

		$data['host'] = $host;

		return $data;
	}

	if ($_POST)
	{
		if (isset($_POST['database_check']))
		{
			$host = $_POST['host'];
			$user = $_POST['user'];
			$password = $_POST['password'];
			$name = $_POST['name'];

			$data = test_database($host, $user, $password, $name, $_POST['prefix']);
		}
		else if (isset($_POST['server_test']))
		{
			$current_dir = dirname(__FILE__);
			$writable = is_writable($current_dir) && is_writable(__FILE__);

			list($download, $download_error) = download('https://s3.amazonaws.com/install.koken.me/releases/pclzip.lib.txt', 'pclzip.lib.php');

			$ua = strtolower($_SERVER['HTTP_USER_AGENT']);

			$imagick = in_array('imagick', get_loaded_extensions()) && class_exists('Imagick');

			$php = $connection = $permissions = $im = $browser = array( 'warn' => array(), 'pass_string' => '', 'fail' => array() );

			if (!$has_json)
			{
				$php['fail'][] = 'Koken requires that PHP be built with the JSON extension. Contact your host or system administrator for help with installing the JSON extension.';
			}

			if (version_compare(PHP_VERSION, '5.2.0', '<'))
			{
				$php['fail'][] = "Koken requires PHP 5.2.0. Your server is running " . PHP_VERSION . '.';
			}
			else
			{
				$php['pass_string'] = PHP_VERSION;
			}

			// Safe mode completely removed in PHP 5.4, so only check PHP installs earlier than that.
			if (version_compare(PHP_VERSION, '5.4.0', '<') && (ini_get('safe_mode') === true || ini_get('safe_mode') === 1 || ini_get('safe_mode') === '1' || strtolower(ini_get('safe_mode')) === 'on')) {
	  			$php['fail'][] = "Koken is incompatible with PHP's safe_mode. Ask your host or system administrator to disable safe_mode in PHP's configuration.";
			}

			if (!function_exists('gzopen'))
			{
				$php['fail'][] = 'Koken requires <code>zlib</code> library support in PHP to be enabled before installing. Check with your web host or system administrator for more information.';
			}

			if ( strpos($ua, 'msie') !== false || strpos($ua, 'internet explorer') !== false)
			{
	  			$browser['fail'][] = 'The Koken beta does not currently support Internet Explorer. Please use Chrome, Safari or Firefox.';
			}

			if (!in_array('mysql', get_loaded_extensions()) && !in_array('mysqli', get_loaded_extensions()))
			{
				$php['fail'][] = 'Koken requires either the mysql or mysqli PHP extension to be installed and enabled.';
			}

			if (isset($_SERVER['ACCESS_DOMAIN']) && strpos($_SERVER['ACCESS_DOMAIN'], 'gridserver.com') !== false && getenv('FCGI_ROLE'))
			{
				$htaccess = <<<HT
# Force (mt) to use PHP 5.5 due to FCGI incompatibilies
AddHandler php5latest-script .php

HT;
				file_put_contents('.htaccess', $htaccess, FILE_APPEND);
			}

			if (!count($php['fail']) > 0)
			{
				if (!$writable || (!$download && $download_error === 'perms'))
				{
					$permissions['fail'][] = 'This directory does not have the necessary permissions to perform the install. Set the permissions on the koken folder and the koken/index.php file to 777.';
				}
				else if (!$download && $download_error === 'extensions')
				{
					$php['fail'][] = 'Koken requires the <a href="http://php.net/curl" target="_blank">cURL extension</a> to be enabled in PHP. Consult with your host and modify your server configuration to enable the cURL extension.';
				}
				else if (!$download)
				{
					$connection['fail'][] = 'Koken cannot download the necessary files from install.koken.me. Check with your host to see if they are limiting outgoing connections and if so, have them open a path to install.koken.me.' . ( $download_error ? " Error: $download_error" : '' );
				}
			}

			if (count($php['fail']) > 0)
			{
				$permissions['warn'][] = 'Permissions can not be tested until the above PHP requirements are met.';
				$connection['warn'][] = 'Connections can not be tested until the above PHP requirements are met.';
			}

			$disabled_functions = explode(',', str_replace(' ', '', ini_get('disable_functions')));

			if (ini_get('suhosin.executor.func.blacklist'))
			{
				$disabled_functions = array_merge($disabled_functions, explode(',', str_replace(' ', '', ini_get('suhosin.executor.func.blacklist'))));
			}

			if (!$imagick && (!is_callable('exec') || in_array('exec', $disabled_functions)))
			{
				if (in_array('gd', get_loaded_extensions()))
				{
					$magick_path = 'gd';
				}
				else
				{
					$im['fail'][] = "Koken requires either the GD library, the 'imagick' PECL extension or ImageMagick in order to process images. Ask your host to enable one of those options before continuing installation.";
				}
			}
			else if (!$imagick)
			{
				$common_magick_paths = array(
					'$PATH', '/usr/bin', '/usr/local/bin', '/usr/local/sbin', '/bin', '/opt/local/bin', '/opt/ImageMagick/bin', '/usr/local/ImageMagick/bin'
				);
				if (isset($_POST['custom_magick_path']) && !empty($_POST['custom_magick_path']))
				{
					$common_magick_paths[] =  $_POST['custom_magick_path'];
				}
				$magick_path = false;
				$magicks = array();
				foreach($common_magick_paths as $path)
				{
					if ($path === '$PATH')
					{
						$path = 'convert';
					}
					else if (!preg_match("/\/convert$/", $path) && strpos('\\', $path) === false)
					{
						$path = rtrim($path, '/') . '/convert';
					}
					$out = '';
					exec($path . ' -version', $out);
					$test = $out[0];
					if (!empty($test) && preg_match('/\d+\.\d+\.\d+/', $test, $matches)) {
						$im['pass_string'] = $matches[0];
						$magicks[] = array('path' => $path, 'version' => $matches[0]);
					}
				}

				if (empty($magicks))
				{
					if (in_array('gd', get_loaded_extensions()))
					{
						$magick_path = 'gd';
					}
					else
					{
						$im['fail'][] = 'Koken cannot locate the GD or ImageMagick library on your server.<br><br>We looked for ImageMagick\'s <code>convert</code> in the following locations: <code>' . join('</code>, <code>', $common_magick_paths) . '</code>.<br><br>Ask your host for the path to ImageMagick on your server and enter it below.';
					}
				}
				else
				{
					$top = array_shift($magicks);
					foreach($magicks as $m)
					{
						if (version_compare($m['version'], $top['version']) > 0)
						{
							$top = $m;
						}
					}

					if (substr($top['version'], 0, 1) === '6')
					{
						$magick_path = $top['path'];
					}
					else
					{
						if (in_array('gd', get_loaded_extensions()))
						{
							$magick_path = 'gd';
						}
						else
						{
							$im['fail'][] = 'The version of ImageMagick on your server is not compatible with Koken.<br>Koken requires ImageMagick 6+, but your server has ' . $top['version'] . '.<br>Ask your host for the path to a newer version of ImageMagick and enter it below or enable the PHP GD library and restart the installation.';
						}
					}
				}
			}

			if ($imagick)
			{
				$magick_path = 'convert';
			}

			if (isset($magick_path))
			{
				$im['path'] = $magick_path;
			}

			header('Content-type: application/json');
			die(json_encode( array(
					'php' => $php,
					'permissions' => $permissions,
					'connection' => $connection,
					'im' => $im,
					'browser' => $browser
				)
			));
		}
		else
		{
			if (file_exists('core.zip') || download('https://s3.amazonaws.com/install.koken.me/releases/latest.zip', 'core.zip'))
			{
				require('pclzip.lib.php');

				unlink(__FILE__);

				$archive = new PclZip('core.zip');
				$archive->extract(PCLZIP_CB_POST_EXTRACT, 'extract_callback');

				$storage = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR;
				$zip = 'elementary.zip';

				if (!file_exists($zip))
				{
					download('https://koken-store.s3.amazonaws.com/plugins/be1cb2d9-ed05-2d81-85b4-23282832eb84.zip', $zip);
				}

				rename($zip, $storage . $zip);

				chdir($storage);

				$theme_zip = new PclZip($zip);
				$theme_zip->extract(PCLZIP_CB_POST_EXTRACT, 'extract_callback');

				rename('be1cb2d9-ed05-2d81-85b4-23282832eb84', 'elementary');
				unlink($zip);

				chdir(dirname(__FILE__));

				unlink('pclzip.lib.php');
				unlink('core.zip');

				if (isset($_POST['use_conf_file']))
				{
					$conf = file_get_contents('database.php');
				}
				else
				{
					$_POST['password'] = str_replace("'", "\\'", $_POST['password']);

					$conf = <<<OUT
<?php

	\$KOKEN_DATABASE = array(
		'driver' => '{$_POST['driver']}',
		'hostname' => '{$_POST['host']}',
		'database' => '{$_POST['name']}',
		'username' => '{$_POST['user']}',
		'password' => '{$_POST['password']}',
		'prefix' => '{$_POST['prefix']}',
		'socket' => ''
	);
OUT;
				}

				if ($_POST['magick'] !== 'convert')
				{
					$user_setup = file_get_contents('storage/configuration/user_setup.php');
					$user_setup = str_replace('convert', $_POST['magick'], $user_setup);
					file_put_contents('storage/configuration/user_setup.php', $user_setup);
				}

				if (file_put_contents('storage/configuration/database.php', $conf))
				{
					@unlink('database.php');
					$data['success'] = true;
				}
				else
				{
					// TODO: Fail
				}

			}
			else
			{
				$data['error'] = 'Permissions';
			}
		}
		header('Content-type: application/json');
		die( json_encode($data) );
		exit;
	}
	else if (file_exists('database.php'))
	{
		include('database.php');
		$data = test_database($KOKEN_DATABASE['hostname'], $KOKEN_DATABASE['username'], $KOKEN_DATABASE['password'], $KOKEN_DATABASE['database'], $KOKEN_DATABASE['prefix']);
		$has_database_config = !isset($data['error']);
	}

?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">

	<title>Koken - Setup</title>

	<!-- css -->
	<link rel="stylesheet" href="//s3.amazonaws.com/install.koken.me/css/screen.css">




	<!--[if IE]>
		<script src="//html5shiv.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->

	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js"></script>
	<script>
		$(function() {

			$(window).on('keydown', function(e) {
				if (e.keyCode === 13 && $('button:visible').length === 1) {
					$('button:visible').trigger('click');
				}
			});

			var payload = {}, database = { magick: 'convert' }, hold = false;

			$('a.toggle').bind('click', function() {
				$(this).toggleClass('open');
				$(this).parent().siblings('.row').toggle();
			});
			for (i in jstz.olson.timezones) {
				var t = jstz.olson.timezones[i],
					parts = i.split(',');
					offset = parts[0]/60;

				if (offset > 0) {
					offset = '+' + offset;
				} else if (offset == 0) {
					offset = '';
				} else {
					offset = String(offset);
				}
				$('<option/>').attr('value', t).text('(GMT' + String(offset).replace('.5', ':30').replace('.75', ':45') + ') ' + t.replace('Etc/', '')).appendTo('#tz');
			}

			var tz = jstz.determine();
			tz = tz ? tz.name() : 'Etc/UTC';
			$('#tz').val(tz);

			var current = false;
			var has_database_config = <?php echo $has_database_config ? 'true' : 'false'; ?>;

			if (has_database_config) {
				database.use_conf_file = true;
				database.magick = 'convert';
				var	steps = [ 'admin', 'key', 'opt', 'signup', 'dl', 'wait', 'final' ];
			} else {
				var	steps = [ 'test', 'admin', 'db', 'key', 'opt', 'signup', 'dl', 'wait', 'final' ];
			}

			function next() {

				var index, p;

				if (current) {
					$('#setup-' + current).removeClass().addClass('animated fadeOutLeft');
					index = $.inArray(current, steps) + 1;
				} else {
					index = 0;
				}

				current = steps[ index ];

				if (current === 'key') {
					current = 'opt';
				}

				if (current === 'final') {
					$('#progress-strip').removeClass().addClass('animated fadeOutDown')
				} else {
					p = ((index+1)/(steps.length-1));
					$('#progress').css({
						width: p*100 + '%'
					});
					if (p === 1) {
						$('#progress span').addClass('animate');
					}
				}


				$('#setup-' + current).removeClass().addClass('animated fadeInRight').show();

			}

			function test() {

				var groups = [ 'php', 'permissions', 'connection', 'im', 'browser' ],
					magick_path = $('#custom_magick_path').length ? $('#custom_magick_path').val().trim() : null;

				$('div.test').removeClass('fail warn pass loading').find('span').remove();
				$('p.testerr').remove();
				$('#test-failed').hide();
				$('#test-wait').html('Testing your server for compatibility. Please wait.')
				$('#run-again').hide();

				$('[data-group="' + groups[0] + '"]').addClass('loading');

				$.post(location.href, {
						server_test: true,
						custom_magick_path: magick_path
					}, function(data) {

						var	intId = setInterval(function() {
							if (groups.length) {
								var g = groups.shift(),
									el = $('[data-group="' + g + '"]'),
									nextGroup = $('[data-group="' + groups[0] + '"]'),
									results = data[g];

								if (results.fail.length || results.warn.length) {

									var key = results.fail.length ? 'fail' : 'warn';

									var p = $('<p />').addClass('testerr ' + key).html( '<span>' + results[key].join(' ') + '</span>' );
									$('#test-errors').append(p);
									el.addClass(key);

									if (g === 'im' && p.text().indexOf('requires either') === -1) {
										var form = '<input type="text" id="custom_magick_path" placeholder="Enter path to ImageMagick here" />';
										p.append(form);
									}

									p.hide();

									$('#test-failed').show();

									p.addClass('animated fadeInUp').show();

								} else {

									var text;

									if (results.pass_string.length) {
										text = results.pass_string;
									} else {
										switch(g) {
											case 'permissions':
												text = 'Set';
												break;

											case 'connection':
												text = 'Connected';
												break;

											case 'browser':
												text = 'Supported';
												break;
										}
									}

									el.addClass('pass');

									if (g === 'im') {
										database.magick = results.path;
									}

								}

								if (nextGroup.length) {
									nextGroup.addClass('loading');
								} else {
									clearInterval(intId);
									if ($('div.pass').length === $('div.test').length) {
										$('#test-wait').html('<strong class="success">Your server meets the minimum system requirements.</strong><br>Click the button below to begin installation.');
										$('#test-passed').addClass('animated fadeInUp').show();
									} else {
										$('#test-wait').html('<strong>Your server does not meet the minimum system requirements.</strong><br>Correct the problems listed below then try again.');
										$('#run-again').fadeIn();
									}
								}
							}
						}, 750);
					}
				);
			}

			$(document).on('webkitAnimationEnd mozAnimationEnd msAnimationEnd animationend', '.animated', function() {
				var el = $(this);
				if (!el.attr('id') || el.attr('id').indexOf('test-') === 0) return false;
				if (el.attr('id') === 'setup-' + current) {
					var first = el.find('input:first');
					if (first.length) {
						first[0].focus();
					}
					if (current === 'test') {
						test();
					}
				} else {
					el.hide();
				}
			})

			next();

			$('button').click(function() {
				if (hold) return;

				if ($(this).data('step') === 'test') {
					test();
					return;
				}

				if ($(this).data('step') === 'done') {
					location.href = "admin/";
					return;
				}
				var valid = true;
				$('.form-error-msg').remove();

				$('input:visible').each(function() {
					if ($(this).val().trim() === '' && !$(this).data('optional')) {
						$(this).parent().append(
							$('<span/>').addClass('form-error-msg').css('display', 'inline').text('This field cannot be left blank')
						);
						this.focus();
						valid = false;
						return false;
					} else if ($(this).attr('type') === 'email' && !$(this).val().trim().match(/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i)) {
						$(this).parent().append(
							$('<span/>').addClass('form-error-msg').css('display', 'inline').text('Not a valid email address')
						);
						this.focus();
						valid = false;
						return false;
					}
				});

				if (valid) {
					if ($(this).data('step') === 3) {
						$.post(location.href, {
							database_check: true,
							host: $('#database_hostname').val().trim(),
							user: $('#database_username').val().trim(),
							password: $('#database_password').val().trim(),
							name: $('#database_name').val().trim(),
							prefix: $('#database_prefix').val().trim()
						}, function(data) {
							if (data.error || !data.driver) {
								$('#setup-db header').after(
									$('<div/>').addClass('database-error-msg').append( $('<span/>').addClass('form-error-msg').css('display', 'inline').text('Database connection error: ' + data.error) )
								)
							} else {
								database.driver = data.driver;
								database.host = data.host;
								database.user = $('#database_username').val().trim();
								database.password = $('#database_password').val().trim();
								database.name = $('#database_name').val().trim();
								database.prefix = $('#database_prefix').val().trim();
								next();
							}
						});
					} else {
						next();

						if ($(this).data('step') === 5) {
							$('#subscribe').val( $('#email').val().trim() );
						}

						if ($(this).data('step') === 6 && $(this).data('subscribe') === true) {
							payload.subscribe = $('#subscribe').val();
						}

						if ($(this).data('step') === 7) {
							hold = true;

							payload.timezone = $('#tz').val();
							payload.first_name = $('#first_name').val().trim();
							payload.last_name = $('#last_name').val().trim();
							payload.email = $('#email').val().trim();
							payload.password = $('#password').val().trim();
							$.post(location.href, database, function(data) {
								if (data.success) {
									$.post('api.php?/install/complete', payload, function(data) {
										if (data.success) {
											next();
											hold = false;
										}
									});
								} else {

								}
							});
						}

					}
				} else {
					return false;
				}
			});
		});

		/*! jsTimezoneDetect - v1.0.5 - 2013-04-01 */
		(function(e){var t=function(){"use strict";var e="s",n=2011,r=function(e){var t=-e.getTimezoneOffset();return t!==null?t:0},i=function(e,t,n){var r=new Date;return e!==undefined&&r.setFullYear(e),r.setDate(n),r.setMonth(t),r},s=function(e){return r(i(e,0,2))},o=function(e){return r(i(e,5,2))},u=function(e){var t=e.getMonth()>7?o(e.getFullYear()):s(e.getFullYear()),n=r(e);return t-n!==0},a=function(){var t=s(n),r=o(n),i=t-r;return i<0?t+",1":i>0?r+",1,"+e:t+",0"},f=function(){var e=a();return new t.TimeZone(t.olson.timezones[e])},l=function(e){var t=new Date(2010,6,15,1,0,0,0),n={"America/Denver":new Date(2011,2,13,3,0,0,0),"America/Mazatlan":new Date(2011,3,3,3,0,0,0),"America/Chicago":new Date(2011,2,13,3,0,0,0),"America/Mexico_City":new Date(2011,3,3,3,0,0,0),"America/Asuncion":new Date(2012,9,7,3,0,0,0),"America/Santiago":new Date(2012,9,3,3,0,0,0),"America/Campo_Grande":new Date(2012,9,21,5,0,0,0),"America/Montevideo":new Date(2011,9,2,3,0,0,0),"America/Sao_Paulo":new Date(2011,9,16,5,0,0,0),"America/Los_Angeles":new Date(2011,2,13,8,0,0,0),"America/Santa_Isabel":new Date(2011,3,5,8,0,0,0),"America/Havana":new Date(2012,2,10,2,0,0,0),"America/New_York":new Date(2012,2,10,7,0,0,0),"Asia/Beirut":new Date(2011,2,27,1,0,0,0),"Europe/Helsinki":new Date(2011,2,27,4,0,0,0),"Europe/Istanbul":new Date(2011,2,28,5,0,0,0),"Asia/Damascus":new Date(2011,3,1,2,0,0,0),"Asia/Jerusalem":new Date(2011,3,1,6,0,0,0),"Asia/Gaza":new Date(2009,2,28,0,30,0,0),"Africa/Cairo":new Date(2009,3,25,0,30,0,0),"Pacific/Auckland":new Date(2011,8,26,7,0,0,0),"Pacific/Fiji":new Date(2010,10,29,23,0,0,0),"America/Halifax":new Date(2011,2,13,6,0,0,0),"America/Goose_Bay":new Date(2011,2,13,2,1,0,0),"America/Miquelon":new Date(2011,2,13,5,0,0,0),"America/Godthab":new Date(2011,2,27,1,0,0,0),"Europe/Moscow":t,"Asia/Yekaterinburg":t,"Asia/Omsk":t,"Asia/Krasnoyarsk":t,"Asia/Irkutsk":t,"Asia/Yakutsk":t,"Asia/Vladivostok":t,"Asia/Kamchatka":t,"Europe/Minsk":t,"Pacific/Apia":new Date(2010,10,1,1,0,0,0),"Australia/Perth":new Date(2008,10,1,1,0,0,0)};return n[e]};return{determine:f,date_is_dst:u,dst_start_for:l}}();t.TimeZone=function(e){"use strict";var n={"America/Denver":["America/Denver","America/Mazatlan"],"America/Chicago":["America/Chicago","America/Mexico_City"],"America/Santiago":["America/Santiago","America/Asuncion","America/Campo_Grande"],"America/Montevideo":["America/Montevideo","America/Sao_Paulo"],"Asia/Beirut":["Asia/Beirut","Europe/Helsinki","Europe/Istanbul","Asia/Damascus","Asia/Jerusalem","Asia/Gaza"],"Pacific/Auckland":["Pacific/Auckland","Pacific/Fiji"],"America/Los_Angeles":["America/Los_Angeles","America/Santa_Isabel"],"America/New_York":["America/Havana","America/New_York"],"America/Halifax":["America/Goose_Bay","America/Halifax"],"America/Godthab":["America/Miquelon","America/Godthab"],"Asia/Dubai":["Europe/Moscow"],"Asia/Dhaka":["Asia/Yekaterinburg"],"Asia/Jakarta":["Asia/Omsk"],"Asia/Shanghai":["Asia/Krasnoyarsk","Australia/Perth"],"Asia/Tokyo":["Asia/Irkutsk"],"Australia/Brisbane":["Asia/Yakutsk"],"Pacific/Noumea":["Asia/Vladivostok"],"Pacific/Tarawa":["Asia/Kamchatka"],"Pacific/Tongatapu":["Pacific/Apia"],"Africa/Johannesburg":["Asia/Gaza","Africa/Cairo"],"Asia/Baghdad":["Europe/Minsk"]},r=e,i=function(){var e=n[r],i=e.length,s=0,o=e[0];for(;s<i;s+=1){o=e[s];if(t.date_is_dst(t.dst_start_for(o))){r=o;return}}},s=function(){return typeof n[r]!="undefined"};return s()&&i(),{name:function(){return r}}},t.olson={},t.olson.timezones={"-720,0":"Pacific/Majuro","-660,0":"Pacific/Pago_Pago","-600,1":"America/Adak","-600,0":"Pacific/Honolulu","-570,0":"Pacific/Marquesas","-540,0":"Pacific/Gambier","-540,1":"America/Anchorage","-480,1":"America/Los_Angeles","-480,0":"Pacific/Pitcairn","-420,0":"America/Phoenix","-420,1":"America/Denver","-360,0":"America/Guatemala","-360,1":"America/Chicago","-360,1,s":"Pacific/Easter","-300,0":"America/Bogota","-300,1":"America/New_York","-270,0":"America/Caracas","-240,1":"America/Halifax","-240,0":"America/Santo_Domingo","-240,1,s":"America/Santiago","-210,1":"America/St_Johns","-180,1":"America/Godthab","-180,0":"America/Argentina/Buenos_Aires","-180,1,s":"America/Montevideo","-120,0":"America/Noronha","-120,1":"America/Noronha","-60,1":"Atlantic/Azores","-60,0":"Atlantic/Cape_Verde","0,0":"UTC","0,1":"Europe/London","60,1":"Europe/Berlin","60,0":"Africa/Lagos","60,1,s":"Africa/Windhoek","120,1":"Asia/Beirut","120,0":"Africa/Johannesburg","180,0":"Asia/Baghdad","210,1":"Asia/Tehran","240,0":"Asia/Dubai","240,1":"Asia/Baku","270,0":"Asia/Kabul","300,1":"Asia/Yekaterinburg","300,0":"Asia/Karachi","330,0":"Asia/Kolkata","345,0":"Asia/Kathmandu","360,0":"Asia/Dhaka","360,1":"Asia/Omsk","390,0":"Asia/Rangoon","420,1":"Asia/Krasnoyarsk","420,0":"Asia/Jakarta","480,0":"Asia/Shanghai","480,1":"Asia/Irkutsk","525,0":"Australia/Eucla","525,1,s":"Australia/Eucla","540,1":"Asia/Yakutsk","540,0":"Asia/Tokyo","570,0":"Australia/Darwin","570,1,s":"Australia/Adelaide","600,0":"Australia/Brisbane","600,1":"Asia/Vladivostok","600,1,s":"Australia/Sydney","630,1,s":"Australia/Lord_Howe","660,1":"Asia/Kamchatka","660,0":"Pacific/Noumea","690,0":"Pacific/Norfolk","720,1,s":"Pacific/Auckland","720,0":"Pacific/Tarawa","765,1,s":"Pacific/Chatham","780,0":"Pacific/Tongatapu","780,1,s":"Pacific/Apia","840,0":"Pacific/Kiritimati"},typeof exports!="undefined"?exports.jstz=t:e.jstz=t})(this);
	</script>
</head>
<body>

	<div id="progress-strip"><div id="progress"><span><span></span></span></div></div>

	<div id="container">

		<img id="logo" src="//s3.amazonaws.com/install.koken.me/img/koken-logo.png" width="71" height="71" />

		<div id="content">

			<div id="setup-test">
				<header>

					<h1>Server test</h1>

					<div class="front">

						<div id="test-wrap">
							<div data-group="php" class="test"></div>
							<div data-group="permissions" class="test"></div>
							<div data-group="connection" class="test"></div>
							<div data-group="im" class="test"></div>
							<div data-group="browser" class="test"></div>
						</div>

						<p id="test-wait">Testing your server for compatibility. Please wait.</p>

						<div id="test-failed">

							<div id="test-errors"></div>

							<div id="run-again" style="display:none" class="row button">

								<button data-step="test" title="Run serve test again">Run test again</button>

							</div>
						</div>

						<div id="test-passed">

							<p class="small">
								By installing this application you agree to the<br><a href="http://koken.me/eula.html" title="View Koken License Agreement in separate window" onclick="return !window.open(this.href);">License Agreement</a> and <a href="http://koken.me/privacy.html" title="View Privacy Policy in separate window" onclick="return !window.open(this.href);">Privacy Policy</a>.
							</p>

							<div class="row button">

								<button data-step="1" title="Next step">Begin installation</button>

							</div>

						</div>

				</header>
			</div>

			<div id="setup-intro">

				<header>

					<h1>Welcome to Koken</h1>

					<div class="front">

						<p class="big">
							Your server meets the minimum system requirements.
							<br>
							Click the button below to start.
						</p>

						<br>

						<p class="small">
							By installing this application you agree to the<br><a href="http://koken.me/eula.html" title="View Koken License Agreement in separate window" onclick="return !window.open(this.href);">License Agreement</a> and <a href="http://koken.me/privacy.html" title="View Privacy Policy in separate window" onclick="return !window.open(this.href);">Privacy Policy</a>.
						</p>

					</div>

				</header>

				<div class="row button">

					<button data-step="1" title="Next step">Begin installation</button>

				</div>

			</div> <!-- close #setup-intro -->

			<div id="setup-admin">

				<header>

					<h1>Setup user</h1>
					<p>This will be the administrator for this installation.</p>

				</header>

				<div class="col-half lcol">

					<div class="row">
						<label for="">First name</label>
						<input id="first_name" type="text" />
					</div>

					<div class="row">
						<label for="">Last name</label>
						<input id="last_name" type="text" />
					</div>

				</div>

				<div class="col-half rcol">

					<div class="row">
						<label for="">Email</label>
						<input id="email" type="email" placeholder="you@domain.com" />
					</div>

					<div class="row">
						<label for="">Password</label>
						<input id="password" type="password" />
					</div>

				</div>

				<div class="col-full">

					<div class="row button">

						<button data-step="2" title="Next step">Next &rarr;</button>

					</div>

				</div>

			</div> <!-- close #setup-admin -->

			<div id="setup-db">

				<header>

					<h1>Connect to database</h1>
					<p>Enter your MySQL database information.</p>

				</header>

				<div class="col-half lcol">

					<div class="row">
						<label for="">Hostname</label>
						<input id="database_hostname" type="text" />

					</div>

					<div class="row">
						<label for="">Database name</label>
						<input id="database_name" type="text" />
					</div>

				</div>

				<div class="col-half rcol">

					<div class="row">
						<label for="">Username</label>
						<input id="database_username" type="text" />
					</div>

					<div class="row">
						<label for="">Password</label>
						<input id="database_password" type="password" data-optional="true" />
					</div>

				</div>

				<div class="col-half lcol">

					<div class="row">
						<a href="#" class="toggle" title="View advanced options">Advanced options</a>
					</div>

					<div class="row" style="display:none">
						<label for="">Table prefix</label>
						<input id="database_prefix" type="text" value="koken_" />
					</div>

				</div>

				<div class="col-full">

					<div class="row button">

						<button data-step="3" title="Next step">Next &rarr;</button>

					</div>

				</div>

			</div> <!-- close #setup-db -->

			<div id="setup-dl">

				<header>

					<h1>Download and install</h1>
					<p>Ready to download and setup Koken. Click the button below to begin.<br>This should only take a few minutes.</p>

				</header>

				<!-- initial state -->
				<button data-step="7" title="Install it">Install now</button>

				<!-- download progress -->
				<div class="col-half" style="display:none;">
					<h2>Downloading...</h2>
				</div>

			</div> <!-- close #setup-dl -->

			<div id="setup-opt">

				<header>

					<h1>Set timezone</h1>
					<p>Your timezone has been estimated below. Change if necessary.</p>

				</header>

				<div class="col-full">

					<div class="row">
						<label for="tz">Time zone</label>
						<select id="tz">
						</select>
					</div>

				</div>

				<div class="col-full">

					<div class="row button">
						<button data-step="5" title="Next step">Next &rarr;</button>
					</div>

				</div>

			</div> <!-- close #setup-opt -->

			<div id="setup-signup">

				<header>

					<h1>Keep in touch</h1>

					<p>
						Our email newsletter contains the latest news, announcements and special offers.<br>Keep in touch by adding your email address below.
					</p>

				</header>

				<div class="row">

					<input id="subscribe" type="email" class="half" />

				</div>

				<div class="row button">

					<button data-step="6" class="sec" title="No thanks" data-subscribe="false">No thanks</button>&nbsp;&nbsp;<button data-step="6" title="Sign up" data-subscribe="true">Yes, sign me up</button>

				</div>

			</div> <!-- close #setup-signup -->

			<div id="setup-wait">

				<header>

					<h1>Installing...</h1>
					<p>Koken is now being downloaded and installed. Please wait.</p>

				</header>

			</div> <!-- close #setup-wait -->

			<div id="setup-final">

				<header>

					<h1>Installation complete</h1>

					<p>
						All done. Click the button below to start using Koken.
					</p>

				</header>

				<div class="col-full">

					<div class="row button">
						<button data-step="done" title="Start using Koken">Start</button>
					</div>

				</div>

			</div> <!-- close #setup-final -->

		</div> <!-- close #content -->

	</div> <!-- close #container -->

</body>

</html>