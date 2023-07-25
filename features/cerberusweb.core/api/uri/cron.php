<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2019, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerb.ai/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://cerb.ai	    http://webgroup.media
***********************************************************************/

class ChCronController extends DevblocksControllerExtension {
	/*
	 * Request Overload
	 */
	function handleRequest(DevblocksHttpRequest $request) {
		$reload = DevblocksPlatform::importGPC($_REQUEST['reload'] ?? null, 'integer',0);
		$loglevel = DevblocksPlatform::importGPC($_REQUEST['loglevel'] ?? null, 'integer',0);
		
		$logger = DevblocksPlatform::services()->log();
		$translate = DevblocksPlatform::getTranslationService();
		
		$settings = DevblocksPlatform::services()->pluginSettings();
		$authorized_ips_str = $settings->get('cerberusweb.core',CerberusSettings::AUTHORIZED_IPS,CerberusSettingsDefaults::AUTHORIZED_IPS);
		$authorized_ips = DevblocksPlatform::parseCrlfString($authorized_ips_str);
		
		$authorized_ip_defaults = DevblocksPlatform::parseCsvString(AUTHORIZED_IPS_DEFAULTS);
		$authorized_ips = array_merge($authorized_ips, $authorized_ip_defaults);
		
		$is_ignoring_wait = DevblocksPlatform::importGPC($_REQUEST['ignore_wait'] ?? null, 'integer',0);
		$is_ignoring_internal = DevblocksPlatform::importGPC($_REQUEST['ignore_internal'] ?? null, 'integer',0);
		
		if(!DevblocksPlatform::isIpAuthorized(DevblocksPlatform::getClientIp(), $authorized_ips)) {
			echo sprintf($translate->_('cron.ip_unauthorized'), DevblocksPlatform::strEscapeHtml(DevblocksPlatform::getClientIp()));
			DevblocksPlatform::dieWithHttpError(null, 403);
		}
		
		$stack = $request->path;
		
		array_shift($stack); // cron
		$job_ids = array_shift($stack);
		
		@set_time_limit(600); // 10 mins
		
		CerberusContexts::pushActivityDefaultActor(CerberusContexts::CONTEXT_APPLICATION, 0);
		
		$url = DevblocksPlatform::services()->url();
		$time_left = intval(ini_get('max_execution_time')) ?: 86400;
		
		$logger->info(sprintf("[Scheduler] Set Time Limit: %d seconds", $time_left));
		
		if($reload) {
			$reload_url = sprintf("%s?reload=%d&loglevel=%d&ignore_wait=%d&ignore_internal=%d",
				$url->write('c=cron' . ($job_ids ? ("&a=".$job_ids) : "")),
				intval($reload),
				intval($loglevel),
				intval($is_ignoring_wait),
				intval($is_ignoring_internal)
			);
			echo "<HTML>".
			"<HEAD>".
			"<TITLE></TITLE>".
			"<meta http-equiv='Refresh' content='".intval($reload).";".$reload_url."'>".
			"<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>".
			"</HEAD>".
			"<BODY>"; // onload=\"setTimeout(\\\"window.location.replace('".$url->write('c=cron')."')\\\",30);\"
		}

		$cron_manifests = DevblocksPlatform::getExtensions('cerberusweb.cron', true);
		$jobs = new CerbPriorityQueueDesc();
		
		if(empty($job_ids)) { // do everything
			if($is_ignoring_internal) {
				$cron_manifests = array_filter($cron_manifests, function($instance) {
					switch($instance->id) {
						case 'cron.bot.scheduled_behavior':
						case 'cron.heartbeat':
						case 'cron.mail_queue':
						case 'cron.mailbox':
						case 'cron.maint':
						case 'cron.parser':
						case 'cron.reminders':
						case 'cron.search':
						case 'cron.storage':
							return false;
					}
					
					return true;
				});
			}
			
			if(is_array($cron_manifests))
			foreach($cron_manifests as $instance) { /* @var $instance CerberusCronPageExtension */
				if($instance->isReadyToRun($is_ignoring_wait)) {
					$last_ran_at = $instance->getParam(CerberusCronPageExtension::PARAM_LASTRUN, 0);
					$jobs->insert($instance, $last_ran_at);
				}
			}
			
		} else { // do named jobs
			foreach(DevblocksPlatform::parseCsvString($job_ids) as $job_id) {
				if(array_key_exists($job_id, $cron_manifests)) {
					$instance = $cron_manifests[$job_id]; /* @var $instance CerberusCronPageExtension */
					
					if($instance->isReadyToRun($is_ignoring_wait)) {
						$last_ran_at = $instance->getParam(CerberusCronPageExtension::PARAM_LASTRUN, 0);
						$jobs->insert($instance, $last_ran_at);
					}
				}
			}
		}

		if($jobs->count()) {
			$jobs->top();
			
			while($jobs->valid()) {
				$job = $jobs->current();
				
				// Are we out of time?
				if($time_left < 20)
					continue;
				
				$started_at = time();
				
				$job->_run();
				
				// Subtract the time we've used
				$time_left -= (time()-$started_at);
				
				$logger->info(sprintf("[Scheduler] Time Remaining: %d seconds", $time_left));
				
				$jobs->next();
			}
		} elseif($reload) {
			$logger->info(sprintf($translate->_('cron.nothing_to_do'), intval($reload)));
		}
		
		if($reload) {
			echo "</BODY>".
			"</HTML>";
		}
		
		CerberusContexts::popActivityDefaultActor();
		
		exit;
	}
};
