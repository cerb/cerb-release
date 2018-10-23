<?php
/************************************************************************
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

class DAO_WorkerRole extends Cerb_ORMHelper {
	const ID = 'id';
	const NAME = 'name';
	const PARAMS_JSON = 'params_json';
	const PRIVS_JSON = 'privs_json';
	const UPDATED_AT = 'updated_at';
	
	const _CACHE_ROLES_ALL = 'ch_roles_all';
	const _CACHE_WORKER_PRIVS_PREFIX = 'ch_privs_worker_';
	const _CACHE_WORKER_ROLES_PREFIX = 'ch_roles_worker_';
	
	private function __construct() {}
	
	static function getFields() {
		$validation = DevblocksPlatform::services()->validation();
		
		$validation
			->addField(self::ID)
			->id()
			->setEditable(false)
			;
		$validation
			->addField(self::NAME)
			->string()
			->setRequired(true)
			;
		$validation
			->addField(self::PARAMS_JSON)
			->string()
			->setMaxLength(16777215)
			;
		$validation
			->addField(self::PRIVS_JSON)
			->string()
			->setMaxLength(65535)
			;
		$validation
			->addField(self::UPDATED_AT)
			->timestamp()
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
		
		$sql = sprintf("INSERT INTO worker_role () ".
			"VALUES ()"
		);
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields, $check_deltas=true) {
		if(!is_array($ids))
			$ids = array($ids);
		
		if(!isset($fields[self::UPDATED_AT]))
			$fields[self::UPDATED_AT] = time();
		
		$context = CerberusContexts::CONTEXT_ROLE;
		self::_updateAbstract($context, $ids, $fields);
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
			
			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges(CerberusContexts::CONTEXT_ROLE, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'worker_role', $fields);
			
			// Send events
			if($check_deltas) {
				
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::services()->event();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.role.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_ROLE, $batch_ids);
			}
		}
		
		// Clear cache
		self::clearCache();
	}
	
	static public function onBeforeUpdateByActor($actor, &$fields, $id=null, &$error=null) {
		if(!CerberusContexts::isActorAnAdmin($actor)) {
			$error = DevblocksPlatform::translate('error.core.no_acl.admin');
			return false;
		}
		
		$context = CerberusContexts::CONTEXT_ROLE;
		
		if(!self::_onBeforeUpdateByActorCheckContextPrivs($actor, $context, $id, $error))
			return false;
		
		return true;
	}
	
	static function getRolesByWorker($worker_id, $nocache=false) {
		$cache = DevblocksPlatform::services()->cache();
		
		if($nocache || null === ($roles = $cache->load(self::_CACHE_WORKER_ROLES_PREFIX.$worker_id))) {
			$worker = DAO_Worker::get($worker_id);
			$memberships = $worker->getMemberships();
			$all_roles = DAO_WorkerRole::getAll();
			$roles = [];

			foreach($all_roles as $role_id => $role) {
				if(
					// If this applies to everyone
					'all' == $role->params['who'] ||
					(
						// ... or any group this worker is in
						'groups' == $role->params['who'] &&
						($in_groups = array_intersect(array_keys($memberships), $role->params['who_list'])) &&
						!empty($in_groups)
					) ||
					(
						// ... or this worker is on the list
						'workers' == $role->params['who'] &&
						in_array($worker_id, $role->params['who_list'])
					)
				) {
					$roles[$role_id] = $role;
				}
			}
			
			if(!is_array($roles))
				return false;

			$cache->save($roles, self::_CACHE_WORKER_ROLES_PREFIX.$worker_id);
		}
		
		return $roles;
	}
	
	static function getCumulativePrivsByWorker($worker_id, $nocache=false) {
		$cache = DevblocksPlatform::services()->cache();

		if($nocache || null === ($privs = $cache->load(self::_CACHE_WORKER_PRIVS_PREFIX.$worker_id))) {
			if(false === ($worker = DAO_Worker::get($worker_id)))
				return false;
			
			if(false === ($worker->getMemberships()))
				return false;
			
			if(false === ($roles = DAO_WorkerRole::getRolesByWorker($worker_id)))
				return false;
			
			$privs = [];
			
			foreach($roles as $role) {
				switch($role->params['what']) {
					case 'all':
						$privs = ['*' => []];
						$cache->save($privs, self::_CACHE_WORKER_PRIVS_PREFIX.$worker_id);
						return;
						break;
						
					case 'itemized':
						$role_privs = array_fill_keys($role->privs, []);
						$privs = array_merge($privs, $role_privs);
						break;
				}
			}
			
			$cache->save($privs, self::_CACHE_WORKER_PRIVS_PREFIX.$worker_id);
		}
		
		return $privs;
	}
	
	static function getAll($nocache=false) {
		$cache = DevblocksPlatform::services()->cache();
		if($nocache || null === ($roles = $cache->load(self::_CACHE_ROLES_ALL))) {
			$roles = DAO_WorkerRole::getWhere(
				null,
				DAO_WorkerRole::NAME,
				true,
				null,
				Cerb_ORMHelper::OPT_GET_MASTER_ONLY
			);
			
			if(!is_array($roles))
				return false;
			
			$cache->save($roles, self::_CACHE_ROLES_ALL);
		}
		
		return $roles;
	}
	
	/**
	 * @param string $where
	 * @return Model_WorkerRole[]
	 */
	static function getWhere($where=null, $sortBy=DAO_WorkerRole::NAME, $sortAsc=true, $limit=null, $options=null) {
		$db = DevblocksPlatform::services()->database();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, name, params_json, privs_json, updated_at ".
			"FROM worker_role ".
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
	 * @return Model_WorkerRole	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		$objects = DAO_WorkerRole::getAll();
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	/**
	 * 
	 * @param array $ids
	 * @return Model_WorkerRole[]
	 */
	static function getIds($ids) {
		if(!is_array($ids))
			$ids = array($ids);

		if(empty($ids))
			return [];

		if(!method_exists(get_called_class(), 'getWhere'))
			return [];

		$ids = DevblocksPlatform::importVar($ids, 'array:integer');

		$models = [];

		$results = static::getWhere(sprintf("id IN (%s)",
			implode(',', $ids)
		));

		// Sort $models in the same order as $ids
		foreach($ids as $id) {
			if(isset($results[$id]))
				$models[$id] = $results[$id];
		}

		unset($results);

		return $models;
	}	
	
	/**
	 * @param resource $rs
	 * @return Model_WorkerRole[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = [];
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_WorkerRole();
			$object->id = intval($row['id']);
			$object->name = $row['name'];
			$object->updated_at = intval($row['updated_at']);
			
			@$params = json_decode($row['params_json'], true) or [];
			$object->params = $params;
			
			@$privs = json_decode($row['privs_json'], true) or [];
			$object->privs = $privs;
			
			$objects[$object->id] = $object;
		}
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function random() {
		return self::_getRandom('worker_role');
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::services()->database();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM worker_role WHERE id IN (%s)", $ids_list));

		self::clearCache();
		self::clearWorkerCache();
		
		// Fire event
		$eventMgr = DevblocksPlatform::services()->event();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => CerberusContexts::CONTEXT_ROLE,
					'context_ids' => $ids
				)
			)
		);
		
		return true;
	}
	
	static function clearCache() {
		$cache = DevblocksPlatform::services()->cache();
		$cache->remove(self::_CACHE_ROLES_ALL);
	}
	
	static function clearWorkerCache($worker_id=null) {
		$cache = DevblocksPlatform::services()->cache();
		
		if(!empty($worker_id)) {
			$cache->remove(self::_CACHE_WORKER_PRIVS_PREFIX.$worker_id);
			$cache->remove(self::_CACHE_WORKER_ROLES_PREFIX.$worker_id);
		} else {
			$workers = DAO_Worker::getAll();
			foreach(array_keys($workers) as $worker_id) {
				$cache->remove(self::_CACHE_WORKER_PRIVS_PREFIX.$worker_id);
				$cache->remove(self::_CACHE_WORKER_ROLES_PREFIX.$worker_id);
			}
		}
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_WorkerRole::getFields();
		
		list(,$wheres) = parent::_parseSearchParams($params, $columns, 'SearchFields_WorkerRole', $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"worker_role.id as %s, ".
			"worker_role.name as %s, ".
			"worker_role.params_json as %s, ".
			"worker_role.updated_at as %s ",
				SearchFields_WorkerRole::ID,
				SearchFields_WorkerRole::NAME,
				SearchFields_WorkerRole::PARAMS_JSON,
				SearchFields_WorkerRole::UPDATED_AT
			);
			
		$join_sql = "FROM worker_role ";
		
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields, $select_sql, 'SearchFields_WorkerRole');
	
		return array(
			'primary_table' => 'worker_role',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'sort' => $sort_sql,
		);
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
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		$results = [];
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_WorkerRole::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					"SELECT COUNT(worker_role.id) ".
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}
};

class Model_WorkerRole {
	public $id;
	public $name;
	public $params = [];
	public $privs = [];
	public $updated_at;
	
	function getWorkerIds() {
		@$who = $this->params['who'];
		@$who_list = $this->params['who_list'];
		
		switch($who) {
			case 'all':
				$workers = DAO_Worker::getAllActive();
				return array_keys($workers);
				break;
				
			case 'groups':
				$groups = DAO_Group::getAll();
				$ids = [];
				
				if(is_array($who_list))
				foreach($who_list as $group_id) {
					@$group = $groups[$group_id];
					
					if(!$group)
						continue;
					
					$ids += array_keys($group->getMembers());
				}
				return $ids;
				break;
				
			case 'workers':
				if(is_array($who_list))
					return $who_list;
				break;
		}
		
		return [];
	}
};

class SearchFields_WorkerRole extends DevblocksSearchFields {
	const ID = 'w_id';
	const NAME = 'w_name';
	const PARAMS_JSON = 'w_params_json';
	const UPDATED_AT = 'w_updated_at';

	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_HAS_FIELDSET = '*_has_fieldset';
	
	static private $_fields = null;
	
	static function getPrimaryKey() {
		return 'worker_role.id';
	}
	
	static function getCustomFieldContextKeys() {
		return array(
			'' => new DevblocksSearchFieldContextKeys('worker_role.id', self::ID),
		);
	}
	
	static function getWhereSQL(DevblocksSearchCriteria $param) {
		switch($param->field) {
			case self::VIRTUAL_CONTEXT_LINK:
				return self::_getWhereSQLFromContextLinksField($param, CerberusContexts::CONTEXT_ROLE, self::getPrimaryKey());
				break;
				
			case self::VIRTUAL_HAS_FIELDSET:
				return self::_getWhereSQLFromVirtualSearchSqlField($param, CerberusContexts::CONTEXT_CUSTOM_FIELDSET, sprintf('SELECT context_id FROM context_to_custom_fieldset WHERE context = %s AND custom_fieldset_id IN (%%s)', Cerb_ORMHelper::qstr(CerberusContexts::CONTEXT_ROLE)), self::getPrimaryKey());
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
		}
		
		return parent::getFieldForSubtotalKey($key, $context, $query_fields, $search_fields, $primary_key);
	}
	
	static function getLabelsForKeyValues($key, $values) {
		switch($key) {
			case SearchFields_WorkerRole::ID:
				$models = DAO_WorkerRole::getIds($values);
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
			self::ID => new DevblocksSearchField(self::ID, 'worker_role', 'id', $translate->_('common.id'), null, true),
			self::NAME => new DevblocksSearchField(self::NAME, 'worker_role', 'name', $translate->_('common.name'), null, true),
			self::UPDATED_AT => new DevblocksSearchField(self::UPDATED_AT, 'worker_role', 'updated_at', $translate->_('common.updated'), null, true),

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

class View_WorkerRole extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'worker_roles';

	function __construct() {
		$this->id = self::DEFAULT_ID;
		$this->name = DevblocksPlatform::translateCapitalized('common.roles');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_WorkerRole::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_WorkerRole::NAME,
			SearchFields_WorkerRole::UPDATED_AT,
		);

		$this->addColumnsHidden(array(
			SearchFields_WorkerRole::PARAMS_JSON,
			SearchFields_WorkerRole::VIRTUAL_CONTEXT_LINK,
			SearchFields_WorkerRole::VIRTUAL_HAS_FIELDSET,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_WorkerRole::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		
		$this->_lazyLoadCustomFieldsIntoObjects($objects, 'SearchFields_WorkerRole');
		
		return $objects;
	}
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_WorkerRole', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_WorkerRole', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = [];

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Virtuals
				case SearchFields_WorkerRole::VIRTUAL_CONTEXT_LINK:
				case SearchFields_WorkerRole::VIRTUAL_HAS_FIELDSET:
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
		$context = CerberusContexts::CONTEXT_ROLE;

		if(!isset($fields[$column]))
			return [];
		
		switch($column) {
			case SearchFields_WorkerRole::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn($context, $column);
				break;

			case SearchFields_WorkerRole::VIRTUAL_HAS_FIELDSET:
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
		$search_fields = SearchFields_WorkerRole::getFields();
	
		$fields = array(
			'text' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_WorkerRole::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'fieldset' =>
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_WorkerRole::VIRTUAL_HAS_FIELDSET),
					'examples' => [
						['type' => 'search', 'context' => CerberusContexts::CONTEXT_CUSTOM_FIELDSET, 'qr' => 'context:' . CerberusContexts::CONTEXT_ROLE],
					]
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_WorkerRole::ID),
					'examples' => [
						['type' => 'chooser', 'context' => CerberusContexts::CONTEXT_ROLE, 'q' => ''],
					]
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_WorkerRole::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
		);
		
		// Add quick search links
		
		$fields = self::_appendVirtualFiltersFromQuickSearchContexts('links', $fields, 'links', SearchFields_WorkerRole::VIRTUAL_CONTEXT_LINK);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_ROLE, $fields, null);
		
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
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_ROLE);
		$tpl->assign('custom_fields', $custom_fields);

		$tpl->assign('view_template', 'devblocks:cerberusweb.core::internal/roles/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteriaParam($param) {
		$field = $param->field;

		switch($field) {
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		switch($key) {
			case SearchFields_WorkerRole::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_WorkerRole::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_WorkerRole::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_WorkerRole::NAME:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_WorkerRole::ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_WorkerRole::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',[]);
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_WorkerRole::VIRTUAL_HAS_FIELDSET:
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

class Context_WorkerRole extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek {
	const ID = 'cerberusweb.contexts.role';
	
	static function isReadableByActor($models, $actor) {
		// Everyone can read
		return CerberusContexts::allowEverything($models);
	}
	
	static function isWriteableByActor($models, $actor) {
		// Only admins can edit modify
		
		if(false == ($actor = CerberusContexts::polymorphActorToDictionary($actor)))
			CerberusContexts::denyEverything($models);
		
		if(CerberusContexts::isActorAnAdmin($actor))
			return CerberusContexts::allowEverything($models);
		
		return CerberusContexts::denyEverything($models);
	}
	
	function getRandom() {
		return DAO_WorkerRole::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::services()->url();
		$url = $url_writer->writeNoProxy('c=profiles&type=role&id='.$context_id, true);
		return $url;
	}
	
	function profileGetFields($model=null) {
		$properties = [];
		
		if(is_null($model))
			$model = new Model_WorkerRole();
		
		$properties['name'] = array(
			'label' => DevblocksPlatform::translateCapitalized('common.name'),
			'type' => Model_CustomField::TYPE_LINK,
			'value' => $model->id,
			'params' => [
				'context' => self::ID,
			],
		);
		
		$properties['updated_at'] = [
			'label' => DevblocksPlatform::translateCapitalized('common.updated'),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $model->updated_at,
		];
		
		return $properties;
	}
	
	function getMeta($context_id) {
		$url_writer = DevblocksPlatform::services()->url();
		
		if(null == ($worker_role = DAO_WorkerRole::get($context_id)))
			return false;
		
		$who = sprintf("%d-%s",
			$worker_role->id,
			DevblocksPlatform::strToPermalink($worker_role->name)
		);
		
		return [
			'id' => $worker_role->id,
			'name' => $worker_role->name,
			'permalink' => $url_writer->writeNoProxy('c=profiles&type=role&who='.$who, true),
			'updated' => $worker_role->updated_at,
		];
	}
	
	function getDefaultProperties() {
		return [
			'updated_at'
		];
	}
	
	function getContext($role, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Role:';
			
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_ROLE);
		
		// Polymorph
		if(is_numeric($role)) {
			$role = DAO_WorkerRole::get($role);
		} elseif($role instanceof Model_WorkerRole) {
			// It's what we want already.
		} elseif(is_array($role)) {
			$role = Cerb_ORMHelper::recastArrayToModel($role, 'Model_WorkerRole');
		} else {
			$role = null;
		}
			
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'name' => $prefix.$translate->_('common.name'),
			'updated_at' => $prefix.$translate->_('common.updated'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
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
		
		$token_values['_context'] = CerberusContexts::CONTEXT_ROLE;
		$token_values['_types'] = $token_types;
		
		// Worker token values
		if(null != $role) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $role->name;
			$token_values['id'] = $role->id;
			$token_values['name'] = $role->name;
			$token_values['updated_at'] = $role->updated_at;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($role, $token_values);
			
			// URL
			$url_writer = DevblocksPlatform::services()->url();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=role&id=%d-%s",$role->id, DevblocksPlatform::strToPermalink($role->name)), true);
		}
		
		return true;
	}
	
	function getKeyToDaoFieldMap() {
		return [
			'id' => DAO_WorkerRole::ID,
			'links' => '_links',
			'name' => DAO_WorkerRole::NAME,
			'updated_at' => DAO_WorkerRole::UPDATED_AT,
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
		
		return $keys;
	}
	
	function getDaoFieldsFromKeyAndValue($key, $value, &$out_fields, &$error) {
		switch(DevblocksPlatform::strLower($key)) {
			case 'links':
				$this->_getDaoFieldsLinks($value, $out_fields, $error);
				break;
				
			case 'params':
				if(!is_array($value)) {
					$error = 'must be an object.';
					return false;
				}
				
				if(false == ($json = json_encode($value))) {
					$error = 'could not be JSON encoded.';
					return false;
				}
				
				$out_fields[DAO_WorkerRole::PARAMS_JSON] = $json;
				break;
		}
		
		return true;
	}
	
	function lazyLoadGetKeys() {
		$lazy_keys = parent::lazyLoadGetKeys();
		return $lazy_keys;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_ROLE;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = [];
		
		if(!$is_loaded) {
			$labels = [];
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true, true);
		}
		
		switch($token) {
			// [TODO]
			case 'privileges':
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
		$view->name = DevblocksPlatform::translateCapitalized('common.roles');
		$view->addParams(array(
			//SearchFields_Worker::IS_DISABLED => new DevblocksSearchCriteria(SearchFields_Worker::IS_DISABLED,'=',0),
		), true);
		$view->renderLimit = 10;
		$view->renderTemplate = 'contextlinks_chooser';
		
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=[], $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = DevblocksPlatform::translateCapitalized('common.roles');
		
		$params_req = [];
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_WorkerRole::VIRTUAL_CONTEXT_LINK,'in',array($context.':'.$context_id)),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	private function _getPluginPrivileges() {
		$plugins = DevblocksPlatform::getPluginRegistry();
		$acls = DevblocksPlatform::getAclRegistry();
		
		unset($plugins['devblocks.core']);
		
		$plugins_acl = [];
		
		if(is_array($plugins))
		foreach($plugins as $plugin_id => $plugin) {
			$plugins_acl[$plugin_id] = [
				'label' => $plugin->name,
				'privs' => [],
			];
		}
		
		if(is_array($acls))
		foreach($acls as $acl) {
			$plugin_id = $acl->plugin_id;
			
			if(empty($plugin_id) || !isset($plugins_acl[$plugin_id]))
				continue;
			
			$plugins_acl[$plugin_id]['privs'][$acl->id] = DevblocksPlatform::translate($acl->label);
		}
		
		// Sort privs within each plugin
		if(is_array($plugins_acl))
		foreach($plugins_acl as &$plugin) {
			asort($plugin['privs']);
		}
		
		// Sort plugins
		DevblocksPlatform::sortObjects($plugins_acl, '[label]');
		
		// Move Cerb back to the top
		$cerb_acl = $plugins_acl['cerberusweb.core'];
		unset($plugins_acl['cerberusweb.core']);
		$keys = array_keys($plugins_acl);
		$values = array_values($plugins_acl);
		array_unshift($keys, 'cerberusweb.core');
		array_unshift($values, $cerb_acl);
		$plugins_acl = array_combine($keys, $values);
		
		return $plugins_acl;
	}
	
	private function _formatCorePrivileges(array $core_acl) {
		$result = [];
		
		if(isset($core_acl['privs']) && is_array($core_acl['privs']))
		foreach($core_acl['privs'] as $priv => $label) {
			$matches = [];
			if(mb_ereg('^\[(.*?)\] (.*?)$', $label, $matches)) {
				$section = $matches[1];
				$label = $matches[2];
				
				if(!isset($result[$section]))
					$result[$section] = [
						'label' => $section,
						'privs' => [],
					];
				
				$result[$section]['privs'][$priv] = $label;
				
			} else {
				$result[''] = [
					'label' => '',
					'privs' => [],
				];
				
				$result['']['privs'][$priv] = $label;
			}
		}
		
		return $result;
	}
	
	function renderPeekPopup($context_id=0, $view_id='', $edit=false) {
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('view_id', $view_id);
		
		$context = CerberusContexts::CONTEXT_ROLE;
		$model = null;
		
		if(!empty($context_id)) {
			$model = DAO_WorkerRole::get($context_id);
		}
		
		if(empty($context_id) || $edit) {
			if(!isset($model))
				$model = new Model_WorkerRole();
				
			$plugins_acl = $this->_getPluginPrivileges();
			
			$core_acl = $plugins_acl['cerberusweb.core'];
			unset($plugins_acl['cerberusweb.core']);
			$tpl->assign('plugins_acl', $plugins_acl);
			
			$core_acl = $this->_formatCorePrivileges($core_acl);
			$tpl->assign('core_acl', $core_acl);
			
			$groups = DAO_Group::getAll();
			$tpl->assign('groups', $groups);
			
			$workers = DAO_Worker::getAllActive();
			$tpl->assign('workers', $workers);
			
			if(is_array($model->privs)) {
				$role_privs = array_fill_keys($model->privs, []);
			} else {
				$role_privs = [];
				$model->privs = $role_privs;
			}
			
			$tpl->assign('role_privs', $role_privs);
			
			// Contexts
			$contexts = Extension_DevblocksContext::getAll(false);
			$tpl->assign('contexts', $contexts);
			
			// Custom fields
			$custom_fields = DAO_CustomField::getByContext($context, false);
			$tpl->assign('custom_fields', $custom_fields);
	
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds($context, $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
			
			$types = Model_CustomField::getTypes();
			$tpl->assign('types', $types);
			
			$tpl->assign('model', $model);
			
			// View
			$tpl->assign('id', $context_id);
			$tpl->assign('view_id', $view_id);
			$tpl->display('devblocks:cerberusweb.core::internal/roles/peek_edit.tpl');
			
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
			
			$tpl->display('devblocks:cerberusweb.core::internal/roles/peek.tpl');
		}
	}
	
}