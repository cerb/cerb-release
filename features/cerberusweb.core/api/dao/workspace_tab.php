<?php
class DAO_WorkspaceTab extends Cerb_ORMHelper {
	const EXTENSION_ID = 'extension_id';
	const ID = 'id';
	const NAME = 'name';
	const PARAMS_JSON = 'params_json';
	const POS = 'pos';
	const UPDATED_AT = 'updated_at';
	const WORKSPACE_PAGE_ID = 'workspace_page_id';
	
	const _CACHE_ALL = 'ch_workspace_tabs';
	
	private function __construct() {}

	static function getFields() {
		$validation = DevblocksPlatform::services()->validation();
		
		// varchar(255)
		$validation
			->addField(self::EXTENSION_ID, DevblocksPlatform::translateCapitalized('common.type'))
			->string()
			->setMaxLength(255)
			->setRequired(true)
			->addValidator(function($value, &$error=null) {
				if(false == Extension_WorkspaceTab::get($value)) {
					$error = sprintf("is not a valid workspace tab extension (%s).", $value);
					return false;
				}
				
				return true;
			})
			;
		// int(10) unsigned
		$validation
			->addField(self::ID, DevblocksPlatform::translate('common.id'))
			->id()
			->setEditable(false)
			;
		// varchar(128)
		$validation
			->addField(self::NAME, DevblocksPlatform::translateCapitalized('common.name'))
			->string()
			->setMaxLength(128)
			->setRequired(true)
			;
		// text
		$validation
			->addField(self::PARAMS_JSON)
			->string()
			->setMaxLength(65535)
			;
		// tinyint(3) unsigned
		$validation
			->addField(self::POS)
			->uint(1)
			;
		// int(10) unsigned
		$validation
			->addField(self::UPDATED_AT)
			->timestamp()
			;
		// int(10) unsigned
		$validation
			->addField(self::WORKSPACE_PAGE_ID, DevblocksPlatform::translateCapitalized('common.workspace.page'))
			->id()
			->setRequired(true)
			->addValidator($validation->validators()->contextId(CerberusContexts::CONTEXT_WORKSPACE_PAGE))
			;
		$validation
			->addField('_fieldsets')
			->string()
			->setMaxLength(65535)
			;
		$validation
			->addField('_links')
			->string()
			->setMaxLength(65535)
			;
			
		return $validation->getFields();
	}
	
	static function create($fields) {
		$db = DevblocksPlatform::services()->database();
		
		$sql = "INSERT INTO workspace_tab () VALUES ()";
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields, $check_deltas=true) {
		if(!is_array($ids))
			$ids = [$ids];
		
		$context = CerberusContexts::CONTEXT_WORKSPACE_TAB;
		self::_updateAbstract($context, $ids, $fields);
		
		if(!isset($fields[self::UPDATED_AT]))
			$fields[self::UPDATED_AT] = time();
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
				
			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges($context, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'workspace_tab', $fields);
			
			// Send events
			if($check_deltas) {
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::services()->event();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.workspace_tab.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged($context, $batch_ids);
			}
		}
		
		self::clearCache();
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('workspace_tab', $fields, $where);
		self::clearCache();
	}
	
	static public function onBeforeUpdateByActor($actor, &$fields, $id=null, &$error=null) {
		$context = CerberusContexts::CONTEXT_WORKSPACE_TAB;
		
		if(!self::_onBeforeUpdateByActorCheckContextPrivs($actor, $context, $id, $error))
			return false;
		
		if(!$id && !isset($fields[self::WORKSPACE_PAGE_ID])) {
			$error = "A 'page_id' is required.";
			return false;
		}
		
		if(isset($fields[self::WORKSPACE_PAGE_ID])) {
			@$page_id = $fields[self::WORKSPACE_PAGE_ID];
			
			if(!$page_id) {
				$error = "Invalid 'page_id' value.";
				return false;
			}
			
			if(!Context_WorkspacePage::isWriteableByActor($page_id, $actor)) {
				$error = "You do not have permission to create tabs on this workspace page.";
				return false;
			}
		}
		
		return true;
	}
	
	static function getAll($nocache=false) {
		$cache = DevblocksPlatform::services()->cache();
		
		if($nocache || null === ($tabs = $cache->load(self::_CACHE_ALL))) {
			$tabs = self::getWhere(
				null,
				DAO_WorkspaceTab::POS,
				true,
				null,
				Cerb_ORMHelper::OPT_GET_MASTER_ONLY
			);
			
			if(!is_array($tabs))
				return false;
			
			$cache->save($tabs, self::_CACHE_ALL);
		}
		
		return $tabs;
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_WorkspaceTab[]
	 */
	static function getWhere($where=null, $sortBy=DAO_WorkspaceTab::POS, $sortAsc=true, $limit=null, $options=null) {
		$db = DevblocksPlatform::services()->database();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, name, workspace_page_id, pos, extension_id, params_json, updated_at ".
			"FROM workspace_tab ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;

		if($options & Cerb_ORMHelper::OPT_GET_MASTER_ONLY) {
			$rs = $db->ExecuteMaster($sql, _DevblocksDatabaseManager::OPT_NO_READ_AFTER_WRITE);
		} else {
			$rs = $db->ExecuteSlave($sql);
		}
		
		return self::_getObjectsFromResult($rs);
	}
	
	/**
	 * @param integer $id
	 * @return Model_WorkspaceTab
	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		$objects = self::getAll();
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	static function getByPage($page_id) {
		$all_tabs = self::getAll();
		$tabs = [];
		
		foreach($all_tabs as $tab_id => $tab) { /* @var $tab Model_WorkspaceTab */
			if($tab->workspace_page_id == $page_id)
				$tabs[$tab_id] = $tab;
		}

		return $tabs;
	}
	
	static function getByPageIds(array $page_ids) {
		$all_tabs = self::getAll();
		$tabs = [];
		
		foreach($all_tabs as $tab_id => $tab) { /* @var $tab Model_WorkspaceTab */
			if(in_array($tab->workspace_page_id, $page_ids))
				$tabs[$tab_id] = $tab;
		}

		return $tabs;
	}
	
	static function countByPageId($page_id) {
		$db = DevblocksPlatform::services()->database();
		
		$sql = sprintf("SELECT count(workspace_page_id) FROM workspace_tab WHERE workspace_page_id = %d",
			$page_id
		);
		return intval($db->GetOneSlave($sql));
	}
	
	/**
	 * 
	 * @param array $ids
	 * @return Model_WorkspaceTab[]
	 */
	static function getIds($ids) {
		return parent::getIds($ids);
	}
	
	/**
	 * @param resource $rs
	 * @return Model_WorkspaceTab[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = [];
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_WorkspaceTab();
			$object->id = intval($row['id']);
			$object->name = $row['name'];
			$object->workspace_page_id = intval($row['workspace_page_id']);
			$object->pos = intval($row['pos']);
			$object->extension_id = $row['extension_id'];
			$object->updated_at = intval($row['updated_at']);
			
			if(!empty($row['params_json']) && false !== ($params = json_decode($row['params_json'], true)))
				@$object->params = $params;
			
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function random() {
		return self::_getRandom('workspace_tab');
	}
	
	static function delete($ids) {
		if(!is_array($ids))
			$ids = array($ids);
		
		$db = DevblocksPlatform::services()->database();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		DAO_WorkspaceWidget::deleteByTab($ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM workspace_list WHERE workspace_tab_id IN (%s)", $ids_list));
		
		$db->ExecuteMaster(sprintf("DELETE FROM workspace_tab WHERE id IN (%s)", $ids_list));
		
		self::clearCache();
		
		return true;
	}
	
	static function deleteByPage($ids) {
		if(!is_array($ids))
			$ids = array($ids);
		
		$db = DevblocksPlatform::services()->database();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		// Find tab IDs by given page IDs
		$rows = $db->GetArrayMaster(sprintf("SELECT id FROM workspace_tab WHERE workspace_page_id IN (%s)", $ids_list));

		// Loop tab IDs and delete
		if(is_array($rows))
		foreach($rows as $row)
			self::delete($row['id']);
		
		return true;
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_WorkspaceTab::getFields();
		
		list(, $wheres) = parent::_parseSearchParams($params, $columns, 'SearchFields_WorkspaceTab', $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"workspace_tab.id as %s, ".
			"workspace_tab.name as %s, ".
			"workspace_tab.workspace_page_id as %s, ".
			"workspace_tab.pos as %s, ".
			"workspace_tab.updated_at as %s, ".
			"workspace_tab.extension_id as %s ",
				SearchFields_WorkspaceTab::ID,
				SearchFields_WorkspaceTab::NAME,
				SearchFields_WorkspaceTab::WORKSPACE_PAGE_ID,
				SearchFields_WorkspaceTab::POS,
				SearchFields_WorkspaceTab::UPDATED_AT,
				SearchFields_WorkspaceTab::EXTENSION_ID
			);
			
		$join_sql = "FROM workspace_tab ";
		
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields, $select_sql, 'SearchFields_WorkspaceTab');
	
		$query_parts = [
			'primary_table' => 'workspace_tab',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'sort' => $sort_sql,
		];
		
		return $query_parts;
	}
	
	/**
	 *
	 * @param array $columns
	 * @param DevblocksSearchCriteria[] $params
	 * @param integer $limit
	 * @param integer $page
	 * @param string $sortBy
	 * @param boolean $sortAsc
	 * @param boolean $withCounts
	 * @return array
	 */
	static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::services()->database();
		
		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			$sort_sql;
			
		if($limit > 0) {
			if(false == ($rs = $db->SelectLimit($sql,$limit,$page*$limit)))
				return false;
		} else {
			if(false == ($rs = $db->ExecuteSlave($sql)))
				return false;
			$total = mysqli_num_rows($rs);
		}
		
		$results = [];
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_WorkspaceTab::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					"SELECT COUNT(workspace_tab.id) ".
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}
	
	public static function maint() {
		$db = DevblocksPlatform::services()->database();
		$logger = DevblocksPlatform::services()->log();
		
		$db->ExecuteMaster("DELETE FROM workspace_list WHERE workspace_tab_id NOT IN (SELECT id FROM workspace_tab)");
		$logger->info('[Maint] Purged ' . $db->Affected_Rows() . ' workspace_list records.');
	}

	static function clearCache() {
		$cache = DevblocksPlatform::services()->cache();
		$cache->remove(self::_CACHE_ALL);
		$cache->removeByTags(['schema_workspaces']);
	}
	
};

class SearchFields_WorkspaceTab extends DevblocksSearchFields {
	const ID = 'w_id';
	const NAME = 'w_name';
	const WORKSPACE_PAGE_ID = 'w_workspace_page_id';
	const POS = 'w_pos';
	const EXTENSION_ID = 'w_extension_id';
	const UPDATED_AT = 'w_updated_at';
	
	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_HAS_FIELDSET = '*_has_fieldset';
	
	static private $_fields = null;
	
	static function getPrimaryKey() {
		return 'workspace_tab.id';
	}
	
	static function getCustomFieldContextKeys() {
		return array(
			CerberusContexts::CONTEXT_WORKSPACE_TAB => new DevblocksSearchFieldContextKeys('workspace_tab.id', self::ID),
			CerberusContexts::CONTEXT_WORKSPACE_PAGE => new DevblocksSearchFieldContextKeys('workspace_tab.workspace_page_id', self::WORKSPACE_PAGE_ID),
		);
	}
	
	static function getWhereSQL(DevblocksSearchCriteria $param) {
		switch($param->field) {
			case self::VIRTUAL_CONTEXT_LINK:
				return self::_getWhereSQLFromContextLinksField($param, CerberusContexts::CONTEXT_WORKSPACE_TAB, self::getPrimaryKey());
				break;
				
			case self::VIRTUAL_HAS_FIELDSET:
				return self::_getWhereSQLFromVirtualSearchSqlField($param, CerberusContexts::CONTEXT_CUSTOM_FIELDSET, sprintf('SELECT context_id FROM context_to_custom_fieldset WHERE context = %s AND custom_fieldset_id IN (%%s)', Cerb_ORMHelper::qstr(CerberusContexts::CONTEXT_WORKSPACE_TAB)), self::getPrimaryKey());
				break;
				
			default:
				if('cf_' == substr($param->field, 0, 3)) {
					return self::_getWhereSQLFromCustomFields($param);
				} else {
					return $param->getWhereSQL(self::getFields(), self::getPrimaryKey());
				}
				break;
		}
	}
	
	static function getFieldForSubtotalKey($key, $context, array $query_fields, array $search_fields, $primary_key) {
		switch($key) {
			case 'page':
				$key = 'page.id';
				break;
		}
		
		return parent::getFieldForSubtotalKey($key, $context, $query_fields, $search_fields, $primary_key);
	}
	
	static function getLabelsForKeyValues($key, $values) {
		switch($key) {
			case SearchFields_WorkspaceTab::EXTENSION_ID:
				return parent::_getLabelsForKeyExtensionValues(Extension_WorkspaceTab::POINT);
				break;
				
			case SearchFields_WorkspaceTab::ID:
				$models = DAO_WorkspaceTab::getIds($values);
				return array_column(DevblocksPlatform::objectsToArrays($models), 'name', 'id');
				break;
				
			case SearchFields_WorkspaceTab::WORKSPACE_PAGE_ID:
				$models = DAO_WorkspacePage::getIds($values);
				return array_column(DevblocksPlatform::objectsToArrays($models), 'name', 'id');
				break;
		}
		
		return parent::getLabelsForKeyValues($key, $values);
	}
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		if(is_null(self::$_fields))
			self::$_fields = self::_getFields();
		
		return self::$_fields;
	}
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function _getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::EXTENSION_ID => new DevblocksSearchField(self::EXTENSION_ID, 'workspace_tab', 'extension_id', $translate->_('common.type'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::ID => new DevblocksSearchField(self::ID, 'workspace_tab', 'id', $translate->_('common.id'), Model_CustomField::TYPE_NUMBER, true),
			self::NAME => new DevblocksSearchField(self::NAME, 'workspace_tab', 'name', $translate->_('common.name'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::POS => new DevblocksSearchField(self::POS, 'workspace_tab', 'pos', $translate->_('common.order'), Model_CustomField::TYPE_NUMBER, true),
			self::UPDATED_AT => new DevblocksSearchField(self::UPDATED_AT, 'workspace_tab', 'updated_at', $translate->_('common.updated'), Model_CustomField::TYPE_DATE, true),
			self::WORKSPACE_PAGE_ID => new DevblocksSearchField(self::WORKSPACE_PAGE_ID, 'workspace_tab', 'workspace_page_id', $translate->_('common.workspace.page'), Model_CustomField::TYPE_NUMBER, true),
			
			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null, false),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null, false),
		);
		
		// Custom Fields
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array_keys(self::getCustomFieldContextKeys()));
		
		if(!empty($custom_columns))
			$columns = array_merge($columns, $custom_columns);
		
		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_WorkspaceTab {
	public $id;
	public $name;
	public $workspace_page_id;
	public $pos;
	public $extension_id;
	public $params=[];
	public $updated_at;
	
	/**
	 * @return Model_WorkspacePage
	 */
	function getWorkspacePage() {
		return DAO_WorkspacePage::get($this->workspace_page_id);
	}
	
	/**
	 * @return Extension_WorkspaceTab
	 */
	function getExtension() {
		$extension_id = $this->extension_id;
		
		
		if(null != ($extension = DevblocksPlatform::getExtension($extension_id, true)))
			return $extension;
		
		return null;
	}
	
	/**
	 * @return string
	 */
	function getExtensionName() {
		if(false == ($extension = $this->getExtension()))
			return null;
		
		return DevblocksPlatform::translateCapitalized($extension->manifest->params['label']);
	}
	
	function getPlaceholderPrompts() {
		if(false == (@$placeholder_prompts = $this->params['placeholder_prompts']))
			return [];
		
		if(false == (@$placeholder_prompts = yaml_parse($placeholder_prompts, -1)))
			return [];
		
		$keys = array_map(function($prompt) { return $prompt['placeholder']; }, $placeholder_prompts);
		
		// Set placeholder names as keys
		return array_combine($keys, $placeholder_prompts);
		
		// Handle PHP's single document YAML format
		/*
		if(
			array_key_exists(0, $placeholder_prompts) 
			&& !array_key_exists('placeholder', $placeholder_prompts[0])
			&& array_key_exists(0, $placeholder_prompts[0])
		) {
			$placeholder_prompts = $placeholder_prompts[0];
		}
		*/
		
		return $placeholder_prompts;
	}
	
	function getDashboardPrefsAsWorker(Model_Worker $worker) {
		$prefs = [];
		
		if(false != ($placeholder_prompts = $this->getPlaceholderPrompts()) 
			&& is_array($placeholder_prompts)) {
				
			foreach($placeholder_prompts as $prompt) {
				$prefs[$prompt['placeholder']] = $prompt['default'];
			}
		}
		
		$results = DAO_WorkerDashboardPref::get($this->id, $worker);
		
		// Set values based on prompts
		
		foreach($results as $result) {
			if(false == (@$prompt = $placeholder_prompts[$result['pref_key']]))
				continue;
			
			switch($prompt['type']) {
				case 'picklist':
					if(@$prompt['params']['multiple']) {
						$prefs[$result['pref_key']] = json_decode($result['pref_value'], true);
						
					} else {
						$prefs[$result['pref_key']] = $result['pref_value'];
					}
					break;
					
				case 'chooser':
				case 'date_range':
				default:
					$prefs[$result['pref_key']] = $result['pref_value'];
					break;
			}
		}
		
		return $prefs;
	}
	
	function setDashboardPrefsAsWorker(array $prefs, Model_Worker $worker) {
		$placeholder_prompts = $this->getPlaceholderPrompts();
		
		foreach($prefs as $pref_key => $pref_value) {
			if(false == (@$prompt = $placeholder_prompts[$pref_key]))
				continue;
			
			switch($prompt['type']) {
				case 'chooser':
					$prefs[$pref_key] = implode(',', $pref_value);
					break;
					
				case 'picklist':
					if(@$prompt['params']['multiple']) {
						$prefs[$pref_key] = json_encode($pref_value);
					}
					break;
					
				case 'date_range':
				default:
					break;
			}
		}
		
		DAO_WorkerDashboardPref::set($this->id, $prefs, $worker);
	}
	
	/**
	 * @return Model_WorkspaceList[]
	 */
	function getWorklists() {
		return DAO_WorkspaceList::getByTab($this->id);
	}
};

class View_WorkspaceTab extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'workspacetab';

	function __construct() {
		$this->id = self::DEFAULT_ID;
		$this->name = DevblocksPlatform::translateCapitalized('common.workspace.tabs');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_WorkspaceTab::NAME;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_WorkspaceTab::NAME,
			SearchFields_WorkspaceTab::WORKSPACE_PAGE_ID,
			SearchFields_WorkspaceTab::EXTENSION_ID,
			SearchFields_WorkspaceTab::POS,
			SearchFields_WorkspaceTab::UPDATED_AT,
		);
		$this->addColumnsHidden(array(
			SearchFields_WorkspaceTab::VIRTUAL_CONTEXT_LINK,
			SearchFields_WorkspaceTab::VIRTUAL_HAS_FIELDSET,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_WorkspaceTab::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		
		$this->_lazyLoadCustomFieldsIntoObjects($objects, 'SearchFields_WorkspaceTab');
		
		return $objects;
	}
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_WorkspaceTab', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_WorkspaceTab', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = [];

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Fields
				case SearchFields_WorkspaceTab::EXTENSION_ID:
				case SearchFields_WorkspaceTab::WORKSPACE_PAGE_ID:
					$pass = true;
					break;
					
				// Virtuals
				case SearchFields_WorkspaceTab::VIRTUAL_CONTEXT_LINK:
				case SearchFields_WorkspaceTab::VIRTUAL_HAS_FIELDSET:
					$pass = true;
					break;
					
				// Valid custom fields
				default:
					if(DevblocksPlatform::strStartsWith($field_key, 'cf_'))
						$pass = $this->_canSubtotalCustomField($field_key);
					break;
			}
			
			if($pass)
				$fields[$field_key] = $field_model;
		}
		
		return $fields;
	}
	
	function getSubtotalCounts($column) {
		$counts = [];
		$fields = $this->getFields();
		$context = CerberusContexts::CONTEXT_WORKSPACE_TAB;

		if(!isset($fields[$column]))
			return [];
		
		switch($column) {
			case SearchFields_WorkspaceTab::EXTENSION_ID:
			case SearchFields_WorkspaceTab::WORKSPACE_PAGE_ID:
				$label_map = function(array $values) use ($column) {
					return SearchFields_WorkspaceTab::getLabelsForKeyValues($column, $values);
				};
				$counts = $this->_getSubtotalCountForStringColumn($context, $column, $label_map, '=', 'value');
				break;

			case SearchFields_WorkspaceTab::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn($context, $column);
				break;
				
			case SearchFields_WorkspaceTab::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn($context, $column);
				break;

			default:
				// Custom fields
				if(DevblocksPlatform::strStartsWith($column, 'cf_')) {
					$counts = $this->_getSubtotalCountForCustomColumn($context, $column);
				}
				
				break;
		}
		
		return $counts;
	}
	
	function getQuickSearchFields() {
		$search_fields = SearchFields_WorkspaceTab::getFields();
	
		$fields = array(
			'text' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_WorkspaceTab::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'fieldset' =>
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_WorkspaceTab::VIRTUAL_HAS_FIELDSET),
					'examples' => [
						['type' => 'search', 'context' => CerberusContexts::CONTEXT_CUSTOM_FIELDSET, 'qr' => 'context:' . CerberusContexts::CONTEXT_WORKSPACE_TAB],
					]
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_WorkspaceTab::ID),
					'examples' => [
						['type' => 'chooser', 'context' => CerberusContexts::CONTEXT_WORKSPACE_TAB, 'q' => ''],
					]
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_WorkspaceTab::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'page.id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_WorkspaceTab::WORKSPACE_PAGE_ID),
					'examples' => [
						['type' => 'chooser', 'context' => 'cerberusweb.contexts.workspace.page', 'q' => ''],
					]
				),
			'pos' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_WorkspaceTab::POS),
				),
			'type' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_WorkspaceTab::EXTENSION_ID, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PREFIX),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_WorkspaceTab::UPDATED_AT),
				),
		);
		
		// Add quick search links
		
		$fields = self::_appendVirtualFiltersFromQuickSearchContexts('links', $fields, 'links', SearchFields_WorkspaceTab::VIRTUAL_CONTEXT_LINK);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_WORKSPACE_TAB, $fields, null);
		
		// Add is_sortable
		
		$fields = self::_setSortableQuickSearchFields($fields, $search_fields);
		
		// Sort by keys
		ksort($fields);
		
		return $fields;
	}	
	
	function getParamFromQuickSearchFieldTokens($field, $tokens) {
		switch($field) {
			case 'fieldset':
				return DevblocksSearchCriteria::getVirtualQuickSearchParamFromTokens($field, $tokens, '*_has_fieldset');
				break;
			
			default:
				if($field == 'links' || substr($field, 0, 6) == 'links.')
					return DevblocksSearchCriteria::getContextLinksParamFromTokens($field, $tokens);
				
				$search_fields = $this->getQuickSearchFields();
				return DevblocksSearchCriteria::getParamFromQueryFieldTokens($field, $tokens, $search_fields);
				break;
		}
		
		return false;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_WORKSPACE_TAB);
		$tpl->assign('custom_fields', $custom_fields);
		
		// Extensions
		$tab_extensions = Extension_WorkspaceTab::getAll(false);
		$tpl->assign('tab_extensions', $tab_extensions);

		$tpl->assign('view_template', 'devblocks:cerberusweb.core::internal/workspaces/tabs/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			case SearchFields_WorkspaceTab::EXTENSION_ID:
			case SearchFields_WorkspaceTab::WORKSPACE_PAGE_ID:
				$label_map = SearchFields_WorkspaceTab::getLabelsForKeyValues($field, $values);
				parent::_renderCriteriaParamString($param, $label_map);
				break;
			
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		switch($key) {
			case SearchFields_WorkspaceTab::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_WorkspaceTab::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_WorkspaceTab::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_WorkspaceTab::NAME:
			case SearchFields_WorkspaceTab::EXTENSION_ID:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_WorkspaceTab::ID:
			case SearchFields_WorkspaceTab::WORKSPACE_PAGE_ID:
			case SearchFields_WorkspaceTab::POS:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_WorkspaceTab::UPDATED_AT:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_WorkspaceTab::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',[]);
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_WorkspaceTab::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',[]);
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}
};

class Context_WorkspaceTab extends Extension_DevblocksContext implements IDevblocksContextPeek, IDevblocksContextProfile {
	const ID = CerberusContexts::CONTEXT_WORKSPACE_TAB;
	
	static function isReadableByActor($models, $actor) {
		return CerberusContexts::isReadableByDelegateOwner($actor, CerberusContexts::CONTEXT_WORKSPACE_TAB, $models, 'page_owner_');
	}
	
	static function isWriteableByActor($models, $actor) {
		return CerberusContexts::isWriteableByDelegateOwner($actor, CerberusContexts::CONTEXT_WORKSPACE_TAB, $models, 'page_owner_');
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::services()->url();
		$url = $url_writer->writeNoProxy('c=profiles&type=workspace_tab&id='.$context_id, true);
		return $url;
	}
	
	function profileGetFields($model=null) {
		$translate = DevblocksPlatform::getTranslationService();
		$properties = [];
		
		if(is_null($model))
			$model = new Model_WorkspaceTab();
		
		$properties['name'] = array(
			'label' => mb_ucfirst($translate->_('common.name')),
			'type' => Model_CustomField::TYPE_LINK,
			'value' => $model->id,
			'params' => [
				'context' => self::ID,
			],
		);
		
		$properties['page_id'] = array(
			'label' => mb_ucfirst($translate->_('common.page')),
			'type' => Model_CustomField::TYPE_LINK,
			'value' => $model->workspace_page_id,
			'params' => [
				'context' => CerberusContexts::CONTEXT_WORKSPACE_PAGE,
			]
		);
		
		$properties['extension_id'] = array(
			'label' => mb_ucfirst($translate->_('common.type')),
			'type' => Model_CustomField::TYPE_SINGLE_LINE,
			'value' => $model->getExtensionName(),
		);
		
		$properties['updated'] = array(
			'label' => DevblocksPlatform::translateCapitalized('common.updated'),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $model->updated_at,
		);
		
		return $properties;
	}
	
	function getRandom() {
		return DAO_WorkspaceTab::random();
	}
	
	function getMeta($context_id) {
		if(null == ($workspace_tab = DAO_WorkspaceTab::get($context_id)))
			return [];
		
		return array(
			'id' => $workspace_tab->id,
			'name' => $workspace_tab->name,
			'permalink' => $this->profileGetUrl($context_id),
			'updated' => $workspace_tab->updated_at,
		);
	}
	
	function getDefaultProperties() {
		return array(
			'extension__label',
			'page__label',
			'order',
			'updated_at',
		);
	}
	
	function getContext($tab, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Workspace Tab:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_WORKSPACE_TAB);
		
		// Polymorph
		if(is_numeric($tab)) {
			$tab = DAO_WorkspaceTab::get($tab);
		} elseif($tab instanceof Model_WorkspaceTab) {
			// It's what we want already.
		} elseif(is_array($tab)) {
			$tab = Cerb_ORMHelper::recastArrayToModel($tab, 'Model_WorkspaceTab');
		} else {
			$tab = null;
		}
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'extension__label' => $prefix.$translate->_('common.type'),
			'extension_id' => $prefix.$translate->_('Extension ID'),
			'id' => $prefix.$translate->_('common.id'),
			'name' => $prefix.$translate->_('common.name'),
			'order' => $prefix.$translate->_('common.order'),
			'updated_at' => $prefix.$translate->_('common.updated'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'extension__label' => Model_CustomField::TYPE_SINGLE_LINE,
			'extension_id' => Model_CustomField::TYPE_SINGLE_LINE,
			'id' => Model_CustomField::TYPE_NUMBER,
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
			'order' => Model_CustomField::TYPE_NUMBER,
			'updated_at' => Model_CustomField::TYPE_DATE,
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Custom field/fieldset token types
		if(false !== ($custom_field_types = $this->_getTokenTypesFromCustomFields($fields, $prefix)) && is_array($custom_field_types))
			$token_types = array_merge($token_types, $custom_field_types);
		
		// Token values
		$token_values = [];
		
		$token_values['_context'] = CerberusContexts::CONTEXT_WORKSPACE_TAB;
		$token_values['_types'] = $token_types;
		
		// Token values
		if(null != $tab) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $tab->name;
			$token_values['extension_id'] = $tab->extension_id;
			$token_values['id'] = $tab->id;
			$token_values['name'] = $tab->name;
			$token_values['order'] = $tab->pos;
			$token_values['page_id'] = $tab->workspace_page_id;
			$token_values['updated_at'] = $tab->updated_at;
			
			if(false != ($tab_extension = $tab->getExtension())) {
				$token_values['extension__label'] = DevblocksPlatform::translateCapitalized($tab_extension->manifest->params['label']);
			}
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($tab, $token_values);
			
			// URL
			$url_writer = DevblocksPlatform::services()->url();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=workspace_tab&id=%d-%s",$tab->id, DevblocksPlatform::strToPermalink($tab->name)), true);
		}
		
		// Page
		$merge_token_labels = $merge_token_values = [];
		CerberusContexts::getContext(CerberusContexts::CONTEXT_WORKSPACE_PAGE, null, $merge_token_labels, $merge_token_values, '', true);
		
		CerberusContexts::merge(
			'page_',
			$prefix.'Page:',
			$merge_token_labels,
			$merge_token_values,
			$token_labels,
			$token_values
		);
		
		return true;
	}
	
	function getKeyToDaoFieldMap() {
		return [
			'extension_id' => DAO_WorkspaceTab::EXTENSION_ID,
			'id' => DAO_WorkspaceTab::ID,
			'links' => '_links',
			'name' => DAO_WorkspaceTab::NAME,
			'page_id' => DAO_WorkspaceTab::WORKSPACE_PAGE_ID,
			'pos' => DAO_WorkspaceTab::POS,
			'updated_at' => DAO_WorkspaceTab::UPDATED_AT,
		];
	}
	
	function getKeyMeta() {
		$keys = parent::getKeyMeta();
		
		$keys['params'] = [
			'is_immutable' => false,
			'is_required' => false,
			'notes' => 'JSON-encoded key/value object',
			'type' => 'object',
		];
		
		$keys['extension_id']['notes'] = "[Workspace Tab Type](/docs/plugins/extensions/points/cerberusweb.ui.workspace.tab/)";
		$keys['page_id']['notes'] = "The ID of the [workspace page](/docs/records/types/workspace_page/) containing this tab";
		$keys['pos']['notes'] = "The position of this tab on the workspace page; `0` is first";
		
		return $keys;
	}
	
	function getDaoFieldsFromKeyAndValue($key, $value, &$out_fields, &$error) {
		$dict_key = DevblocksPlatform::strLower($key);
		switch($dict_key) {
			
			case 'params':
				if(!is_array($value)) {
					$error = 'must be an object.';
					return false;
				}
				
				if(false == ($json = json_encode($value))) {
					$error = 'could not be JSON encoded.';
					return false;
				}
				
				$out_fields[DAO_WorkspaceTab::PARAMS_JSON] = $json;
				break;
		}
		
		return true;
	}
	
	function lazyLoadGetKeys() {
		$lazy_keys = parent::lazyLoadGetKeys();
		
		$lazy_keys['widgets'] = [
			'label' => 'Widgets',
			'type' => 'Records',
		];
		
		$lazy_keys['widgets_data'] = [
			'label' => 'Widgets Data',
			'type' => 'HashMap',
		];
		
		$lazy_keys['worklists'] = [
			'label' => 'Worklists',
			'type' => 'Records',
		];
		
		return $lazy_keys;
	}
	
	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_WORKSPACE_TAB;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = [];
		
		if(!$is_loaded) {
			$labels = [];
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true, true);
		}
		
		switch($token) {
			case 'links':
				$links = $this->_lazyLoadLinks($context, $context_id);
				$values = array_merge($values, $links);
				break;
			
			case 'widgets':
				$values = $dictionary;

				if(!isset($values['widgets']))
					$values['widgets'] = [];
				
				$widgets = DAO_WorkspaceWidget::getByTab($context_id);

				if(is_array($widgets))
				foreach($widgets as $widget) { /* @var $widget Model_WorkspaceWidget */
					$widget_labels = [];
					$widget_values = [];
					CerberusContexts::getContext(CerberusContexts::CONTEXT_WORKSPACE_WIDGET, $widget, $widget_labels, $widget_values, null, true);
					$values['widgets'][] = $widget_values;
				}
				break;
			
			case 'widgets_data':
				$values = $dictionary;
				
				if(!isset($values['widgets']))
					$values = self::lazyLoadContextValues('widgets', $values);
				
				if(!isset($values['widgets']))
					break;
				
				$widgets = DAO_WorkspaceWidget::getByTab($context_id);
				
				if(is_array($values['widgets']))
				foreach($values['widgets'] as $k => $widget) {
					if(!isset($widgets[$widget['id']]))
						continue;
				
					$widget_ext = Extension_WorkspaceWidget::get($widget['extension_id']);
					
					$values['widgets'][$k]['data'] = false;
					
					if(!($widget_ext instanceof ICerbWorkspaceWidget_ExportData))
						continue;
					
					@$json = json_decode($widget_ext->exportData($widgets[$widget['id']], 'json'), true);

					if(!is_array($json))
						continue;
					
					// Remove redundant data
					if(isset($json['widget'])) {
						unset($json['widget']['label']);
						unset($json['widget']['version']);
					}
					
					$values['widgets'][$k]['data'] = isset($json['widget']) ? $json['widget'] : $json;
				}
				break;
				
			case 'worklists':
				$values = $dictionary;

				if(!isset($values['worklists']))
					$values['worklists'] = [];
				
				$worklists = DAO_WorkspaceList::getByTab($context_id);

				if(is_array($worklists))
				foreach($worklists as $worklist) { /* @var $worklist Model_WorkspaceList */
					// [TODO] Use dictionaries here
					$values['worklists'][] = [
						'id' => $worklist->id,
						'title' => $worklist->name,
						'context' => $worklist->context,
					];
				}
				break;
			
			default:
				if(DevblocksPlatform::strStartsWith($token, 'custom_')) {
					$fields = $this->_lazyLoadCustomFields($token, $context, $context_id);
					$values = array_merge($values, $fields);
				}
				break;
		}
		
		return $values;
	}
	
	function getChooserView($view_id=null) {
		if(empty($view_id))
			$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);
	
		// View
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Workspace Tab';
		/*
		$view->addParams(array(
			SearchFields_WorkspaceTab::UPDATED_AT => new DevblocksSearchCriteria(SearchFields_WorkspaceTab::UPDATED_AT,'=',0),
		), true);
		*/
		$view->renderSortBy = SearchFields_WorkspaceTab::NAME;
		$view->renderSortAsc = true;
		$view->renderLimit = 10;
		$view->renderTemplate = 'contextlinks_chooser';
		
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=[], $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Workspace Tab';
		
		$params_req = [];
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_WorkspaceTab::VIRTUAL_CONTEXT_LINK,'in',array($context.':'.$context_id)),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='', $edit=false) {
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('view_id', $view_id);
		
		$context = CerberusContexts::CONTEXT_WORKSPACE_TAB;
		
		$model = new Model_WorkspaceTab();
		
		if(!empty($context_id)) {
			$model = DAO_WorkspaceTab::get($context_id);
			
		} else {
			if(!empty($edit)) {
				$tokens = explode(' ', trim($edit));
				
				foreach($tokens as $token) {
					@list($k,$v) = explode(':', $token);
					
					if($v)
					switch($k) {
						case 'page.id':
							$model->workspace_page_id = intval($v);
							break;
					}
				}
			}
		}
		
		if(empty($context_id) || $edit) {
			if(isset($model))
				$tpl->assign('model', $model);
			
			if($context_id && !$model)
				return;
			
			// Custom fields
			$custom_fields = DAO_CustomField::getByContext($context, false);
			$tpl->assign('custom_fields', $custom_fields);
	
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds($context, $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
			
			$types = Model_CustomField::getTypes();
			$tpl->assign('types', $types);
			
			// Tab extensions
			$tab_extensions = Extension_WorkspaceTab::getAll(false);
			// [TODO] Translate first
			DevblocksPlatform::sortObjects($tab_extensions, 'params->[label]');
			$tpl->assign('tab_extensions', $tab_extensions);
			
			// Library
			
			if(empty($context_id)) {
				$packages = DAO_PackageLibrary::getByPoint('workspace_tab');
				$tpl->assign('packages', $packages);
			}
			
			// View
			$tpl->assign('id', $context_id);
			$tpl->assign('view_id', $view_id);
			$tpl->display('devblocks:cerberusweb.core::internal/workspaces/tabs/peek_edit.tpl');
			
		} else {
			// Links
			$links = array(
				$context => array(
					$context_id => 
						DAO_ContextLink::getContextLinkCounts(
							$context,
							$context_id,
							[]
						),
				),
			);
			$tpl->assign('links', $links);
			
			// Timeline
			if($context_id) {
				$timeline_json = Page_Profiles::getTimelineJson(Extension_DevblocksContext::getTimelineComments($context, $context_id));
				$tpl->assign('timeline_json', $timeline_json);
			}

			// Context
			if(false == ($context_ext = Extension_DevblocksContext::get($context)))
				return;
			
			// Dictionary
			$labels = [];
			$values = [];
			CerberusContexts::getContext($context, $model, $labels, $values, '', true, false);
			$dict = DevblocksDictionaryDelegate::instance($values);
			$tpl->assign('dict', $dict);
			
			$properties = $context_ext->getCardProperties();
			$tpl->assign('properties', $properties);
			
			// Card search buttons
			$search_buttons = $context_ext->getCardSearchButtons($dict, []);
			$tpl->assign('search_buttons', $search_buttons);
			
			$tab_counts = array(
				'widgets' => DAO_WorkspaceWidget::countByWorkspaceTabId($context_id),
				'worklists' => DAO_WorkspaceList::countByWorkspaceTabId($context_id),
			);
			$tpl->assign('tab_counts', $tab_counts);
			
			$tpl->display('devblocks:cerberusweb.core::internal/workspaces/tabs/peek.tpl');
		}
	}
};