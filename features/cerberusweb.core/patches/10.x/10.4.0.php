<?php
$db = DevblocksPlatform::services()->database();
$logger = DevblocksPlatform::services()->log();
$tables = $db->metaTables();

// ===========================================================================
// Resize `explorer_set.hash`

list($columns, ) = $db->metaTable('explorer_set');

if(isset($columns['hash']) && 'varchar(40)' != $columns['hash']['type'])
	$db->ExecuteMaster('ALTER TABLE explorer_set MODIFY COLUMN hash varchar(40) not null');

// ===========================================================================
// Add index to `context_to_custom_fieldset`

list($columns, $indexes) = $db->metaTable('context_to_custom_fieldset');

if(!isset($indexes['context_and_id']))
	$db->ExecuteMaster('ALTER TABLE context_to_custom_fieldset ADD INDEX context_and_id (context, context_id)');

// ===========================================================================
// Modify profile_widget.options_kata for conditionality

list($columns, ) = $db->metaTable('profile_widget');

if(!array_key_exists('options_kata', $columns)) {
	$db->ExecuteMaster('ALTER TABLE profile_widget ADD COLUMN options_kata TEXT');
}

// ===========================================================================
// Update built-in automations

$automation_files = [
	'ai.cerb.automationBuilder.autocomplete.d3Format.json',
	'ai.cerb.automationBuilder.autocomplete.d3TimeFormat.json',
	'cerb.editor.toolbar.indentSelection.json',
	'cerb.ticket.assign.json',
	'cerb.ticket.move.json',
	'cerb.ticket.status.json',
];

foreach($automation_files as $automation_file) {
	$path = realpath(APP_PATH . '/features/cerberusweb.core/assets/automations/') . '/' . $automation_file;
	
	if(!file_exists($path) || false === ($automation_data = json_decode(file_get_contents($path), true)))
		continue;
	
	DAO_Automation::importFromJson($automation_data);
	
	unset($automation_data);
}

// ===========================================================================
// Add new toolbars

if(!$db->GetOneMaster("SELECT 1 FROM toolbar WHERE name = 'comment.editor'")) {
	$db->ExecuteMaster(sprintf('INSERT IGNORE INTO toolbar (name, extension_id, description, toolbar_kata, created_at, updated_at) VALUES (%s,%s,%s,%s,%d,%d)',
		$db->qstr('comment.editor'),
		$db->qstr('cerb.toolbar.comment.editor'),
		$db->qstr('Editing a comment'),
		$db->qstr(''),
		time(),
		time()
	));
}

if(!$db->GetOneMaster("SELECT 1 FROM toolbar WHERE name = 'records.worklist'")) {
	$db->ExecuteMaster(sprintf('INSERT IGNORE INTO toolbar (name, extension_id, description, toolbar_kata, created_at, updated_at) VALUES (%s,%s,%s,%s,%d,%d)',
		$db->qstr('records.worklist'),
		$db->qstr('cerb.toolbar.records.worklist'),
		$db->qstr('Viewing a worklist of records'),
		$db->qstr(''),
		time(),
		time()
	));
}

// ===========================================================================
// Update the task status widget to new endpoints

$json = <<< 'EOD'
{"data_query":"type:worklist.records\r\nof:task\r\nquery:(\r\n  id:{{record_id}}\r\n  limit:1\r\n  sort:[id]\r\n)\r\nformat:dictionaries","cache_secs":"","placeholder_simulator_kata":"#record_id: 435","sheet_kata":"layout:\r\n  style: fieldsets\r\n  headings@bool: no\r\n  paging@bool: no\r\n  colors:\r\n    statuses@csv: #66aa57, #636363, #5585cc, #d3352a\r\n\r\ncolumns:\r\n  text\/status:\r\n    params:\r\n      bold@bool: yes\r\n      text_size: 200%\r\n      value_template@raw:\r\n        {{status|capitalize}}\r\n        {% if reopen and status in ['waiting','closed'] %}\r\n        (<abbr title=\"{{reopen|date('r')}}\">{{reopen|date_pretty}}<\/abbr>)\r\n        {% endif %}        \r\n      text_color@raw: statuses:{{status_id}}\r\n  text\/due:\r\n    params:\r\n      value_template@raw:\r\n        {% if due %}\r\n        (due <abbr title=\"{{due|date('r')}}\">{{due|date_pretty}}<\/abbr>)\r\n        {% endif %}","toolbar_kata":""}
EOD;

$sql = sprintf("UPDATE profile_widget SET extension_id = 'cerb.profile.tab.widget.sheet', extension_params_json = %s WHERE extension_id = 'cerb.profile.tab.widget.html' AND name = 'Status' AND profile_tab_id IN (SELECT id FROM profile_tab WHERE context = 'cerberusweb.contexts.task' AND name = 'Overview')",
	$db->qstr($json)
);
$db->ExecuteMaster($sql);

// ===========================================================================
// Update the ticket status widget to new endpoints

$json = <<< 'EOD'
{"data_query":"type:worklist.records\r\nof:ticket\r\nquery:(\r\n  id:{{record_id}}\r\n  limit:1\r\n  sort:[id]\r\n)\r\nformat:dictionaries","cache_secs":"","placeholder_simulator_kata":"","sheet_kata":"layout:\r\n  style: fieldsets\r\n  headings@bool: no\r\n  paging@bool: no\r\n  title_column: image\r\n  colors:\r\n    labels@csv: #898989, #646464\r\n    labels_dark@csv: #898989, #cccccc\r\n    statuses@csv: #66aa57, #5585cc, #636363, #d3352a\r\n\r\ncolumns:\r\n  icon\/image:\r\n    params:\r\n      record_uri@raw: cerb:group:{{group_id}}\r\n      text_size@raw: 400%\r\n  \r\n  card\/group__label:\r\n    params:\r\n      bold@bool: yes\r\n      underline@bool: no\r\n      text_size: 135%\r\n      text_color@raw: labels:0\r\n  \r\n  card\/bucket__label:\r\n    params:\r\n      bold@bool: yes\r\n      underline@bool: no\r\n      text_color@raw: labels:1\r\n      text_size: 200%\r\n  \r\n  text\/status:\r\n    params:\r\n      bold@bool: yes\r\n      text_size: 145%\r\n      value_template@raw:\r\n        {{status|capitalize}}\r\n        {% if reopen_date and status in ['waiting','closed'] %}\r\n        (<abbr title=\"{{reopen_date|date('r')}}\">{{reopen_date|date_pretty}}<\/abbr>)\r\n        {% endif %}        \r\n      text_color@raw: statuses:{{status_id}}\r\n  \r\n  toolbar\/actions:\r\n    label: Actions\r\n    params:\r\n      kata:\r\n        interaction\/reopen:\r\n          uri: cerb:automation:cerb.ticket.status\r\n          hidden@raw,bool: {{not cerb_record_writeable('ticket', id) or 'open' == status}}\r\n          label: Re-open\r\n          icon: upload\r\n          inputs:\r\n            ticket@raw: {{id}}\r\n            #confirm@bool: yes\r\n            status: open\r\n        interaction\/move:\r\n          uri: cerb:automation:cerb.ticket.move\r\n          hidden@raw,bool: {{not cerb_record_writeable('ticket', id)}}\r\n          label: Move\r\n          icon: send\r\n          keyboard: M\r\n          inputs:\r\n            ticket@raw: {{id}}\r\n        interaction\/close:\r\n          uri: cerb:automation:cerb.ticket.status\r\n          hidden@raw,bool:\r\n            {{\r\n              not cerb_record_writeable('ticket', id) \r\n              or status in ['closed','deleted']\r\n            }}\r\n          label: Close\r\n          icon: circle-ok\r\n          keyboard: C\r\n          inputs:\r\n            ticket@raw: {{id}}\r\n            confirm@bool: yes\r\n            status: closed\r\n        interaction\/delete:\r\n          uri: cerb:automation:cerb.ticket.status\r\n          hidden@raw,bool:\r\n            {{\r\n              not cerb_record_writeable('ticket', id) \r\n              or 'deleted' == status \r\n              or not cerb_has_priv('contexts.cerberusweb.contexts.ticket.delete')\r\n            }}\r\n          label: Delete\r\n          icon: circle-remove\r\n          keyboard: X\r\n          inputs:\r\n            ticket@raw: {{id}}\r\n            confirm@bool: yes\r\n            status: deleted","toolbar_kata":""}
EOD;

$sql = sprintf("UPDATE profile_widget SET extension_id = 'cerb.profile.tab.widget.sheet', extension_params_json = %s WHERE extension_id = 'cerb.profile.tab.widget.html' AND name = 'Status' AND profile_tab_id IN (SELECT id FROM profile_tab WHERE context = 'cerberusweb.contexts.ticket' AND name = 'Overview')",
	$db->qstr($json)
);
$db->ExecuteMaster($sql);

// ===========================================================================
// Update package library

$packages = [
	'cerb_profile_tab_ticket_overview.json',
	'cerb_profile_widget_ticket_status.json',
];

CerberusApplication::packages()->importToLibraryFromFiles($packages, APP_PATH . '/features/cerberusweb.core/packages/library/');

// ===========================================================================
// Finish up

return TRUE;
