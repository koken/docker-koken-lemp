<?php

	error_reporting(0);
	set_time_limit(0);

	$ready = file_exists('ready.txt');

	if (isset($_POST['del']))
	{
		unlink(__FILE__);
		unlink('ready.txt');
		exit;
	}
	else if (isset($_POST['ready']))
	{
		header('Content-type: application/json');
		die(json_encode(array(
			'ready' => $ready
		)));
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

			var payload = { image_processing: 'gm convert' }, hold = false;

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
			var ready = <?php echo $ready ? 'true' : 'false'; ?>;
			var	steps = [ 'admin', 'key', 'opt', 'signup', 'wait', 'final' ];

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
					if (current === 'downloading') {
						p = 1;
					} else {
						p = ((index+1)/(steps.length-1));
					}
					$('#progress').css({
						width: p*100 + '%'
					});

					if (p === 1) {
						$('#progress span').addClass('animate');
					} else {
						$('#progress span').removeClass('animate');
					}
				}

				$('#setup-' + current).removeClass().addClass('animated fadeInRight').show();

			}

			$(document).on('webkitAnimationEnd mozAnimationEnd msAnimationEnd animationend', '.animated', function() {
				var el = $(this);
				if (!el.attr('id')) return false;
				if (el.attr('id') === 'setup-' + current) {
					var first = el.find('input:first');
					if (first.length) {
						first[0].focus();
					}
				} else {
					el.hide();
				}
			});

			function isReady() {
				$.post(location.href, { ready: 1 }, function(data) {
					if (data.ready) {
						next();
					} else {
						setTimeout(isReady, 1000);
					}
				});
			}

			if (!ready) {
				steps.unshift('downloading');
				setTimeout(isReady, 2000);
			}

			next();

			$('button').click(function() {
				if (hold) return;

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

						$.post(location.href, { del: 1});

						$.post('api.php?/install/complete', payload, function(data) {
							if (data.success) {
								next();
								hold = false;
							}
						});
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

		<img id="logo" src="//s3.amazonaws.com/install.koken.me/img/koken_logo.svg" width="71" height="71" />

		<div id="content">

			<div id="setup-admin">

				<header>

					<h1>Setup user</h1>
					<p>This will be the administrator for this installation.</p>

				</header>

				<div class="col-half lcol">

					<div class="row">
						<label for="first_name">First name</label>
						<input id="first_name" type="text" />
					</div>

					<div class="row">
						<label for="last_name">Last name</label>
						<input id="last_name" type="text" />
					</div>

				</div>

				<div class="col-half rcol">

					<div class="row">
						<label for="email">Email</label>
						<input id="email" type="email" placeholder="you@domain.com" />
					</div>

					<div class="row">
						<label for="password">Password</label>
						<input id="password" type="password" />
					</div>

				</div>

				<div class="col-full">

					<div class="row button">

						<button data-step="2" title="Next step">Next &rarr;</button>

					</div>

				</div>

			</div> <!-- close #setup-admin -->

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

					<button data-step="7" class="sec" title="No thanks" data-subscribe="false">No thanks</button>&nbsp;&nbsp;<button data-step="7" title="Sign up" data-subscribe="true">Yes, sign me up</button>

				</div>

				<div class="row" style="margin-top:3em;">

					<p class="mute small">
						By installing this application you agree to our<br><a href="http://koken.me/eula.html" title="View Koken License Agreement in separate window" onclick="return !window.open(this.href);">License Agreement</a> and <a href="http://koken.me/privacy.html" title="View Privacy Policy in separate window" onclick="return !window.open(this.href);">Privacy Policy</a>.
					</p>

				</div>

			</div> <!-- close #setup-signup -->

			<div id="setup-downloading">

				<header>

					<h1>One moment...</h1>
					<p>We're downloading and preparing Koken just for you.<br>This should only take a few seconds.</p>

				</header>

			</div> <!-- close #setup-downloading -->

			<div id="setup-wait">

				<header>

					<h1>Installing...</h1>
					<p>Koken is now being installed. Please wait...</p>

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