<?php
$db = DevblocksPlatform::services()->database();
$logger = DevblocksPlatform::services()->log();
$tables = $db->metaTables();

// ===========================================================================
// Migrate mail filters to Virtual Attendants

if(isset($tables['preparse_rule'])) {

	// Look up custom fields for types
	$sql = "SELECT id, name, context, type FROM custom_field";
	$results = $db->GetArrayMaster($sql);
	$custom_fields = [];
	
	if(!empty($results))
	foreach($results as $result) {
		$custom_fields[$result['id']] = array(
			'label' => $result['name'],
			'context' => $result['context'],
			'type' => $result['type'],
		);
	}

	// Rules
		
	$sql = sprintf("SELECT name, criteria_ser, actions_ser ".
		"FROM preparse_rule ".
		"ORDER BY is_sticky DESC, sticky_order ASC, pos DESC "
	);
	$results = $db->GetArrayMaster($sql);

	if(!empty($results)) {
		// Insert trigger_event
		$db->ExecuteMaster(sprintf("INSERT INTO trigger_event (owner_context, owner_context_id, event_point, title) ".
			"VALUES (%s, %d, %s, %s)",
			$db->qstr('cerberusweb.contexts.app'),
			0,
			$db->qstr('event.mail.received.app'),
			$db->qstr('Delivery Blacklist')
		));
		$trigger_id = $db->LastInsertId();
		
		// Decision: Delivered to inbox?
		$db->ExecuteMaster(sprintf("INSERT INTO decision_node (parent_id, trigger_id, title, params_json, node_type, pos) ".
			"VALUES (%d, %d, %s, %s, %s, %d)",
			0,
			$trigger_id,
			$db->qstr('First match:'),
			$db->qstr(''),
			$db->qstr('switch'),
			0
		));
		$parent_id = $db->LastInsertId();
		
		$parent_filters_node_id = $parent_id;
	}
	
	$outcome_pos = 0;
	
	if(!empty($results) && is_array($results))
	foreach($results as $result) {
		$conditions = array();

		if(false === (@$criterion = unserialize($result['criteria_ser'])))
			continue;
			
		if(!is_array($criterion) || empty($criterion))
			continue;
		
		// Loop through and add outcomes
		
		if(is_array($criterion))
		foreach($criterion as $key => $data) {
			
			switch($key) {
				case 'dayofweek':
					$map = array(
						'sun' => '7',
						'mon' => '1',
						'tue' => '2',
						'wed' => '3',
						'thu' => '4',
						'fri' => '5',
						'sat' => '6',
					);
					$days = array();
					
					if(is_array($data))
					foreach($data as $day => $null) {
						if(isset($map[$day]))
							$days[] = $map[$day];
					}
					
					$condition = array(
						'condition' => '_day_of_week',
						'oper' => 'is',
						'day' => $days,
					);
					
					$conditions[] = $condition;
					break;
					
				case 'timeofday':
					$from = isset($data['from']) ? $data['from'] : null;
					$to = isset($data['to']) ? $data['to'] : null;

					if(is_null($from) || is_null($to))
						break;
					
					if(false === ($from = strtotime($from))
						|| false === ($to = strtotime($to))) {
							break;
						}
						
					$condition = array(
						'condition' => '_time_of_day',
						'oper' => 'between',
						'from' => date('h:ia',$from),
						'to' => date('h:ia', $to),
					);
					
					$conditions[] = $condition;
					break;
					
				case 'type':
					@$val = $data['value'];
					
					if(empty($val))
						break;
					
					$condition = array(
						'condition' => 'is_new',
						'bool' => (0 == strcasecmp($val,'new')) ? 1 : 0,
					);
						
					$conditions[] = $condition;
					break;
					
				case 'attachment':
					@$val = $data['value'];
					
					if(empty($val))
						break;
					
					$condition = array(
						'condition' => 'attachment_name',
						'oper' => 'like',
						'value' => $val,
					);
						
					$conditions[] = $condition;
					break;
					
				case 'body_encoding':
					@$val = $data['value'];
					
					if(empty($val))
						break;
					
					$condition = array(
						'condition' => 'encoding',
						'oper' => 'like',
						'value' => $val,
					);
						
					$conditions[] = $condition;
					break;
					
				case 'body':
					@$val = $data['value'];
					
					if(empty($val))
						break;
					
					$condition = array(
						'condition' => 'body',
						'oper' => 'regexp',
						'value' => $val,
					);
						
					$conditions[] = $condition;
					break;
					
				default:
					// Headers
					if('header' == substr($key,0,6)) {
						@$header = $data['header'];
						@$val = $data['value'];
						
						$condition = array(
							'condition' => 'header',
							'header' => $header,
							'oper' => 'like',
							'value' => $val,
						);
							
						$conditions[] = $condition;
						break;
					}
					
					// Custom fields
					if('cf_' != substr($key,0,3))
						break;

					$cfield_id = substr($key,3);
					
					if(!isset($custom_fields[$cfield_id]))
						break;
						
					$cfield = $custom_fields[$cfield_id];
					$cfield_prefix = '';
					$condition = null;
						
					switch($cfield['context']) {
						case 'cerberusweb.contexts.address':
							$cfield_prefix = 'sender_custom_';
							break;
						case 'cerberusweb.contexts.org':
							$cfield_prefix = 'sender_org_custom_';
							break;
					}
					
					$condition_key = $cfield_prefix.$cfield_id;
					
					switch($cfield['type']) {
						case 'C': // Checkbox
							$condition = array(
								'condition' => $condition_key,
								'bool' => !empty($data['value']) ? 1 : 0,
							);
							break;
						case 'S': // Single text
						case 'T': // Multi text
						case 'U': // URL
							$oper = ('!=' == @$data['oper']) ? '!like' : 'like';
							$condition = array(
								'condition' => $condition_key,
								'oper' => $oper,
								'value' => $data['value'],
							);
							break;
						case 'D': // Dropdown
						case 'X': // Multi-Check
							$values = is_array($data['value']) ? array_values($data['value']) : array();
							$condition = array(
								'condition' => $condition_key,
								'oper' => 'in',
								'values' => $values,
							);
							break;
						case 'N': // Number
							$oper = null;
							switch(@$data['oper']) {
								case '=':
									$oper = 'is';
									break;
								case '!=':
									$oper = '!is';
									break;
								case '<':
									$oper = 'lt';
									break;
								case '>':
									$oper = 'gt';
									break;
							}
							
							$condition = array(
								'condition' => $condition_key,
								'oper' => $oper,
								'value' => $data['value'],
							);
							break;
						case 'E': // Date
							@$from = $data['from'];
							@$to = $data['to'];
							
							$condition = array(
								'condition' => $condition_key,
								'oper' => 'is',
								'from' => $from,
								'to' => $to,
							);
							break;
						case 'W': // Worker
							$values = is_array($data['value']) ? array_values($data['value']) : array();
							$condition = array(
								'condition' => $condition_key,
								'oper' => 'in',
								'worker_id' => $values,
							);
							break;
						default:
							break;
					}

					if(!empty($condition))
						$conditions[] = $condition;
					
					break;
			}
			
		} // end criterion
		
		$parent_id = $parent_filters_node_id;
		
		$groups = array();
		
		// Nest decision if multiple recipients
		if(isset($criterion['tocc'])) {
			$data = $criterion['tocc'];
			@$val = $data['value'];
			
			if(!empty($val)) {
				$vals = DevblocksPlatform::parseCsvString($val);
				$conds = array();
				
				foreach($vals as $email) {
					//$email = trim($email, '*'); // strip leading or trailing wild
					
					$conds[] = array(
						'condition' => 'recipients',
						'oper' => 'contains',
						'value' => $email,
					);
				}
				
				if(!empty($conds)) {
					$groups[] = array(
						'any' => 1,
						'conditions' => $conds,
					);
				}
			}
		} // end recipients nest check
		
		// Nest decision if multiple senders
		if(isset($criterion['from'])) {
			$data = $criterion['from'];
			@$val = $data['value'];
			
			if(!empty($val)) {
				$vals = DevblocksPlatform::parseCsvString($val);
				$conds = array();
				
				foreach($vals as $email) {
					//$email = trim($email, '*'); // strip leading or trailing wild
					
					$conds[] = array(
						'condition' => 'sender_address',
						'oper' => 'like',
						'value' => $email,
					);
				}
				
				if(!empty($conds)) {
					$groups[] = array(
						'any' => 1,
						'conditions' => $conds,
					);
				}
			}
		} // end sender nest check
		
		if(!empty($conditions))
			$groups[] = array(
				'any' => 0,
				'conditions' => $conditions,
			);

		if(!empty($groups)) {
			// Outcome: Rule
			$db->ExecuteMaster(sprintf("INSERT INTO decision_node (parent_id, trigger_id, title, params_json, node_type, pos) ".
				"VALUES (%d, %d, %s, %s, %s, %d)",
				$parent_id,
				$trigger_id,
				$db->qstr($result['name']),
				$db->qstr(json_encode(array(
					'groups' => $groups
				))),
				$db->qstr('outcome'),
				$outcome_pos++
			));
			$parent_id = $db->LastInsertId();
		}
		
		$do = array();
		$action_pos = 0;
		
		if(false !== ($actions = unserialize($result['actions_ser']))) {
			$action_labels = array();
			
			if(is_array($actions))
			foreach($actions as $key => $data) {
				switch($key) {
					case 'blackhole':
						$action_labels[] = 'reject';
						
						$do[] = array(
							'action' => 'reject',
						);
						break;
						
					case 'redirect':
						@$to = $data['to'];

						if(empty($to))
							break;
						
						$action_labels[] = 'reject';
						$action_labels[] = 'redirect to ' . $to;
						
						$do[] = array(
							'action' => 'reject',
						);
						$do[] = array(
							'action' => 'redirect_email',
							'to' => $to,
						);
						break;
						
					case 'bounce':
						@$body = $data['message'];
						
						if(empty($body))
							break;
							
						$action_labels[] = 'reject';
						$action_labels[] = 'reply to sender';
						
						$do[] = array(
							'action' => 'reject',
						);
						$do[] = array(
							'action' => 'send_email_sender',
							'subject' => 'Undeliverable message: {{subject}}',
							'content' => $body,
						);
						break;
				}
			}
			
		} // finish action nodes

		if(!empty($do)) {
			$label = 'Perform actions';
			
			if(!empty($action_labels))
				$label = mb_ucfirst(implode(', ', $action_labels));
			
			// Actions: Perform these actions
			$db->ExecuteMaster(sprintf("INSERT INTO decision_node (parent_id, trigger_id, title, params_json, node_type, pos) ".
				"VALUES (%d, %d, %s, %s, %s, %d)",
				$parent_id,
				$trigger_id,
				$db->qstr($label),
				$db->qstr(json_encode(
					array(
						'actions' => $do,
					)
				)),
				$db->qstr('action'),
				$action_pos++
			));
			$db->LastInsertId();
			
		}
	}
}

// ===========================================================================
// Drop mail filters (replaced by Virtual Attendants)

if(isset($tables['preparse_rule'])) {
	$db->ExecuteMaster('DROP TABLE IF EXISTS preparse_rule');
}

// ===========================================================================
// Add 'queue_delivery_date' to 'mail_queue'

if(!isset($tables['mail_queue'])) {
	$logger->error("The 'mail_queue' table is required");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('mail_queue');

if(!isset($columns['queue_delivery_date'])) {
	$db->ExecuteMaster("ALTER TABLE mail_queue ADD COLUMN queue_delivery_date INT UNSIGNED NOT NULL DEFAULT 0, ADD INDEX queue_delivery_date (queue_delivery_date)");
}

if(isset($columns['queue_priority'])) {
	$db->ExecuteMaster("ALTER TABLE mail_queue DROP COLUMN queue_priority");
	unset($columns['queue_priority']);
}

// ===========================================================================
// Add the 'context_scheduled_behavior' table

if(!isset($tables['context_scheduled_behavior'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS `context_scheduled_behavior` (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			context VARCHAR(255) NOT NULL DEFAULT '',
			context_id INT UNSIGNED NOT NULL DEFAULT 0,
			behavior_id INT UNSIGNED NOT NULL DEFAULT 0,
			run_date INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			INDEX context (context),
			INDEX behavior_id (behavior_id),
			INDEX run_date (run_date)
		) ENGINE=%s;
		", APP_DB_ENGINE);
	$db->ExecuteMaster($sql);
	
	$tables['context_scheduled_behavior'] = 'context_scheduled_behavior';
}

// ===========================================================================
// Enable Virtual Attendant scheduled behavior cronjob

if(null != ($cron = DevblocksPlatform::getExtension('cron.virtual_attendant.scheduled_behavior', true, true))) {
	$cron->setParam(CerberusCronPageExtension::PARAM_ENABLED, true);
	$cron->setParam(CerberusCronPageExtension::PARAM_DURATION, '1');
	$cron->setParam(CerberusCronPageExtension::PARAM_TERM, 'm');
	$cron->setParam(CerberusCronPageExtension::PARAM_LASTRUN, strtotime('Yesterday 23:00'));
}

// ===========================================================================
// Refactor 'notification'

if(!isset($tables['notification'])) {
	$logger->error("The 'notification' table is required");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('notification');

if(!isset($columns['context'])) {
	$db->ExecuteMaster("ALTER TABLE notification ADD COLUMN context VARCHAR(255) NOT NULL DEFAULT '', ADD INDEX context (context)");
}

if(!isset($columns['context_id'])) {
	$db->ExecuteMaster("ALTER TABLE notification ADD COLUMN context_id INT UNSIGNED NOT NULL DEFAULT 0, ADD INDEX context_id (context_id)");
	
	// Base URL
	$url_writer = DevblocksPlatform::services()->url();
	$base_url = $url_writer->write('', true, false);
	$base_url_noprotocol = preg_replace('#^(http|https)://#','',$base_url);
	
	// Convert existing notifications to contexts
	$rs = $db->ExecuteMaster(sprintf("SELECT id, url FROM notification WHERE url REGEXP '^(http|https)://%s';",
		$base_url_noprotocol
	));
	
	if($rs instanceof mysqli_result)
	while($row = mysqli_fetch_array($rs)) {
		$url = preg_replace(sprintf("#^(http|https)://%s#", $base_url_noprotocol),'',$row['url']);
		$stack = explode('/', $url);
		
		$context = null;
		$context_id = 0;
		
		switch(@array_shift($stack)) {
			case 'display':
				@$id = array_shift($stack);
				if(is_numeric($id)) {
					$context = 'cerberusweb.contexts.ticket';
					$context_id = $id;
				} else {
					$mask = $id;
					// Lookup mask
					if(null != ($id = $db->GetOneMaster(sprintf("SELECT id FROM ticket WHERE mask=%s", $db->qstr($mask))))) {
						$context = 'cerberusweb.contexts.ticket';
						$context_id = $id;
					} else {
						if(null != ($id = $db->GetOneMaster(sprintf("SELECT new_ticket_id FROM ticket_mask_forward WHERE old_mask=%s", $db->qstr($mask))))) {
							$context = 'cerberusweb.contexts.ticket';
							$context_id = $id;
						} else {
							// Delete if we can't find it (points to something that moved or was deleted)
							$db->ExecuteMaster(sprintf("DELETE FROM notification WHERE id = %d", $row['id']));
						}
					}
				}
				break;
				
			case 'tasks':
				switch(@array_shift($stack)) {
					case 'display':
						@$id = intval(array_shift($stack));
						if(!empty($id)) {
							$context = 'cerberusweb.contexts.task';
							$context_id = $id;
						}
						break;
				}
				break;
		}
		
		if(!empty($context) || !empty($context_id)) {
			$db->ExecuteMaster(sprintf("UPDATE notification SET context=%s, context_id=%d, url='' WHERE id=%d",
				$db->qstr($context),
				$context_id,
				$row['id']
			));
		}
	}
}

return TRUE;
