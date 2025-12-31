<?php

use function GuzzleHttp\json_decode;

defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="row">
	<div class="col-md-12">

		<?php


		$statuses = $this->leads_model->get_status();
		$sources  = $this->leads_model->get_source();
		$staff = $this->staff_model->get('', ['active' => 1]);
		if (!get_option('appId')) {
			update_option('appId', 'changeme');
		}
		if (!get_option('appSecret')) {
			update_option('appSecret', 'changeme');
		}
		if (!get_option('verifytoken')) {
			update_option('verifytoken', 'token654321');
		}
		if (!get_option('subscribed_pages')) {
			update_option('subscribed_pages', json_encode(array()));
		}

		?>
		<h3><?php echo htmlspecialchars(_l('facebookleadsintegration')); ?></h3>
		<hr />
		<h4><?php echo htmlspecialchars(_l('app_settings')); ?></h4>
		<br>
		<?php echo render_input('settings[appId]', 'app_id', get_option('appId')); ?>
		<input type="hidden" value="<?php echo htmlspecialchars(get_option('appId')) ?>" id="appId">
		<?php echo render_input('settings[appSecret]', 'app_secret', get_option('appSecret')); ?>
		<input type="hidden" value="<?php echo htmlspecialchars(get_option('appSecret')) ?>" id="appSecret">

		<hr />
		<br>
		<h4><?php echo htmlspecialchars(_l('leadsmodule_settings')); ?></h4>
		<br>
		<?php echo render_input('settings[verifytoken]', 'verify_token', get_option('verifytoken')); ?>
		<p><?php echo htmlspecialchars(_l('newleads_settings')); ?></p>
		<div class="row">

			<div class="col-md-4 leads-filter-column">
				<select name="view_assigned" id="view_assigned" data-live-search="true" class="selectpicker" data-width="100%" onchange="updateValue(1)">
					<option value=""><?php echo htmlspecialchars(_l('leads_dt_assigned')); ?></option>
					<?php

					foreach ($staff as $member) { ?>
						<option value="<?php echo htmlspecialchars($member['staffid']) ?>" <?php if ($member['staffid'] == get_option("facebook_lead_assigned")) {
																								echo htmlspecialchars("selected");
																							} ?>><?php echo htmlspecialchars($member['firstname'] . ' ' . $member['lastname']); ?></option>
					<?php
					}
					?>
				</select>

			</div>
			<div class="col-md-4 leads-filter-column">


				<select name="view_source" id="view_source" data-live-search="true" class="selectpicker" data-width="100%" onchange="updateValue(2)">
					<option value=""><?php echo htmlspecialchars(_l('leads_source')); ?></option>
					<?php

					foreach ($sources as $source) { ?>
						<option value="<?php echo htmlspecialchars($source['id']) ?>" <?php if ($source['id'] == get_option("facebook_lead_source")) {
																							echo htmlspecialchars("selected");
																						} ?>><?php echo htmlspecialchars($source['name']); ?></option>
					<?php
					}
					?>
				</select>

			</div>
			<div class="col-md-4 leads-filter-column">


				<select name="view_status" id="view_status" data-live-search="true" class="selectpicker" data-width="100%" onchange="updateValue(3)">
					<option value=""><?php echo htmlspecialchars(_l('status')); ?></option>
					<?php

					foreach ($statuses as $status) { ?>
						<option value="<?php echo htmlspecialchars($status['id']) ?>" <?php if ($status['id'] == get_option("facebook_lead_status")) {
																							echo htmlspecialchars("selected");
																						} ?>><?php echo htmlspecialchars($status['name']); ?></option>
					<?php
					}
					?>
				</select>



			</div>
			<br>
		</div>
		<br>
		<p><?php echo htmlspecialchars(_l('webhook_callback_url')); ?></p>
		<div class="input-group">
			<input type="text" id="facebook-webhook-url" name="webhookurl" class="form-control" readonly value="<?php echo htmlspecialchars(base_url()); ?>facebookleadsintegration/webhook">
			<span class="input-group-btn">
				<button type="button" class="btn btn-default" onclick="copyWebhookUrl()"><?php echo htmlspecialchars(_l('copy') ?? 'Copy'); ?></button>
			</span>
		</div>
		<small class="text-muted"><?php echo htmlspecialchars(_l('use_this_url_to_configure_facebook_webhook') ?? 'Use this URL in your Facebook App webhook configuration.'); ?></small>

		<hr />
		<br>
		<h4><?php echo htmlspecialchars(_l('fetch_settings')); ?></h4>
		<br>
		<!-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script> -->
		<script>
			const subscribeEndpoint = '<?php echo admin_url('facebookleadsintegration/subscribePage'); ?>';
			const unsubscribeEndpoint = '<?php echo admin_url('facebookleadsintegration/unsubscribePage'); ?>';
			const subscribeLabel = '<?php echo htmlspecialchars(_l('fbleadssubscribe')); ?>';
			const unsubscribeLabel = '<?php echo htmlspecialchars(_l('fbleadsunsubscribe')); ?>';

			function showNotification(type, message) {
				if (typeof alert_float === 'function') {
					alert_float(type, message);
				} else {
					console.log(type, message);
				}
			}

			function toggleButtonLoading($button, loading) {
				if (loading) {
					$button.data('original-text', $button.val());
					$button.prop('disabled', true).val('Please wait...');
				} else {
					var originalText = $button.data('original-text');
					if (originalText) {
						$button.val(originalText);
					}
					$button.prop('disabled', false);
				}
			}

			function setSubscribedState(page_id, page_access_token) {
				$("#" + page_id)
					.attr("onclick", "unsubscribeApps(" + page_id + ",'" + page_access_token + "')")
					.attr("value", unsubscribeLabel)
					.removeClass("btn-info")
					.addClass('btn-danger');
			}

			function setUnsubscribedState(page_id, page_access_token) {
				$("#" + page_id)
					.attr("onclick", "subscribe(" + page_id + ",'" + page_access_token + "')")
					.attr("value", subscribeLabel)
					.removeClass("btn-danger")
					.addClass('btn-info');
			}

			function subscribe(id, access_token) {
				var $button = $('#' + id);
				toggleButtonLoading($button, true);

				$.ajax({
					url: subscribeEndpoint,
					type: 'POST',
					dataType: 'json',
					data: {
						id: id,
						access_token: access_token
					},
					success: function(resp) {
						if (resp && resp.success) {
							setSubscribedState(id, access_token);
							if (resp.webhook_url) {
								$('#facebook-webhook-url').val(resp.webhook_url);
							}
							showNotification('success', resp.message || 'Subscribed successfully.');
						} else {
							showNotification('danger', (resp && resp.message) ? resp.message : 'Unable to subscribe this page.');
						}
					},
					error: function(xhr) {
						var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Unable to subscribe this page.';
						showNotification('danger', message);
					},
					complete: function() {
						toggleButtonLoading($button, false);
					}
				});
			}

			function unsubscribeApps(page_id, page_access_token) {
				var $button = $('#' + page_id);
				toggleButtonLoading($button, true);

				$.ajax({
					url: unsubscribeEndpoint,
					type: 'POST',
					dataType: 'json',
					data: {
						id: page_id,
						access_token: page_access_token
					},
					success: function(resp) {
						if (resp && resp.success) {
							setUnsubscribedState(page_id, page_access_token);
							showNotification('success', resp.message || 'Unsubscribed successfully.');
						} else {
							showNotification('danger', (resp && resp.message) ? resp.message : 'Unable to unsubscribe this page.');
						}
					},
					error: function(xhr) {
						var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Unable to unsubscribe this page.';
						showNotification('danger', message);
					},
					complete: function() {
						toggleButtonLoading($button, false);
					}
				});
			}

			function copyWebhookUrl() {
				var webhookInput = document.getElementById('facebook-webhook-url');
				if (!webhookInput) {
					return;
				}
				webhookInput.select();
				webhookInput.setSelectionRange(0, 99999);
				try {
					document.execCommand('copy');
					showNotification('success', 'Webhook URL copied to clipboard.');
				} catch (e) {
					showNotification('danger', 'Unable to copy webhook URL. Please copy it manually.');
				}
			}

			window.fbAsyncInit = function() {
				FB.init({
					appId: $('#appId').val(),
					xfbml: true,
					version: 'v5.0'
				});
			};

			(function(d, s, id) {
				var js, fjs = d.getElementsByTagName(s)[0];
				if (d.getElementById(id)) {
					return;
				}
				js = d.createElement(s);
				js.id = id;
				js.src = "//connect.facebook.net/en_US/sdk.js";
				fjs.parentNode.insertBefore(js, fjs);
			}(document, 'script', 'facebook-jssdk'));

			function checkLoginState() {
				FB.getLoginStatus(function(response) {
					console.log('statusChangeCallback');
					console.log(response);
					console.log('successfully logged in', response);
				});

				FB.login(function(response) {
					if (response.status == 'connected') {
						console.log('access_token', response.authResponse.accessToken);

						$.ajax({
							url: "https://graph.facebook.com/v5.0/oauth/access_token?grant_type=fb_exchange_token&client_id=" + $('#appId').val() + "&client_secret=" + $('#appSecret').val() + "&fb_exchange_token=" + response.authResponse.accessToken,
							success: function(result) {
								console.log('Long token ', result.access_token);
								var token = $('input[name="csrfToken"]').attr('value');
								$.ajax({
									url: 'facebookleadsintegration/saveToken',
									type: 'post',
									data: {
										data: result.access_token,
										CSRF: token
									},
									success: function(response) { //response is value returned from php (for your example it's "bye bye"


									}
								});


							}
						});

						// Logged into your app and Facebook.
						FB.api('me/accounts', function(response) {
							console.log('successfully retrieved pages', response);


							var pages = response.data;
							var token = $('input[name="csrfToken"]').attr('value');
							$.ajax({
								url: 'facebookleadsintegration/getTable',
								type: 'POST',
								data: {
									pages: pages,
									CSRF: token
								},
								success: function(resp) {
									$('#list').html(resp);

									//To get data by using leadgenId


								},
								error: function() {
									console.log('something went wrong');
								}


							});

						});

					} else if (response.status == 'not_authorized') {

					} else {

					}
				}, {
					scope: 'pages_manage_ads,pages_manage_metadata,pages_read_engagement,ads_management,leads_retrieval'
				});

			}
		</script>

		<script>
			function updateValue(id) {
				var token = $('input[name="csrfToken"]').attr('value');
				var view_assigned = $('#view_assigned').val();
				var view_source = $('#view_source').val();
				var view_status = $('#view_status').val();

				$.ajax({
					url: 'facebookleadsintegration/updateFields',
					type: 'post',
					data: {
						id: id,
						view_assigned: view_assigned,
						view_source: view_source,
						view_status: view_status,
						CSRF: token
					},
					success: function(response) { //response is value returned from php (for your example it's "bye bye"


					}
				});

			}
		</script>


		<input type="button" value="<?php echo htmlspecialchars(_l('fetch_facebook_pages')); ?>" onclick="checkLoginState();" class="btn btn-info">

		<div class="row">

			<div class="col-md-12" id="list">

				<?php
				if(get_option('facebook_pages'))
				{
					$pages = json_decode(get_option('facebook_pages')) ;
					$subscribed_pages = json_decode(get_option('subscribed_pages')) ?: [];
					$flag=0;
					$html = '<table class="table table-striped" id="pageTable"><thead><tr><th>' . _l('page_name') . '</th><th>' . _l('action').'</th></tr></thead><tbody>';
					foreach ($pages as $page) {
						if (in_array($page->id . get_option('appId'), $subscribed_pages)) {
							$html .= '<tr> <td>' . $page->name . '</td> <td><input type="button" value="' . _l('fbleadsunsubscribe') . '" id="' . $page->id . '" onclick="unsubscribeApps (' . $page->id . ',\'' . $page->access_token . '\');" class="btn btn-danger"></td> </tr>';
							$flag=1;
						} 
					}
					if($flag==0)
					{
						$html .='<tr><td valign="top" colspan="8" class="dataTables_empty">'._l('no_page_subscribed_yet').'</td></tr>';	
					}
					$html .= '</tbody></table>';
					print_r(($html));
				}else{
					$html = '<table class="table table-striped" id="pageTable"><thead><tr><th>' . _l('page_name') . '</th><th>' . _l('action').'</th></tr></thead><tbody><tr><td valign="top" colspan="8" class="dataTables_empty">'._l('no_page_subscribed_yet').'</td></tr>';
					print_r(($html));
				}
				?>

			</div>
			<div id="leadData">

			</div>
		</div>

	</div>
</div>
