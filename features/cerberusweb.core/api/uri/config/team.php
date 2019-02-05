<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2018, Webgroup Media LLC
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

class PageSection_SetupTeam extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::services()->template();
		$visit = CerberusApplication::getVisit();
		$response = DevblocksPlatform::getHttpResponse();
		
		$visit->set(ChConfigurationPage::ID, 'team');
		
		$stack = $response->path;
		@array_shift($stack); // config
		@array_shift($stack); // team
		@$tab = array_shift($stack);
		$tpl->assign('tab', $tab);
		
		$tpl->display('devblocks:cerberusweb.core::configuration/section/team/index.tpl');
	}
	
	function saveSettingsJsonAction() {
		try {
			$worker = CerberusApplication::getActiveWorker();
			
			if(!$worker || !$worker->is_superuser)
				throw new Exception(DevblocksPlatform::translate('error.core.no_acl.admin'));
			
			//@$mail_default_from_id = DevblocksPlatform::importGPC($_POST['mail_default_from_id'],'integer',0);
			
			//$settings = DevblocksPlatform::services()->pluginSettings();
			//$settings->set('cerberusweb.core',CerberusSettings::MAIL_DEFAULT_FROM_ID, $mail_default_from_id);
			
			echo json_encode(array('status'=>true));
			return;
			
		} catch (Exception $e) {
			echo json_encode(array('status'=>false,'error'=>$e->getMessage()));
			return;
			
		}
	}
	
	function renderTabRolesAction() {
		$tpl = DevblocksPlatform::services()->template();
		
		$defaults = C4_AbstractViewModel::loadFromClass('View_WorkerRole');
		$defaults->id = 'config_roles';
		$defaults->name = DevblocksPlatform::translateCapitalized('common.roles');
		
		if(null != ($view = C4_AbstractViewLoader::getView($defaults->id, $defaults))) {
			$tpl->assign('view', $view);
		}
		
		$tpl->display('devblocks:cerberusweb.core::internal/views/search_and_view.tpl');
	}
	
	function renderTabGroupsAction() {
		$tpl = DevblocksPlatform::services()->template();
		
		$defaults = C4_AbstractViewModel::loadFromClass('View_Group');
		$defaults->id = 'config_groups';
		$defaults->name = DevblocksPlatform::translateCapitalized('common.groups');
		
		if(null != ($view = C4_AbstractViewLoader::getView($defaults->id, $defaults))) {
			$tpl->assign('view', $view);
		}
		
		$tpl->display('devblocks:cerberusweb.core::internal/views/search_and_view.tpl');
	}
	
	function renderTabWorkersAction() {
		$tpl = DevblocksPlatform::services()->template();
		
		$defaults = C4_AbstractViewModel::loadFromClass('View_Worker');
		$defaults->id = 'config_workers';
		$defaults->name = DevblocksPlatform::translateCapitalized('common.workers');
		
		if(null != ($view = C4_AbstractViewLoader::getView($defaults->id, $defaults))) {
			$tpl->assign('view', $view);
		}
		
		$tpl->display('devblocks:cerberusweb.core::internal/views/search_and_view.tpl');
	}
}