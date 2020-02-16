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

class PageSection_ProfilesOrganization extends Extension_PageSection {
	function render() {
		$response = DevblocksPlatform::getHttpResponse();
		$stack = $response->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // org
		@$context_id = intval(array_shift($stack));
		
		$context = CerberusContexts::CONTEXT_ORG;
		
		Page_Profiles::renderProfile($context, $context_id, $stack);
	}
	
	function savePeekPopupJsonAction() {
		@$id = DevblocksPlatform::importGPC($_POST['id'],'integer', 0);
		@$view_id = DevblocksPlatform::importGPC($_POST['view_id'],'string','');
		@$delete = DevblocksPlatform::importGPC($_POST['do_delete'],'integer',0);
		
		$active_worker = CerberusApplication::getActiveWorker();

		header('Content-Type: application/json; charset=utf-8');
		
		try {
			if(!empty($id) && !empty($delete)) { // delete
				if(!$active_worker->hasPriv(sprintf("contexts.%s.delete", CerberusContexts::CONTEXT_ORG)))
					throw new Exception_DevblocksAjaxValidationError(DevblocksPlatform::translate('error.core.no_acl.delete'));
				
				DAO_ContactOrg::delete($id);
				
				echo json_encode(array(
					'status' => true,
					'id' => $id,
					'view_id' => $view_id,
				));
				return;
				
			} else { // create/edit
				@$org_name = DevblocksPlatform::importGPC($_POST['org_name'],'string','');
				@$aliases = DevblocksPlatform::importGPC($_POST['aliases'],'string','');
				@$street = DevblocksPlatform::importGPC($_POST['street'],'string','');
				@$city = DevblocksPlatform::importGPC($_POST['city'],'string','');
				@$province = DevblocksPlatform::importGPC($_POST['province'],'string','');
				@$postal = DevblocksPlatform::importGPC($_POST['postal'],'string','');
				@$country = DevblocksPlatform::importGPC($_POST['country'],'string','');
				@$phone = DevblocksPlatform::importGPC($_POST['phone'],'string','');
				@$website = DevblocksPlatform::importGPC($_POST['website'],'string','');
				@$email_id = DevblocksPlatform::importGPC($_POST['email_id'],'integer',0);
				
				$error = null;
				
				// Privs
				$fields = array(
					DAO_ContactOrg::NAME => $org_name,
					DAO_ContactOrg::STREET => $street,
					DAO_ContactOrg::CITY => $city,
					DAO_ContactOrg::PROVINCE => $province,
					DAO_ContactOrg::POSTAL => $postal,
					DAO_ContactOrg::COUNTRY => $country,
					DAO_ContactOrg::PHONE => $phone,
					DAO_ContactOrg::WEBSITE => $website,
					DAO_ContactOrg::EMAIL_ID => $email_id,
				);
		
				if($id==0) {
					if(!DAO_ContactOrg::validate($fields, $error))
						throw new Exception_DevblocksAjaxValidationError($error);
					
					if(!DAO_ContactOrg::onBeforeUpdateByActor($active_worker, $fields, null, $error))
						throw new Exception_DevblocksAjaxValidationError($error);
					
					if(false == ($id = DAO_ContactOrg::create($fields)))
						throw new Exception_DevblocksAjaxValidationError("Failed to create a new record.");
					
					DAO_ContactOrg::onUpdateByActor($active_worker, $fields, $id);
					
					// View marquee
					if(!empty($id) && !empty($view_id)) {
						C4_AbstractView::setMarqueeContextCreated($view_id, CerberusContexts::CONTEXT_ORG, $id);
					}
					
				}
				else {
					if(!DAO_ContactOrg::validate($fields, $error, $id))
						throw new Exception_DevblocksAjaxValidationError($error);
					
					if(!DAO_ContactOrg::onBeforeUpdateByActor($active_worker, $fields, $id, $error))
						throw new Exception_DevblocksAjaxValidationError($error);
					
					DAO_ContactOrg::update($id, $fields);
					DAO_ContactOrg::onUpdateByActor($active_worker, $fields, $id);
				}
				
				if($id) {
					// Custom field saves
					@$field_ids = DevblocksPlatform::importGPC($_POST['field_ids'], 'array', []);
					if(!DAO_CustomFieldValue::handleFormPost(CerberusContexts::CONTEXT_ORG, $id, $field_ids, $error))
						throw new Exception_DevblocksAjaxValidationError($error);
					
					// Aliases
					DAO_ContextAlias::set(CerberusContexts::CONTEXT_ORG, $id, DevblocksPlatform::parseCrlfString($org_name . "\n" . $aliases));
					
					// Avatar image
					@$avatar_image = DevblocksPlatform::importGPC($_POST['avatar_image'], 'string', '');
					DAO_ContextAvatar::upsertWithImage(CerberusContexts::CONTEXT_ORG, $id, $avatar_image);
					
					// Comments
					DAO_Comment::handleFormPost(CerberusContexts::CONTEXT_ORG, $id);
					
					// Index immediately
					$search = Extension_DevblocksSearchSchema::get(Search_Org::ID);
					$search->indexIds(array($id));
				}
			}
			
			echo json_encode(array(
				'status' => true,
				'id' => $id,
				'label' => $org_name,
				'view_id' => $view_id,
			));
			return;
			
		} catch (Exception_DevblocksAjaxValidationError $e) {
				echo json_encode(array(
					'status' => false,
					'id' => $id,
					'error' => $e->getMessage(),
					'field' => $e->getFieldName(),
				));
				return;
			
		} catch (Exception $e) {
				echo json_encode(array(
					'status' => false,
					'id' => $id,
					'error' => 'An error occurred.',
				));
				return;
			
		}
	}
	
	function showBulkPopupAction() {
		@$ids = DevblocksPlatform::importGPC($_REQUEST['ids']);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id']);

		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('view_id', $view_id);

		if(!empty($ids)) {
			$org_ids = DevblocksPlatform::parseCsvString($ids);
			$tpl->assign('org_ids', implode(',', $org_ids));
		}
		
		// Custom Fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_ORG, false);
		$tpl->assign('custom_fields', $custom_fields);
		
		// Broadcast
		if(false == ($context_ext = Extension_DevblocksContext::get(CerberusContexts::CONTEXT_ORG)))
			return [];
		
		/* @var $context_ext IDevblocksContextBroadcast */
			
		// Recipient fields
		$recipient_fields = $context_ext->broadcastRecipientFieldsGet();
		$tpl->assign('broadcast_recipient_fields', $recipient_fields);
		
		// Placeholders
		$token_values = $context_ext->broadcastPlaceholdersGet();
		@$token_labels = $token_values['_labels'] ?: [];
		
		$placeholders = Extension_DevblocksContext::getPlaceholderTree($token_labels);
		$tpl->assign('placeholders', $placeholders);
		
		$groups = DAO_Group::getAll();
		$tpl->assign('groups', $groups);
		
		$buckets = DAO_Bucket::getAll();
		$tpl->assign('buckets', $buckets);
		
		$html_templates = DAO_MailHtmlTemplate::getAll();
		$tpl->assign('html_templates', $html_templates);
		
		$tpl->display('devblocks:cerberusweb.core::contacts/orgs/bulk.tpl');
	}
	
	function startBulkUpdateJsonAction() {
		$active_worker = CerberusApplication::getActiveWorker();
		
		// Filter: whole list or check
		@$filter = DevblocksPlatform::importGPC($_POST['filter'],'string','');
		$ids = array();
		
		// View
		@$view_id = DevblocksPlatform::importGPC($_POST['view_id'],'string');
		$view = C4_AbstractViewLoader::getView($view_id);
		$view->setAutoPersist(false);
		
		// Org fields
		@$status = DevblocksPlatform::importGPC($_POST['status'],'string','');
		@$country = trim(DevblocksPlatform::importGPC($_POST['country'],'string',''));

		// Scheduled behavior
		@$behavior_id = DevblocksPlatform::importGPC($_POST['behavior_id'],'string','');
		@$behavior_when = DevblocksPlatform::importGPC($_POST['behavior_when'],'string','');
		@$behavior_params = DevblocksPlatform::importGPC($_POST['behavior_params'],'array',array());
		
		$do = array();
		
		// Delete
		if(strlen($status) > 0) {
			switch($status) {
				case 'deleted':
					if($active_worker->hasPriv('contexts.cerberusweb.contexts.org.delete')) {
						$do['delete'] = true;
					}
					break;
			}
		}
		
		// Do: Country
		if(0 != strlen($country))
			$do['country'] = $country;
			
		// Do: Scheduled Behavior
		if(0 != strlen($behavior_id)) {
			$do['behavior'] = array(
				'id' => $behavior_id,
				'when' => $behavior_when,
				'params' => $behavior_params,
			);
		}
		
		// Watchers
		$watcher_params = array();
		
		@$watcher_add_ids = DevblocksPlatform::importGPC($_POST['do_watcher_add_ids'],'array',array());
		if(!empty($watcher_add_ids))
			$watcher_params['add'] = $watcher_add_ids;
			
		@$watcher_remove_ids = DevblocksPlatform::importGPC($_POST['do_watcher_remove_ids'],'array',array());
		if(!empty($watcher_remove_ids))
			$watcher_params['remove'] = $watcher_remove_ids;
		
		if(!empty($watcher_params))
			$do['watchers'] = $watcher_params;
		
		// Do: Custom fields
		$do = DAO_CustomFieldValue::handleBulkPost($do);
		
		// Broadcast: Compose
		if($active_worker->hasPriv('contexts.cerberusweb.contexts.org.broadcast')) {
			@$do_broadcast = DevblocksPlatform::importGPC($_POST['do_broadcast'],'string',null);
			@$broadcast_group_id = DevblocksPlatform::importGPC($_POST['broadcast_group_id'],'integer',0);
			@$broadcast_bucket_id = DevblocksPlatform::importGPC($_POST['broadcast_bucket_id'],'integer',0);
			@$broadcast_to = DevblocksPlatform::importGPC($_POST['broadcast_to'],'array',[]);
			@$broadcast_subject = DevblocksPlatform::importGPC($_POST['broadcast_subject'],'string',null);
			@$broadcast_message = DevblocksPlatform::importGPC($_POST['broadcast_message'],'string',null);
			@$broadcast_format = DevblocksPlatform::importGPC($_POST['broadcast_format'],'string',null);
			@$broadcast_html_template_id = DevblocksPlatform::importGPC($_POST['broadcast_html_template_id'],'integer',0);
			@$broadcast_is_queued = DevblocksPlatform::importGPC($_POST['broadcast_is_queued'],'integer',0);
			@$broadcast_status_id = DevblocksPlatform::importGPC($_POST['broadcast_status_id'],'integer',0);
			@$broadcast_file_ids = DevblocksPlatform::sanitizeArray(DevblocksPlatform::importGPC($_POST['broadcast_file_ids'],'array',array()), 'integer', array('nonzero','unique'));
			
			if(0 != strlen($do_broadcast) && !empty($broadcast_subject) && !empty($broadcast_message)) {
				$do['broadcast'] = [
					'to' => $broadcast_to,
					'subject' => $broadcast_subject,
					'message' => $broadcast_message,
					'format' => $broadcast_format,
					'html_template_id' => $broadcast_html_template_id,
					'is_queued' => $broadcast_is_queued,
					'status_id' => $broadcast_status_id,
					'group_id' => $broadcast_group_id,
					'bucket_id' => $broadcast_bucket_id,
					'worker_id' => $active_worker->id,
					'file_ids' => $broadcast_file_ids,
				];
			}
		}
		
		switch($filter) {
			// Checked rows
			case 'checks':
				@$org_ids_str = DevblocksPlatform::importGPC($_POST['org_ids'],'string');
				$ids = DevblocksPlatform::parseCsvString($org_ids_str);
				break;
			case 'sample':
				@$sample_size = min(DevblocksPlatform::importGPC($_POST['filter_sample_size'],'integer',0),9999);
				$filter = 'checks';
				$ids = $view->getDataSample($sample_size);
				break;
			default:
				break;
		}
		
		// If we have specific IDs, add a filter for those too
		if(!empty($ids)) {
			$view->addParams([
				new DevblocksSearchCriteria(SearchFields_ContactOrg::ID, 'in', $ids)
			], true);
		}
		
		// Create batches
		$batch_key = DAO_ContextBulkUpdate::createFromView($view, $do);
		
		header('Content-Type: application/json; charset=utf-8');
		
		echo json_encode(array(
			'cursor' => $batch_key,
		));
		
		return;
	}
};