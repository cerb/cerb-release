<?php
$db = DevblocksPlatform::services()->database();
$tables = $db->metaTables();

// `attachment` =============================
list($columns, $indexes) = $db->metaTable('attachment');

if(!isset($indexes['message_id'])) {
	$db->ExecuteMaster('ALTER TABLE attachment ADD INDEX message_id (message_id)');
}

// `kb_category` =============================
if(!isset($tables['kb_category'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS kb_category (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			parent_id INT UNSIGNED DEFAULT 0 NOT NULL,
			name VARCHAR(64) DEFAULT '' NOT NULL,
			PRIMARY KEY (id)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->ExecuteMaster($sql);	
    
}

list($columns, $indexes) = $db->metaTable('kb_category');

if(!isset($indexes['parent_id'])) {
    $db->ExecuteMaster('ALTER TABLE kb_category ADD INDEX parent_id (parent_id)');
}

if(isset($columns['id']) 
	&& ('int(10) unsigned' != $columns['id']['type'] 
	|| 'auto_increment' != $columns['id']['extra'])
) {
	$db->ExecuteMaster("ALTER TABLE kb_category MODIFY COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT");
}

// `kb_article_to_category` =============================
if(!isset($tables['kb_article_to_category'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS kb_article_to_category (
			kb_article_id INT UNSIGNED DEFAULT 0 NOT NULL,
			kb_category_id INT UNSIGNED DEFAULT 0 NOT NULL,
			kb_top_category_id INT UNSIGNED DEFAULT 0 NOT NULL,
			PRIMARY KEY (kb_article_id, kb_category_id)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->ExecuteMaster($sql);	
	
	if(!isset($indexes['kb_article_id'])) {
	    $db->ExecuteMaster('ALTER TABLE kb_article_to_category ADD INDEX kb_article_id (kb_article_id)');
	}
	
	if(!isset($indexes['kb_category_id'])) {
	    $db->ExecuteMaster('ALTER TABLE kb_article_to_category ADD INDEX kb_category_id (kb_category_id)');
	}
	
	if(!isset($indexes['kb_top_category_id'])) {
	    $db->ExecuteMaster('ALTER TABLE kb_article_to_category ADD INDEX kb_top_category_id (kb_top_category_id)');
	}
}

// `kb_article` ========================
if(!isset($tables['kb_article'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS kb_article (
			id INT UNSIGNED DEFAULT 0 NOT NULL,
			title VARCHAR(128) DEFAULT '' NOT NULL,
			updated INT UNSIGNED DEFAULT 0 NOT NULL,
			views INT UNSIGNED DEFAULT 0 NOT NULL,
			content MEDIUMTEXT,
			PRIMARY KEY (id)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->ExecuteMaster($sql);	
}

list($columns, $indexes) = $db->metaTable('kb_article');

if(!isset($columns['updated'])) {
	$db->ExecuteMaster('ALTER TABLE kb_article ADD COLUMN updated INT UNSIGNED DEFAULT 0 NOT NULL');
	$db->ExecuteMaster("UPDATE kb_article SET updated = %d", time());
}

if(!isset($columns['views'])) {
	$db->ExecuteMaster('ALTER TABLE kb_article ADD COLUMN views INT UNSIGNED DEFAULT 0 NOT NULL');
}

if(!isset($indexes['updated'])) {
	$db->ExecuteMaster('ALTER TABLE kb_article ADD INDEX updated (updated)');
}

if(!isset($columns['format'])) {
    $db->ExecuteMaster('ALTER TABLE kb_article ADD COLUMN format TINYINT(1) UNSIGNED DEFAULT 0 NOT NULL');
    $db->ExecuteMaster("UPDATE kb_article SET format=1");
}

if(!isset($columns['content_raw'])) {
    $db->ExecuteMaster('ALTER TABLE kb_article ADD COLUMN content_raw MEDIUMTEXT');
    $db->ExecuteMaster("UPDATE kb_article SET content_raw=content");
}

if(isset($columns['code'])) {
	// First translate any existing codes to new KB topics
	$sql = "SELECT DISTINCT code FROM kb_article";
	$rs = $db->ExecuteMaster($sql);
	
	$num = 1;
	
    while($row = mysqli_fetch_assoc($rs)) {
    	$code = $row['code'];

    	if(empty($code))
    		continue;
    	
    	$cat_name = "Imported KB #".$num++;
    	
    	$db->ExecuteMaster(sprintf("INSERT INTO kb_category (parent_id,name) VALUES (0,%s)",
    		$db->qstr($cat_name)
    	));
    	$cat_id = $db->LastInsertId();
    	
    	$rs2 = $db->ExecuteMaster(sprintf("SELECT id FROM kb_article WHERE code = %s",
    		$db->qstr($code)
    	));
    	
    	while($row2 = mysqli_fetch_assoc($rs2)) {
    		$article_id = intval($row2['id']);
    		$db->ExecuteMaster("REPLACE INTO kb_article_to_category (kb_article_id, kb_category_id, kb_top_category_id) ".
    			"VALUES (%d, %d, %d)",
    			$article_id,
    			$cat_id,
    			$cat_id
    		);
    	}
    	
    	mysqli_free_result($rs2);
    }
    
    mysqli_free_result($rs);
    
    unset($num);
	
    $db->ExecuteMaster("ALTER TABLE kb_article DROP COLUMN code");
}

// `message_content` ========================
list($columns, $indexes) = $db->metaTable('message_content');

if(isset($columns['content'])) {
	if(0 != strcasecmp('mediumtext',$columns['content']['type'])) {
		$db->ExecuteMaster("ALTER TABLE message_content CHANGE COLUMN content content MEDIUMTEXT");
	}
}

// `message_header` ========================
list($columns, $indexes) = $db->metaTable('message_header');

// Drop compound primary key
if(isset($columns['message_id']) && isset($columns['header_name'])
	&& 'PRI'==$columns['message_id']['key'] && 'PRI'==$columns['message_id']['key']) {
		$db->ExecuteMaster("ALTER TABLE message_header DROP PRIMARY KEY");
}

if(!isset($indexes['message_id'])) {
	$db->ExecuteMaster('ALTER TABLE message_header ADD INDEX message_id (message_id)');
}

if(!isset($indexes['header_value'])) {
	$db->ExecuteMaster('ALTER TABLE message_header ADD INDEX header_value (header_value(10))');
}

// `message_note` ========================
list($columns, $indexes) = $db->metaTable('message_note');

if(!isset($indexes['message_id'])) {
	$db->ExecuteMaster('ALTER TABLE message_note ADD INDEX message_id (message_id)');
}

// `note` =============================
if(!isset($tables['note'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS note (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_extension_id VARCHAR(128) DEFAULT '' NOT NULL,
			source_id INT UNSIGNED DEFAULT 0 NOT NULL,
			created INT UNSIGNED DEFAULT 0 NOT NULL,
			worker_id INT UNSIGNED DEFAULT 0 NOT NULL,
			content TEXT,
			PRIMARY KEY (id)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->ExecuteMaster($sql);	
}

list($columns, $indexes) = $db->metaTable('note');

if(isset($columns['id']) 
	&& ('int(10) unsigned' != $columns['id']['type'] 
	|| 'auto_increment' != $columns['id']['extra'])
) {
	$db->ExecuteMaster("ALTER TABLE note MODIFY COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT");
}

if(!isset($indexes['source_extension_id'])) {
	$db->ExecuteMaster('ALTER TABLE note ADD INDEX source_extension_id (source_extension_id)');
}

if(!isset($indexes['source_id'])) {
	$db->ExecuteMaster('ALTER TABLE note ADD INDEX source_id (source_id)');
}

if(!isset($indexes['created'])) {
	$db->ExecuteMaster('ALTER TABLE note ADD INDEX created (created)');
}

if(!isset($indexes['worker_id'])) {
	$db->ExecuteMaster('ALTER TABLE note ADD INDEX worker_id (worker_id)');
}

// `preparse_rule` =============================
if(!isset($tables['preparse_rule'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS preparse_rule (
			id INT UNSIGNED DEFAULT 0 NOT NULL,
			name VARCHAR(64) DEFAULT '' NOT NULL,
			criteria_ser MEDIUMTEXT,
			actions_ser MEDIUMTEXT,
			pos INT UNSIGNED DEFAULT 0 NOT NULL,
			PRIMARY KEY (id),
			INDEX pos (pos)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->ExecuteMaster($sql);	
}

// `team_routing_rule` ========================
list($columns, $indexes) = $db->metaTable('team_routing_rule');

if(!isset($columns['name'])) {
    $db->ExecuteMaster("ALTER TABLE team_routing_rule ADD COLUMN name VARCHAR(64) DEFAULT '' NOT NULL");
    $db->ExecuteMaster("UPDATE team_routing_rule SET name='Rule' WHERE name=''");
}

if(!isset($columns['criteria_ser'])) {
    $db->ExecuteMaster('ALTER TABLE team_routing_rule ADD COLUMN criteria_ser MEDIUMTEXT');
}

//if(!isset($columns['actions_ser'])) {
//    $db->ExecuteMaster('ALTER TABLE team_routing_rule ADD COLUMN actions_ser MEDIUMTEXT');
//}

// Convert old header+patterns to criteria
if(isset($columns['header']) && isset($columns['pattern'])) {
	$sql = "SELECT id,header,pattern FROM team_routing_rule";
	$rs = $db->ExecuteMaster($sql);
	
	while($row = mysqli_fetch_assoc($rs)) {
		@$id = intval($row['id']);
		@$header = mb_convert_case($row['header'], MB_CASE_LOWER);
		$pattern = $row['pattern'] ?? null;
		$criterion = array();
		
		if(empty($header) || empty($pattern)) {
			continue;
		}
		
		if($header != 'from' && $header != 'subject') {
			continue;
		}
			
		$criterion[$header] = array(
			'value' => $pattern
		);

		// Fill criteria_ser
		$sql = sprintf("UPDATE team_routing_rule SET criteria_ser = %s WHERE id = %d",
			$db->qstr(serialize($criterion)),
			$id
		);
		$db->ExecuteMaster($sql);
	}
	
	mysqli_free_result($rs);

	// Drop columns
	$db->ExecuteMaster('ALTER TABLE team_routing_rule DROP COLUMN header');
	$db->ExecuteMaster('ALTER TABLE team_routing_rule DROP COLUMN pattern');
}

// `ticket` ========================
list($columns, $indexes) = $db->metaTable('ticket');

if(!isset($columns['unlock_date'])) {
	$db->ExecuteMaster('ALTER TABLE ticket ADD COLUMN unlock_date INT UNSIGNED DEFAULT 0 NOT NULL');
}

if(!isset($indexes['unlock_date'])) {
	$db->ExecuteMaster('ALTER TABLE ticket ADD INDEX unlock_date (unlock_date)');
}

if(!isset($indexes['due_date'])) {
	$db->ExecuteMaster('ALTER TABLE ticket ADD INDEX due_date (due_date)');
}

if(!isset($indexes['is_deleted'])) {
	$db->ExecuteMaster('ALTER TABLE ticket ADD INDEX is_deleted (is_deleted)');
}

if(!isset($indexes['last_action_code'])) {
	$db->ExecuteMaster('ALTER TABLE ticket ADD INDEX last_action_code (last_action_code)');
}

if(!isset($indexes['spam_score'])) {
	$db->ExecuteMaster('ALTER TABLE ticket ADD INDEX spam_score (spam_score)');
}

// `ticket_comment` ========================
list($columns, $indexes) = $db->metaTable('ticket_comment');

if(!isset($indexes['ticket_id'])) {
	$db->ExecuteMaster('ALTER TABLE ticket_comment ADD INDEX ticket_id (ticket_id)');
}

if(!isset($columns['address_id'])) {
    $db->ExecuteMaster('ALTER TABLE ticket_comment ADD COLUMN address_id INT UNSIGNED DEFAULT 0 NOT NULL');
	$db->ExecuteMaster('ALTER TABLE ticket_comment ADD INDEX address_id (address_id)');
}

if(isset($columns['worker_id'])) {
	// Convert worker_id to address_id
	$sql = "SELECT w.id, a.id AS address_id FROM worker w INNER JOIN address a ON (w.email=a.email)";
	$rs = $db->ExecuteMaster($sql);
	
	while($row = mysqli_fetch_assoc($rs)) {
		$worker_id = intval($row['id']);
		$address_id = intval($row['address_id']);
		
		$db->ExecuteMaster(sprintf("UPDATE ticket_comment SET address_id = %d WHERE worker_id = %d AND address_id = 0",
			$address_id,
			$worker_id
		));
	}
	
	mysqli_free_result($rs);
	
	$db->ExecuteMaster("ALTER TABLE ticket_comment DROP COLUMN worker_id");
}

// `ticket_field` ========================
if(isset($tables['ticket_field'])) {
	list($columns, $indexes) = $db->metaTable('ticket_field');	
	
	if(!isset($indexes['pos'])) {
		$db->ExecuteMaster('ALTER TABLE ticket_field ADD INDEX pos (pos)');
	}
	
	if('varchar(128)' != $columns['name']['type']) {
		$db->ExecuteMaster("ALTER TABLE ticket_field CHANGE COLUMN name name varchar(128) DEFAULT '' NOT NULL");
	}
}

// [NOTE] This table gets renamed below, so any other changes need to happen below that point

// `ticket_field` ========================
if(!isset($tables['custom_field']) && isset($tables['ticket_field'])) {
	$db->ExecuteMaster("RENAME TABLE ticket_field TO custom_field");
}

// `ticket_field_seq` ========================
if(!isset($tables['custom_field_seq']) && isset($tables['ticket_field_seq'])) {
	$db->ExecuteMaster("RENAME TABLE ticket_field_seq TO custom_field_seq");
}

// `ticket_field_value` ========================
if(!isset($tables['custom_field_value']) && isset($tables['ticket_field_value'])) {
	$db->ExecuteMaster("RENAME TABLE ticket_field_value TO custom_field_value");
}

// `custom_field` ========================
list($columns, $indexes) = $db->metaTable('custom_field');

if(!isset($columns['source_extension'])) {
    $db->ExecuteMaster("ALTER TABLE custom_field ADD COLUMN source_extension VARCHAR(255) DEFAULT '' NOT NULL");
    
    $sql = "UPDATE custom_field SET source_extension = 'cerberusweb.fields.source.ticket' WHERE source_extension = ''";
    $db->ExecuteMaster($sql);
}

if(!isset($indexes['source_extension'])) {
	$db->ExecuteMaster('ALTER TABLE custom_field ADD INDEX source_extension (source_extension)');
}

if(isset($columns['id']) 
	&& ('int(10) unsigned' != $columns['id']['type'] 
	|| 'auto_increment' != $columns['id']['extra'])
) {
	$db->ExecuteMaster("ALTER TABLE custom_field MODIFY COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT");
}

// `custom_field_value` ========================
list($columns, $indexes) = $db->metaTable('custom_field_value');

if(!isset($columns['source_extension'])) {
    $db->ExecuteMaster("ALTER TABLE custom_field_value ADD COLUMN source_extension VARCHAR(255) DEFAULT '' NOT NULL");
    
    $sql = "UPDATE custom_field_value SET source_extension = 'cerberusweb.fields.source.ticket' WHERE source_extension = ''";
    $db->ExecuteMaster($sql);
}

if(!isset($columns['source_id']) && isset($columns['ticket_id'])) {
	if(isset($indexes['ticket_id'])) {
		$db->ExecuteMaster('ALTER TABLE custom_field_value DROP INDEX ticket_id');
	}
	
	$db->ExecuteMaster("ALTER TABLE custom_field_value CHANGE COLUMN ticket_id source_id int(11) DEFAULT '0' NOT NULL");
}

if(!isset($indexes['field_id'])) {
	$db->ExecuteMaster('ALTER TABLE custom_field_value ADD INDEX field_id (field_id)');
}

if(!isset($indexes['source_extension'])) {
	$db->ExecuteMaster('ALTER TABLE custom_field_value ADD INDEX source_extension (source_extension)');
}

if(!isset($indexes['source_id'])) {
	$db->ExecuteMaster('ALTER TABLE custom_field_value ADD INDEX source_id (source_id)');
}

// ===========================================================================
// Migrate some fields out of core and into custom fields to clean concepts up
// ===========================================================================

	// `address` ======================
	list($columns, $indexes) = $db->metaTable('address');
	
	if(isset($columns['phone'])) {
		$sql = "SELECT count(id) FROM address WHERE phone != ''";
		$count = $db->GetOneMaster($sql);
		
		if(!empty($count)) { // Move to a custom field before dropping
			// Create the new custom field
			$sql = sprintf("INSERT INTO custom_field (name,type,group_id,pos,options,source_extension) ".
				"VALUES ('Phone','S',0,0,'',%s)",
				$db->qstr('cerberusweb.fields.source.address')
			);
			$db->ExecuteMaster($sql);
			$field_id = $db->LastInsertId();
			
			// Populate the custom field from org records
			$sql = sprintf("INSERT INTO custom_field_value (field_id, source_id, field_value, source_extension) ".
				"SELECT %d, id, phone, %s FROM address WHERE phone != ''",
				$field_id,
				$db->qstr('cerberusweb.fields.source.address')
			);
			$db->ExecuteMaster($sql);
		}
		
		// Drop the account number hardcoded column
		$db->ExecuteMaster('ALTER TABLE address DROP COLUMN phone');
	}
	
	// `contact_org` ======================
	list($columns, $indexes) = $db->metaTable('contact_org');
	
	if(isset($columns['account_number'])) {
		$sql = "SELECT count(id) FROM contact_org WHERE account_number != ''";
		$count = $db->GetOneMaster($sql);
		
		if(!empty($count)) { // Move to a custom field before dropping
			// Create the new custom field
			$sql = sprintf("INSERT INTO custom_field (name,type,group_id,pos,options,source_extension) ".
				"VALUES ('Account #','S',0,0,'',%s)",
				$db->qstr('cerberusweb.fields.source.org')
			);
			$db->ExecuteMaster($sql);
			$field_id = $db->LastInsertId();
			
			// Populate the custom field from org records
			$sql = sprintf("INSERT INTO custom_field_value (field_id, source_id, field_value, source_extension) ".
				"SELECT %d, id, account_number, %s FROM contact_org WHERE account_number != ''",
				$field_id,
				$db->qstr('cerberusweb.fields.source.org')
			);
			$db->ExecuteMaster($sql);
		}
		
		// Drop the account number hardcoded column
		$db->ExecuteMaster('ALTER TABLE contact_org DROP COLUMN account_number');
	}

// `view_rss` ======================
if(!isset($tables['view_rss']) && isset($tables['ticket_rss'])) {
	$db->ExecuteMaster("RENAME TABLE ticket_rss TO view_rss");
}

list($columns, $indexes) = $db->metaTable('view_rss');

if(!isset($columns['source_extension'])) {
    $db->ExecuteMaster("ALTER TABLE view_rss ADD COLUMN source_extension VARCHAR(255) DEFAULT '' NOT NULL");
    
    $sql = "UPDATE view_rss SET source_extension = 'core.rss.source.ticket' WHERE source_extension = ''";
    $db->ExecuteMaster($sql);
}

// `worker` ========================
list($columns, $indexes) = $db->metaTable('worker');

if(!isset($columns['is_disabled'])) {
    $db->ExecuteMaster('ALTER TABLE worker ADD COLUMN is_disabled TINYINT(1) UNSIGNED DEFAULT 0 NOT NULL');
}

if(!isset($indexes['last_activity_date'])) {
	$db->ExecuteMaster('ALTER TABLE worker ADD INDEX last_activity_date (last_activity_date)');
}

// Configure import cron
if(null != ($cron_mf = DevblocksPlatform::getExtension('cron.import'))) {
	if(null != ($cron = $cron_mf->createInstance())) {
		$cron->setParam(CerberusCronPageExtension::PARAM_ENABLED, false);
		$cron->setParam(CerberusCronPageExtension::PARAM_DURATION, '0');
		$cron->setParam(CerberusCronPageExtension::PARAM_TERM, 'm');
		$cron->setParam(CerberusCronPageExtension::PARAM_LASTRUN, strtotime('Yesterday'));
	}
}

// `worker_event` =============================
if(!isset($tables['worker_event'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS worker_event (
			id INT UNSIGNED DEFAULT 0 NOT NULL,
			created_date INT UNSIGNED DEFAULT 0 NOT NULL,
			worker_id INT UNSIGNED DEFAULT 0 NOT NULL,
			title VARCHAR(255) DEFAULT '' NOT NULL,
			content TEXT,
			is_read TINYINT(1) UNSIGNED DEFAULT 0 NOT NULL,
			url VARCHAR(255) DEFAULT '' NOT NULL,
			PRIMARY KEY (id),
			INDEX created_date (created_date),
			INDEX worker_id (worker_id),
			INDEX is_read (is_read)
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->ExecuteMaster($sql);	
}

return TRUE;
