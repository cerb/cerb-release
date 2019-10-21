<?php
class ChRest_Bots extends Extension_RestController {
	/*
	 * GET /va/list.json
	 * GET /va/123.json
	 */
	function getAction($stack) {
		@$action = array_shift($stack);
		
		switch($action) {
			case 'list':
				$this->_getVaList();
				break;
				
			default:
				if(is_numeric($action))
					$this->_getVa($action);
				break;
		}
		
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	function putAction($stack) {
		//@$action = array_shift($stack);
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	/*
	 * POST /va/behavior/123/run.json
	 */
	function postAction($stack) {
		@$action = array_shift($stack);
		
		switch($action) {
			case 'behavior':
				@$behavior_id = array_shift($stack);
				
				if(is_numeric($behavior_id)) {
					$behavior_id = intval($behavior_id);
					
					@$subaction = array_shift($stack);
					
					switch($subaction) {
						case 'run':
							$this->_postVaBehaviorApiRequest($behavior_id);
							break;
							
						default:
							break;
					}
				}
				
				$this->error(self::ERRNO_NOT_IMPLEMENTED);
				break;
		}
		
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	function deleteAction($stack) {
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	private function _getVaList() {
		$active_worker = CerberusApplication::getActiveWorker();
		
		$vas = DAO_Bot::getReadableByActor($active_worker);
		
		$results = array();
		
		foreach($vas as $va) {
			$labels = array();
			$values = array();
			CerberusContexts::getContext(CerberusContexts::CONTEXT_BOT, $va, $labels, $values, null, true);
			
			$results[] = $values;
		}
		
		$container = array(
			'total' => count($results),
			'count' => count($results),
			'page' => 0,
			'results' => $results,
		);
		
		$this->success($container);
	}

	private function _getVa($id) {
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(false == ($va = DAO_Bot::get($id)))
			$this->error(self::ERRNO_CUSTOM, "Invalid ID.");
		
		if(!Context_Bot::isReadableByActor($va, $active_worker))
			$this->error(self::ERRNO_CUSTOM, "You do not have permission to view this object.");
		
		$labels = array();
		$values = array();
		CerberusContexts::getContext(CerberusContexts::CONTEXT_BOT, $va, $labels, $values, null, true);
		
		$this->success($values);
	}
	
	private function _postVaBehaviorApiRequest($behavior_id) {
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(null == ($behavior = DAO_TriggerEvent::get($behavior_id)))
			$this->error(self::ERRNO_CUSTOM, "The requested behavior doesn't exist.");

		if($behavior->event_point != Event_ApiRequest::ID)
			$this->error(self::ERRNO_CUSTOM, "The requested behavior is not a custom API request.");
		
		// Check permissions
		if(false == ($bot = $behavior->getBot()))
			$this->error(self::ERRNO_CUSTOM, "Invalid bot.");

		if(!Context_Bot::isReadableByActor($bot, $active_worker))
			$this->error(self::ERRNO_ACL);
		
		if($bot->is_disabled)
			$this->error(self::ERRNO_CUSTOM, "Bot is disabled.");
		
		if($behavior->is_disabled)
			$this->error(self::ERRNO_CUSTOM, "Bot behavior is disabled.");
		
		// Vars

		$vars = array();
		
		if(is_array($behavior->variables)) {
			foreach($behavior->variables as $var_key => $var) {
				if(!empty($var['is_private']))
					continue;
				
				// Complain if we're not given all the public vars
				// [TODO] This will fail when a checkbox isn't ticked, or a list variable has no items
				
				if(!isset($_REQUEST[$var_key]))
					$this->error(self::ERRNO_CUSTOM, sprintf("The public variable '%s' is required.", $var_key));
				
				// Format passed variables
				
				$var_val = null;
				
				try {
					$var_val = $behavior->formatVariable($var, DevblocksPlatform::importGPC($_REQUEST[$var_key]));
					$vars[$var_key] = $var_val;
					
				} catch(Exception $e) {
					if(!isset($_REQUEST[$var_key]))
						$this->error(self::ERRNO_CUSTOM, $e->getMessage());
					
				}
				
			}
		}
		
		// Load event manifest
		if(null == ($ext = Extension_DevblocksEvent::get($behavior->event_point, false))) /* @var $ext DevblocksExtensionManifest */
			$this->error(self::ERRNO_CUSTOM);
			
		if(!method_exists($ext->class, 'trigger'))
			$this->error(self::ERRNO_CUSTOM, "This behavior type cannot be triggered.");
		
		// Trigger a custom API request
		$runners = call_user_func([$ext->class, 'trigger'], $behavior->id, $vars);
		
		$values = [];
		
		if(null != (@$runner = $runners[$behavior->id])) {
			// Return the whole scope of the behavior to the caller
			$values = $runner->getDictionary();
			//@$message = $runner->_output ?: ''; /* @var $runner DevblocksDictionaryDelegate */
		}
		
		$this->success($values);
	}
};