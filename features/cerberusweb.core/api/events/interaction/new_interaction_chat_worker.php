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

class Event_NewInteractionChatWorker extends Extension_DevblocksEvent {
	const ID = 'event.interaction.chat.worker';
	
	/**
	 *
	 * @param Model_TriggerEvent $trigger
	 * @return Model_DevblocksEvent
	 */
	function generateSampleEventModel(Model_TriggerEvent $trigger) {
		$actions = [];
		$active_worker = CerberusApplication::getActiveWorker();
		
		return new Model_DevblocksEvent(
			self::ID,
			array(
				'worker' => $active_worker,
				'actions' => &$actions,
				
				'bot_name' => 'Cerb',
				'bot_image' => null,
				'behavior_id' => 0,
				'behavior_has_parent' => false,
				'interaction' => null,
				'interaction_params' => [],
				'client_browser' => null,
				'client_browser_version' => null,
				'client_ip' => null,
				'client_platform' => null,
				'client_url' => null,
			)
		);
	}
	
	function setEvent(Model_DevblocksEvent $event_model=null, Model_TriggerEvent $trigger=null) {
		$labels = array();
		$values = array();
		
		/**
		 * Behavior
		 */
		
		$merge_labels = array();
		$merge_values = array();
		CerberusContexts::getContext(CerberusContexts::CONTEXT_BEHAVIOR, $trigger, $merge_labels, $merge_values, null, true);

			// Merge
			CerberusContexts::merge(
				'behavior_',
				'',
				$merge_labels,
				$merge_values,
				$labels,
				$values
			);
		
		// Worker
		$worker = $event_model->params['worker'] ?? null;
		$merge_labels = array();
		$merge_values = array();
		CerberusContexts::getContext(CerberusContexts::CONTEXT_WORKER, $worker, $merge_labels, $merge_values, null, true);

			// Merge
			CerberusContexts::merge(
				'worker_',
				'',
				$merge_labels,
				$merge_values,
				$labels,
				$values
			);
		
		// Interaction
		$interaction = $event_model->params['interaction'] ?? null;
		$labels['interaction'] = 'Interaction';
		$values['interaction'] = $interaction;
		
		// Interaction Parameters
		$interaction_params = $event_model->params['interaction_params'] ?? null;
		$labels['interaction_params'] = 'Interaction Params';
		$values['interaction_params'] = $interaction_params;
		
		// Client
		$client_browser = $event_model->params['client_browser'] ?? null;
		$client_browser_version = $event_model->params['client_browser_version'] ?? null;
		$client_ip = $event_model->params['client_ip'] ?? null;
		$client_platform = $event_model->params['client_platform'] ?? null;
		$client_time = $event_model->params['client_time'] ?? null;
		$client_url = $event_model->params['client_url'] ?? null;
		
		$labels['client_browser'] = 'Client Browser';
		$labels['client_browser_version'] = 'Client Browser Version';
		$labels['client_ip'] = 'Client IP';
		$labels['client_platform'] = 'Client Platform';
		$labels['client_time'] = 'Client Time';
		$labels['client_url'] = 'Client URL';
		
		$values['client_browser'] = $client_browser;
		$values['client_browser_version'] = $client_browser_version;
		$values['client_ip'] = $client_ip;
		$values['client_platform'] = $client_platform;
		$values['client_time'] = $client_time;
		$values['client_url'] = $client_url;
		
		// Actions
		if($event_model && array_key_exists('actions', $event_model->params)) {
			$values['_actions'] =& $event_model->params['actions'];
		} else {
			$values['_actions'] = [];
		}
		
		/**
		 * Return
		 */

		$this->setLabels($labels);
		$this->setValues($values);
	}
	
	function getValuesContexts($trigger) {
		$vals = array(
			'behavior_id' => array(
				'label' => 'Behavior',
				'context' => CerberusContexts::CONTEXT_BEHAVIOR,
			),
			'behavior_bot_id' => array(
				'label' => 'Bot',
				'context' => CerberusContexts::CONTEXT_BOT,
			),
			'interaction_behavior_id' => array(
				'label' => 'Behavior',
				'context' => CerberusContexts::CONTEXT_BEHAVIOR,
			),
			'worker_id' => array(
				'label' => 'Worker',
				'context' => CerberusContexts::CONTEXT_WORKER,
			),
		);
		
		$vars = parent::getValuesContexts($trigger);
		
		$vals_to_ctx = array_merge($vals, $vars);
		DevblocksPlatform::sortObjects($vals_to_ctx, '[label]');
		
		return $vals_to_ctx;
	}
	
	function getConditionExtensions(Model_TriggerEvent $trigger) {
		$labels = $this->getLabels($trigger);
		$types = $this->getTypes();
		
		$labels['interaction'] = 'Interaction';
		$types['interaction'] = Model_CustomField::TYPE_SINGLE_LINE;
		
		$labels['client_browser'] = 'Client Browser';
		$labels['client_browser_version'] = 'Client Browser Version';
		$labels['client_ip'] = 'Client IP';
		$labels['client_platform'] = 'Client Platform';
		$labels['client_time'] = 'Client Time';
		$labels['client_url'] = 'Client URL';

		$types['client_browser'] = Model_CustomField::TYPE_SINGLE_LINE;
		$types['client_browser_version'] = Model_CustomField::TYPE_SINGLE_LINE;
		$types['client_ip'] = Model_CustomField::TYPE_SINGLE_LINE;
		$types['client_platform'] = Model_CustomField::TYPE_SINGLE_LINE;
		$types['client_time'] = Model_CustomField::TYPE_SINGLE_LINE;
		$types['client_url'] = Model_CustomField::TYPE_SINGLE_LINE;

		$conditions = $this->_importLabelsTypesAsConditions($labels, $types);
		
		return $conditions;
	}
	
	function renderConditionExtension($token, $as_token, $trigger, $params=array(), $seq=null) {
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('params', $params);

		if(!is_null($seq))
			$tpl->assign('namePrefix','condition'.$seq);
		
		switch($as_token) {
		}

		$tpl->clearAssign('namePrefix');
		$tpl->clearAssign('params');
	}
	
	function runConditionExtension($token, $as_token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		$pass = true;
		
		switch($as_token) {
			default:
				$pass = false;
				break;
		}
		
		return $pass;
	}
	
	function getActionExtensions(Model_TriggerEvent $trigger) {
		$actions =
			[
				'set_bot_name' => [
					'label' => 'Set bot name',
					'notes' => '',
					'params' => [
						'name' => [
							'type' => 'text',
							'required' => true,
							'notes' => 'The new displayed name of the [bot](/docs/bots/)',
						],
					],
				],
				'switch_behavior' => [
					'label' => 'Use behavior',
					'notes' => '',
					'params' => [
						'behavior_id' => [
							'type' => 'id',
							'required' => true,
							'notes' => 'The [conversational behavior](/docs/bots/events/event.message.chat.worker/) to start',
						],
						'return' => [
							'type' => 'bit',
							'notes' => 'The current behavior should: `0` (exit), `1` (wait for result)',
						],
						'var' => [
							'type' => 'placeholder',
							'notes' => 'Save the behavior results to this placeholder',
						],
					],
				],
			]
		;
		
		return $actions;
	}
	
	function renderActionExtension($token, $trigger, $params=array(), $seq=null) {
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('params', $params);

		if(!is_null($seq))
			$tpl->assign('namePrefix','action'.$seq);

		$labels = $this->getLabels($trigger);
		$tpl->assign('token_labels', $labels);
			
		switch($token) {
			case 'set_bot_name':
				$tpl->assign('var', 'name');
				$tpl->display('devblocks:cerberusweb.core::internal/decisions/actions/_set_string.tpl');
				break;
			
			case 'switch_behavior':
				$tpl->display('devblocks:cerberusweb.core::events/pm/action_switch_behavior.tpl');
				break;
		}
		
		$tpl->clearAssign('params');
		$tpl->clearAssign('namePrefix');
		$tpl->clearAssign('token_labels');
	}
	
	function simulateActionExtension($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		switch($token) {
			case 'set_bot_name':
				$bot_name = $params['name'] ?? null;
				
				$tpl_builder = DevblocksPlatform::services()->templateBuilder();
				$bot_name = $tpl_builder->build($bot_name, $dict);
				
				$out = sprintf(">>> Setting bot name to: %s\n".
					$bot_name
				);
				break;
			
			case 'switch_behavior':
				@$behavior_id = intval($params['behavior_id']);
				
				$out = sprintf(">>> Using behavior\n".
					"%d\n",
					$behavior_id
				);
				break;
		}
		
		return $out;
	}
	
	function runActionExtension($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		switch($token) {
			case 'set_bot_name':
				$bot_name = $params['name'] ?? null;
				
				$tpl_builder = DevblocksPlatform::services()->templateBuilder();
				$actions =& $dict->_actions;
				
				if(!is_array($actions))
					$actions = [];
				
				$bot_name = $tpl_builder->build($bot_name, $dict);
				
				$actions[] = array(
					'_action' => 'bot.name',
					'_trigger_id' => $trigger->id,
					'name' => $bot_name,
				);
				break;
				
			case 'switch_behavior':
				$tpl_builder = DevblocksPlatform::services()->templateBuilder();
				
				$actions =& $dict->_actions;
				
				if(!is_array($actions))
					$actions = [];
				
				@$behavior_id = intval($params['behavior_id']);
				$var_key = ($params['var'] ?? null) ?: '_behavior';
				
				if(false == ($behavior = DAO_TriggerEvent::get($behavior_id)))
					break;
				
				// Variables as parameters
				
				$vars = array();
				
				if(is_array($params))
				foreach($params as $k => $v) {
					if(DevblocksPlatform::strStartsWith($k, 'var_')) {
						if(!isset($behavior->variables[$k]))
							continue;
						
						try {
							if(is_string($v))
								$v = $tpl_builder->build($v, $dict);
		
							$v = $behavior->formatVariable($behavior->variables[$k], $v, $dict);
							
							$vars[$k] = $v;
							
						} catch(Exception $e) {
							
						}
					}
				}
				
				$actions[] = [
					'_action' => 'behavior.switch',
					'_trigger_id' => $trigger->id,
					'behavior_id' => $behavior_id,
					'behavior_variables' => $vars,
					'var' => $var_key,
				];
				break;
		}
	}
};