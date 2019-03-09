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

class PageSection_ProfilesDraft extends Extension_PageSection {
	function render() {
		$response = DevblocksPlatform::getHttpResponse();
		$stack = $response->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // draft
		@$context_id = intval(array_shift($stack));
		
		$context = CerberusContexts::CONTEXT_DRAFT;
		
		Page_Profiles::renderProfile($context, $context_id, $stack);
	}
	
	function saveDraft() {
		$active_worker = CerberusApplication::getActiveWorker();
		@$draft_id = DevblocksPlatform::importGPC($_REQUEST['draft_id'],'integer',0);

		@$to = DevblocksPlatform::importGPC($_REQUEST['to'],'string','');
		@$subject = DevblocksPlatform::importGPC($_REQUEST['subject'],'string','');
		@$content = DevblocksPlatform::importGPC($_REQUEST['content'],'string','');

		$params = [];
		
		$hint_to = null;
		$type = null;
			
		if(empty($to) && empty($subject) && empty($content)) {
			return false;
		}
		
		@$type = DevblocksPlatform::importGPC($_REQUEST['type'],'string','compose');

		switch($type) {
			case 'compose':
				foreach($_POST as $k => $v) {
					if(substr($k,0,6) == 'field_')
						continue;
					
					$params[$k] = $v;
				}

				// We don't need these fields
				unset($params['c']);
				unset($params['a']);
				unset($params['view_id']);
				unset($params['draft_id']);
				unset($params['group_or_bucket_id']);
				
				// Custom fields
				
				@$field_ids = DevblocksPlatform::importGPC($_REQUEST['field_ids'],'array',array());
				$field_ids = DevblocksPlatform::sanitizeArray($field_ids, 'integer', array('nonzero','unique'));

				if(!empty($field_ids)) {
					$field_values = DAO_CustomFieldValue::parseFormPost(CerberusContexts::CONTEXT_TICKET, $field_ids);
					
					if(!empty($field_values)) {
						$params['custom_fields'] = DAO_CustomFieldValue::formatFieldValues($field_values);
					}
				}
				
				$type = 'mail.compose';
				$hint_to = $to;
				break;
				
			default:
				return false;
				break;
		}
		
		$fields = array(
			DAO_MailQueue::TYPE => $type,
			DAO_MailQueue::TICKET_ID => 0,
			DAO_MailQueue::WORKER_ID => $active_worker->id,
			DAO_MailQueue::UPDATED => time(),
			DAO_MailQueue::HINT_TO => $hint_to,
			DAO_MailQueue::SUBJECT => $subject,
			DAO_MailQueue::BODY => $content,
			DAO_MailQueue::PARAMS_JSON => json_encode($params),
			DAO_MailQueue::IS_QUEUED => 0,
			DAO_MailQueue::QUEUE_DELIVERY_DATE => time(),
		);
		
		// Make sure the current worker is the draft author
		if(!empty($draft_id)) {
			$draft = DAO_MailQueue::getWhere(sprintf("%s = %d AND %s = %d",
				DAO_MailQueue::ID,
				$draft_id,
				DAO_MailQueue::WORKER_ID,
				$active_worker->id
			));
			
			if(!isset($draft[$draft_id]))
				$draft_id = null;
		}
		
		if(empty($draft_id)) {
			$draft_id = DAO_MailQueue::create($fields);
		} else {
			DAO_MailQueue::update($draft_id, $fields);
		}
		
		// If there are attachments, link them to this draft record
		if(isset($params['file_ids']) && is_array($params['file_ids']))
			DAO_Attachment::setLinks(CerberusContexts::CONTEXT_DRAFT, $draft_id, $params['file_ids']);
		
		return $draft_id;
	}
	
	function saveDraftAction() {
		if(false == ($draft_id = $this->saveDraft())) {
			echo json_encode(array());
			return;
		}
		
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('timestamp', time());
		$html = $tpl->fetch('devblocks:cerberusweb.core::mail/queue/saved.tpl');
		
		echo json_encode(array('draft_id'=>$draft_id, 'html'=>$html));
	}
	
	function deleteDraftAction() {
		@$draft_id = DevblocksPlatform::importGPC($_REQUEST['draft_id'],'integer');
		
		@$active_worker = CerberusApplication::getActiveWorker();
		
		if(!empty($draft_id)
			&& null != ($draft = DAO_MailQueue::get($draft_id))
			&&
				(
					$active_worker->id == $draft->worker_id
					|| $active_worker->hasPriv('contexts.cerberusweb.contexts.draft.delete')
				)
			) {
			DAO_MailQueue::delete($draft_id);
		}
	}
	
	function showDraftsPeekAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer',0);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string','');
		
		@$active_worker = CerberusApplication::getActiveWorker();
		
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('view_id', $view_id);
		
		if(null != ($draft = DAO_MailQueue::get($id)))
			if($active_worker->is_superuser || $draft->worker_id==$active_worker->id)
				$tpl->assign('draft', $draft);
		
		$tpl->display('devblocks:cerberusweb.core::mail/queue/peek.tpl');
	}
	
	function showDraftsBulkPanelAction() {
		@$id_csv = DevblocksPlatform::importGPC($_REQUEST['ids']);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id']);

		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('view_id', $view_id);

		if(!empty($id_csv)) {
			$ids = DevblocksPlatform::parseCsvString($id_csv);
			$tpl->assign('ids', implode(',', $ids));
		}
		
		$tpl->display('devblocks:cerberusweb.core::mail/queue/bulk.tpl');
	}
	
	function startBulkUpdateJsonAction() {
		// Filter: whole list or check
		@$filter = DevblocksPlatform::importGPC($_REQUEST['filter'],'string','');
		$ids = array();
		
		// View
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		$view = C4_AbstractViewLoader::getView($view_id);
		$view->setAutoPersist(false);
		
		// Draft fields
		@$status = trim(DevblocksPlatform::importGPC($_POST['status'],'string'));

		$do = array();
		
		// Do: Status
		if(0 != strlen($status))
			$do['status'] = $status;
			
		switch($filter) {
			// Checked rows
			case 'checks':
				@$ids_str = DevblocksPlatform::importGPC($_REQUEST['ids'],'string');
				$ids = DevblocksPlatform::parseCsvString($ids_str);
				break;
				
			case 'sample':
				@$sample_size = min(DevblocksPlatform::importGPC($_REQUEST['filter_sample_size'],'integer',0),9999);
				$filter = 'checks';
				$ids = $view->getDataSample($sample_size);
				break;
				
			default:
				break;
		}
		
		// If we have specific IDs, add a filter for those too
		if(!empty($ids)) {
			$view->addParams([
				new DevblocksSearchCriteria(SearchFields_MailQueue::ID, 'in', $ids)
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