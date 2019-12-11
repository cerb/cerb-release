<?php
class DAO_ProjectBoardColumn extends Cerb_ORMHelper {
	const BOARD_ID = 'board_id';
	const CARDS_JSON = 'cards_json';
	const ID = 'id';
	const NAME = 'name';
	const PARAMS_JSON = 'params_json';
	const UPDATED_AT = 'updated_at';
	
	private function __construct() {}
	
	static function getFields() {
		$validation = DevblocksPlatform::services()->validation();
		
		// int(10) unsigned
		$validation
			->addField(self::BOARD_ID)
			->id()
			->setRequired(true)
			->addValidator($validation->validators()->contextId(Context_ProjectBoard::ID))
			;
		// mediumtext
		$validation
			->addField(self::CARDS_JSON)
			->string()
			->setMaxLength(16777215)
			;
		// int(10) unsigned
		$validation
			->addField(self::ID)
			->id()
			->setEditable(false)
			;
		// varchar(255)
		$validation
			->addField(self::NAME)
			->string()
			->setMaxLength(255)
			->setRequired(true)
			;
		// text
		$validation
			->addField(self::PARAMS_JSON)
			->string()
			->setMaxLength(65535)
			;
		// int(10) unsigned
		$validation
			->addField(self::UPDATED_AT)
			->timestamp()
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
		
		$sql = "INSERT INTO project_board_column () VALUES ()";
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
		
		self::_updateAbstract(Context_ProjectBoardColumn::ID, $ids, $fields);
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
				
			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges(Context_ProjectBoardColumn::ID, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'project_board_column', $fields);
			
			// Send events
			if($check_deltas) {
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::services()->event();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.project_board_column.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(Context_ProjectBoardColumn::ID, $batch_ids);
			}
		}
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('project_board_column', $fields, $where);
	}
	
	static public function onBeforeUpdateByActor($actor, &$fields, $id=null, &$error=null) {
		$context = Context_ProjectBoardColumn::ID;
		
		if(!self::_onBeforeUpdateByActorCheckContextPrivs($actor, $context, $id, $error))
			return false;
		
		// Actor must have access to modify the project board
		if(isset($fields[self::BOARD_ID])) {
			$board_id = $fields[self::BOARD_ID];
			
			if(!$board_id || !Context_ProjectBoard::isWriteableByActor($board_id, $actor)) {
				$error = "You do not have permission to add columns to this project board.";
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_ProjectBoardColumn[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null, $options=null) {
		$db = DevblocksPlatform::services()->database();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, name, board_id, updated_at, params_json, cards_json ".
			"FROM project_board_column ".
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
	 *
	 * @param bool $nocache
	 * @return Model_ProjectBoardColumn[]
	 */
	static function getAll($nocache=false) {
		//$cache = DevblocksPlatform::services()->cache();
		//if($nocache || null === ($objects = $cache->load(self::_CACHE_ALL))) {
			$objects = self::getWhere(null, DAO_ProjectBoardColumn::NAME, true, null, Cerb_ORMHelper::OPT_GET_MASTER_ONLY);
			
			//if(!is_array($objects))
			//	return false;
				
			//$cache->save($objects, self::_CACHE_ALL);
		//}
		
		return $objects;
	}

	/**
	 * @param integer $id
	 * @return Model_ProjectBoardColumn	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	static function countByBoardId($ticket_id) {
		$db = DevblocksPlatform::services()->database();
		
		$sql = sprintf("SELECT count(board_id) FROM project_board_column WHERE board_id = %d",
			$ticket_id
		);
		return intval($db->GetOneSlave($sql));
	}
	
	static function getByBoardId($board_id) {
		$columns = self::getAll();
		
		return array_filter($columns, function($column) use ($board_id) {
			if($column->board_id == $board_id)
				return true;
			
			return false;
		});
	}
	
	/**
	 * 
	 * @param array $ids
	 * @return Model_ProjectBoardColumn[]
	 */
	static function getIds($ids) {
		return parent::getIds($ids);
	}
	
	/**
	 * @param resource $rs
	 * @return Model_ProjectBoardColumn[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_ProjectBoardColumn();
			$object->id = intval($row['id']);
			$object->name = $row['name'];
			$object->board_id = intval($row['board_id']);
			$object->updated_at = intval($row['updated_at']);
			
			@$json = json_decode($row['params_json'], true) ?: [];
			$object->params = $json;
			
			@$json = json_decode($row['cards_json'], true) ?: [];
			$object->cards = $json;
			
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function random() {
		return self::_getRandom('project_board_column');
	}
	
	static function delete($ids) {
		if(!is_array($ids))
			$ids = array($ids);
		
		$db = DevblocksPlatform::services()->database();
		
		$ids = DevblocksPlatform::sanitizeArray($ids, 'int');
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM project_board_column WHERE id IN (%s)", $ids_list));
		
		// Fire event
		$eventMgr = DevblocksPlatform::services()->event();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => Context_ProjectBoardColumn::ID,
					'context_ids' => $ids
				)
			)
		);
		
		return true;
	}
	
	static function deleteByProjectIds($ids) {
		if(!is_array($ids))
			$ids = array($ids);
		
		$ids = DevblocksPlatform::sanitizeArray($ids, 'int');
		
		if(empty($ids))
			return;
		
		foreach($ids as $id) {
			if(false == ($columns = DAO_ProjectBoardColumn::getByBoardId($id)))
				continue;
			
			DAO_ProjectBoardColumn::delete(array_keys($columns));
		}
		
		return true;
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_ProjectBoardColumn::getFields();
		
		list(,$wheres) = parent::_parseSearchParams($params, $columns, 'SearchFields_ProjectBoardColumn', $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"project_board_column.id as %s, ".
			"project_board_column.name as %s, ".
			"project_board_column.board_id as %s, ".
			"project_board_column.updated_at as %s ",
				SearchFields_ProjectBoardColumn::ID,
				SearchFields_ProjectBoardColumn::NAME,
				SearchFields_ProjectBoardColumn::BOARD_ID,
				SearchFields_ProjectBoardColumn::UPDATED_AT
			);
			
		$join_sql = "FROM project_board_column ";
		
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields, $select_sql, 'SearchFields_ProjectBoardColumn');
	
		return array(
			'primary_table' => 'project_board_column',
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
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_ProjectBoardColumn::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					"SELECT COUNT(project_board_column.id) ".
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}

};

class SearchFields_ProjectBoardColumn extends DevblocksSearchFields {
	const ID = 'p_id';
	const NAME = 'p_name';
	const BOARD_ID = 'p_board_id';
	const UPDATED_AT = 'p_updated_at';
	
	const VIRTUAL_BOARD_SEARCH = '*_board_search';
	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_HAS_FIELDSET = '*_has_fieldset';
	const VIRTUAL_WATCHERS = '*_workers';
	
	static private $_fields = null;
	
	static function getPrimaryKey() {
		return 'project_board_column.id';
	}
	
	static function getCustomFieldContextKeys() {
		return array(
			'cerberusweb.contexts.project.board.column' => new DevblocksSearchFieldContextKeys('project_board_column.id', self::ID),
			'cerberusweb.contexts.project.board' => new DevblocksSearchFieldContextKeys('project_board_column.board_id', self::BOARD_ID),
		);
	}
	
	static function getWhereSQL(DevblocksSearchCriteria $param) {
		switch($param->field) {
			case self::VIRTUAL_BOARD_SEARCH:
				return self::_getWhereSQLFromVirtualSearchField($param, Context_ProjectBoard::ID, 'project_board_column.board_id');
				break;
			
			case self::VIRTUAL_CONTEXT_LINK:
				return self::_getWhereSQLFromContextLinksField($param, Context_ProjectBoardColumn::ID, self::getPrimaryKey());
				break;
			
			case self::VIRTUAL_HAS_FIELDSET:
				return self::_getWhereSQLFromVirtualSearchSqlField($param, CerberusContexts::CONTEXT_CUSTOM_FIELDSET, sprintf('SELECT context_id FROM context_to_custom_fieldset WHERE context = %s AND custom_fieldset_id IN (%%s)', Cerb_ORMHelper::qstr(Context_ProjectBoardColumn::ID)), self::getPrimaryKey());
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
			case 'board':
				$key = 'board.id';
				break;
		}
		
		return parent::getFieldForSubtotalKey($key, $context, $query_fields, $search_fields, $primary_key);
	}
	
	static function getLabelsForKeyValues($key, $values) {
		switch($key) {
			case SearchFields_ProjectBoardColumn::ID:
				$models = DAO_ProjectBoardColumn::getIds($values);
				return array_column(DevblocksPlatform::objectsToArrays($models), 'name', 'id');
				break;
				
			case SearchFields_ProjectBoardColumn::BOARD_ID:
				$models = DAO_ProjectBoard::getIds($values);
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
			self::ID => new DevblocksSearchField(self::ID, 'project_board_column', 'id', $translate->_('common.id'), Model_CustomField::TYPE_NUMBER, true),
			self::NAME => new DevblocksSearchField(self::NAME, 'project_board_column', 'name', $translate->_('common.name'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::BOARD_ID => new DevblocksSearchField(self::BOARD_ID, 'project_board_column', 'board_id', $translate->_('projects.common.board'), null, true),
			self::UPDATED_AT => new DevblocksSearchField(self::UPDATED_AT, 'project_board_column', 'updated_at', $translate->_('common.updated'), Model_CustomField::TYPE_DATE, true),

			self::VIRTUAL_BOARD_SEARCH => new DevblocksSearchField(self::VIRTUAL_BOARD_SEARCH, '*', 'board_search', null, null, false),
			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null, false),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null, false),
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', 'workers', $translate->_('common.watchers'), 'WS', false),
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

class Model_ProjectBoardColumn {
	public $id;
	public $name;
	public $board_id;
	public $updated_at;
	public $params;
	public $cards;
	
	function getProjectBoard() {
		return DAO_ProjectBoard::get($this->board_id);
	}
	
	function getCards() {
		$cards = [];
		$card_models = [];
		
		$links = DAO_ContextLink::getAllContextLinks(Context_ProjectBoardColumn::ID, $this->id);
		
		// Find all the distinct models
		foreach($links as $link) {
			if(!array_key_exists($link->context, $card_models))
				$card_models[$link->context] = [];
				
			$card_models[$link->context][] = $link->context_id;
		}
		
		foreach($card_models as $model_context => $model_ids) {
			$models = CerberusContexts::getModels($model_context, $model_ids);
			$dicts = DevblocksDictionaryDelegate::getDictionariesFromModels($models, $model_context);
			DevblocksDictionaryDelegate::bulkLazyLoad($dicts, '_label');
			$card_models[$model_context] = $dicts;
		}
		
		foreach($links as $link) {
			$row = implode(':', [$link->context, $link->context_id]);
			$key = sha1($row);
			
			if(false == (@$card = $card_models[$link->context][$link->context_id]))
				continue;
			
			// Add keys for the project board column
			$card->set('column__context', Context_ProjectBoardColumn::ID);
			$card->set('column_id', $this->id);
			
			$cards[$key] = $card;
		}
		
		unset($card_models);
		
		$sort = [];
		
		foreach($this->cards as $row) {
			$key = sha1($row);
			$sort[] = $key;
		}
		
		$sort = array_flip($sort);
		
		uksort($cards, function($a, $b) use ($sort) {
			$a_pos = isset($sort[$a]) ? $sort[$a] : -1; // PHP_INT_MAX
			$b_pos = isset($sort[$b]) ? $sort[$b] : -1;
			
			if($a_pos == $b_pos)
				return 0;
			
			return ($a_pos < $b_pos) ? -1 : 1;
		});
		
		return $cards;
	}
	
	function runDropActionsForCard($context, $context_id) {
		if(!is_array($this->params))
			return;
		
		// Trigger any built-in actions
		if(array_key_exists('actions', $this->params)) {
			// If the card is a task
			if($context == CerberusContexts::CONTEXT_TASK) {
				foreach($this->params['actions'] as $action => $action_params) {
					switch($action) {
						case 'task_status':
							if(!array_key_exists('status_id', $action_params))
								break;
								
							@$status_id = $action_params['status_id'];
								
							if(!in_array($status_id, [0,1,2]))
								break;
							
							DAO_Task::update($context_id, [
								DAO_Task::STATUS_ID => $status_id,
							]);
							break;
					}
				}
			}
		}
		
		// Setting links should trigger configured bot behaviors
		if(array_key_exists('behaviors', $this->params)) {
			@$behavior_params = $this->params['behaviors'];
			$behaviors = DAO_TriggerEvent::getIds(array_keys($behavior_params));
			
			if(is_array($behaviors))
			foreach($behaviors as $behavior) {
				$event_ext = $behavior->getEvent();
				
				// Only run events for this context
				if(@$event_ext->manifest->params['macro_context'] != $context)
					continue;
				
				call_user_func([$event_ext->manifest->class, 'trigger'], $behavior->id, $context_id, @$behavior_params[$behavior->id] ?: []);
			}
		}
	}
};

class View_ProjectBoardColumn extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'project_board_columns';

	function __construct() {
		$this->id = self::DEFAULT_ID;
		$this->name = DevblocksPlatform::translateCapitalized('projects.common.board.columns');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_ProjectBoardColumn::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_ProjectBoardColumn::NAME,
			SearchFields_ProjectBoardColumn::BOARD_ID,
			SearchFields_ProjectBoardColumn::UPDATED_AT,
		);
		
		$this->addColumnsHidden(array(
			SearchFields_ProjectBoardColumn::VIRTUAL_BOARD_SEARCH,
			SearchFields_ProjectBoardColumn::VIRTUAL_CONTEXT_LINK,
			SearchFields_ProjectBoardColumn::VIRTUAL_HAS_FIELDSET,
			SearchFields_ProjectBoardColumn::VIRTUAL_WATCHERS,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_ProjectBoardColumn::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		
		$this->_lazyLoadCustomFieldsIntoObjects($objects, 'SearchFields_ProjectBoardColumn');
		
		return $objects;
	}
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_ProjectBoardColumn', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_ProjectBoardColumn', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = [];

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Fields
				case SearchFields_ProjectBoardColumn::BOARD_ID:
					$pass = true;
					break;
					
				// Virtuals
				case SearchFields_ProjectBoardColumn::VIRTUAL_CONTEXT_LINK:
				case SearchFields_ProjectBoardColumn::VIRTUAL_HAS_FIELDSET:
				case SearchFields_ProjectBoardColumn::VIRTUAL_WATCHERS:
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
		$counts = array();
		$fields = $this->getFields();
		$context = Context_ProjectBoardColumn::ID;

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
			case SearchFields_ProjectBoardColumn::BOARD_ID:
				$label_map = function($ids) {
					$models = DAO_ProjectBoard::getIds($ids);
					return array_column(DevblocksPlatform::objectsToArrays($models), 'name', 'id');
				};
				$counts = $this->_getSubtotalCountForStringColumn($context, $column, $label_map);
				break;
				
			case SearchFields_ProjectBoardColumn::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn($context, $column);
				break;

			case SearchFields_ProjectBoardColumn::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn($context, $column);
				break;
				
			case SearchFields_ProjectBoardColumn::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn($context, $column);
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
		$search_fields = SearchFields_ProjectBoardColumn::getFields();
	
		$fields = array(
			'text' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_ProjectBoardColumn::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'board' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_ProjectBoardColumn::VIRTUAL_BOARD_SEARCH),
					'examples' => [
						['type' => 'search', 'context' => Context_ProjectBoard::ID, 'q' => ''],
					]
				),
			'board.id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_ProjectBoardColumn::BOARD_ID),
					'examples' => [
						['type' => 'chooser', 'context' => Context_ProjectBoard::ID, 'q' => ''],
					]
				),
			'fieldset' =>
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_ProjectBoardColumn::VIRTUAL_HAS_FIELDSET),
					'examples' => [
						['type' => 'search', 'context' => CerberusContexts::CONTEXT_CUSTOM_FIELDSET, 'qr' => 'context:' . Context_ProjectBoardColumn::ID],
					]
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_ProjectBoardColumn::ID),
					'examples' => [
						['type' => 'chooser', 'context' => Context_ProjectBoardColumn::ID, 'q' => ''],
					]
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_ProjectBoardColumn::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_ProjectBoardColumn::UPDATED_AT),
				),
			'watchers' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_WORKER,
					'options' => array('param_key' => SearchFields_ProjectBoardColumn::VIRTUAL_WATCHERS),
				),
		);
		
		// Add quick search links
		
		$fields = self::_appendVirtualFiltersFromQuickSearchContexts('links', $fields, 'links', SearchFields_ProjectBoardColumn::VIRTUAL_CONTEXT_LINK);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(Context_ProjectBoardColumn::ID, $fields, null);
		
		// Add is_sortable
		
		$fields = self::_setSortableQuickSearchFields($fields, $search_fields);
		
		// Sort by keys
		ksort($fields);
		
		return $fields;
	}	
	
	function getParamFromQuickSearchFieldTokens($field, $tokens) {
		switch($field) {
			case 'board':
				return DevblocksSearchCriteria::getVirtualQuickSearchParamFromTokens($field, $tokens, SearchFields_ProjectBoardColumn::VIRTUAL_BOARD_SEARCH);
				break;
				
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
		$custom_fields = DAO_CustomField::getByContext(Context_ProjectBoardColumn::ID);
		$tpl->assign('custom_fields', $custom_fields);

		$boards = DAO_ProjectBoard::getAll();
		$tpl->assign('boards', $boards);
		
		$tpl->assign('view_template', 'devblocks:cerb.project_boards::project_board_column/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? [$param->value] : $param->value;

		switch($field) {
			case SearchFields_ProjectBoardColumn::ID:
				$label_map = SearchFields_ProjectBoardColumn::getLabelsForKeyValues($field, $values);
				parent::_renderCriteriaParamString($param, $label_map);
				break;
				
			case SearchFields_ProjectBoardColumn::BOARD_ID:
				$label_map = SearchFields_ProjectBoardColumn::getLabelsForKeyValues($field, $values);
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
			case SearchFields_ProjectBoardColumn::VIRTUAL_BOARD_SEARCH:
				echo sprintf("Project matches <b>%s</b>", DevblocksPlatform::strEscapeHtml($param->value));
				break;
			
			case SearchFields_ProjectBoardColumn::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_ProjectBoardColumn::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
			
			case SearchFields_ProjectBoardColumn::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_ProjectBoardColumn::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		// [TODO] Move fields into the right data type
		switch($field) {
			case SearchFields_ProjectBoardColumn::ID:
			case SearchFields_ProjectBoardColumn::NAME:
			case SearchFields_ProjectBoardColumn::BOARD_ID:
			case SearchFields_ProjectBoardColumn::UPDATED_AT:
			case 'placeholder_string':
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case 'placeholder_number':
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case 'placeholder_date':
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_ProjectBoardColumn::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_ProjectBoardColumn::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			case SearchFields_ProjectBoardColumn::VIRTUAL_WATCHERS:
				@$worker_ids = DevblocksPlatform::importGPC($_REQUEST['worker_id'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,$oper,$worker_ids);
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

class Context_ProjectBoardColumn extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek { // IDevblocksContextImport
	const ID = 'cerberusweb.contexts.project.board.column';
	
	static function isReadableByActor($models, $actor) {
		// Everyone can read
		return CerberusContexts::allowEverything($models);
	}
	
	static function isWriteableByActor($models, $actor) {
		// Everyone can modify
		return CerberusContexts::allowEverything($models);
	}

	function getRandom() {
		return DAO_ProjectBoardColumn::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::services()->url();
		$url = $url_writer->writeNoProxy('c=profiles&type=project_board_column&id='.$context_id, true);
		return $url;
	}
	
	function profileGetFields($model=null) {
		$translate = DevblocksPlatform::getTranslationService();
		$properties = [];
		
		if(is_null($model))
			$model = new Model_ProjectBoardColumn();
		
		$properties['name'] = array(
			'label' => mb_ucfirst($translate->_('common.name')),
			'type' => Model_CustomField::TYPE_LINK,
			'value' => $model->id,
			'params' => [
				'context' => self::ID,
			],
		);
		
		$properties['board_id'] = array(
			'label' => DevblocksPlatform::translateCapitalized('projects.common.board'),
			'type' => Model_CustomField::TYPE_LINK,
			'value' => $model->board_id,
			'params' => [
				'context' => Context_ProjectBoard::ID,
			]
		);
		
		$properties['updated'] = array(
			'label' => DevblocksPlatform::translateCapitalized('common.updated'),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $model->updated_at,
		);
		
		return $properties;
	}
	
	function getMeta($context_id) {
		if(false == ($project_board_column = DAO_ProjectBoardColumn::get($context_id)))
			return [];
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($project_board_column->name);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $project_board_column->id,
			'name' => $project_board_column->name,
			'permalink' => $url,
			//'updated' => $project_board_column->updated_at, // [TODO]
		);
	}
	
	function getDefaultProperties() {
		return array(
			'board__label',
			'updated_at',
		);
	}
	
	function getContext($project_board_column, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Project Board Column:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(Context_ProjectBoardColumn::ID);

		// Polymorph
		if(is_numeric($project_board_column)) {
			$project_board_column = DAO_ProjectBoardColumn::get($project_board_column);
		} elseif($project_board_column instanceof Model_ProjectBoardColumn) {
			// It's what we want already.
		} elseif(is_array($project_board_column)) {
			$project_board_column = Cerb_ORMHelper::recastArrayToModel($project_board_column, 'Model_ProjectBoardColumn');
		} else {
			$project_board_column = null;
		}
		
		// [TODO] Column->Board
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'name' => $prefix.$translate->_('common.name'),
			'updated_at' => $prefix.$translate->_('common.updated'),
			'record_url' => $prefix.$translate->_('common.url.record'),
			'board__label' => $prefix.$translate->_('projects.common.board'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'board_id' => Model_CustomField::TYPE_NUMBER,
			'board__label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
			'updated_at' => Model_CustomField::TYPE_DATE,
			'record_url' => Model_CustomField::TYPE_URL,
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Custom field/fieldset token types
		if(false !== ($custom_field_types = $this->_getTokenTypesFromCustomFields($fields, $prefix)) && is_array($custom_field_types))
			$token_types = array_merge($token_types, $custom_field_types);
		
		// Token values
		$token_values = [];
		
		$token_values['_context'] = Context_ProjectBoardColumn::ID;
		$token_values['_types'] = $token_types;
		$token_values['board__context'] = 'cerberusweb.contexts.project.board';
		
		if($project_board_column) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $project_board_column->name;
			$token_values['board_id'] = $project_board_column->board_id;
			$token_values['id'] = $project_board_column->id;
			$token_values['name'] = $project_board_column->name;
			$token_values['updated_at'] = $project_board_column->updated_at;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($project_board_column, $token_values);
			
			// URL
			$url_writer = DevblocksPlatform::services()->url();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=project_board_column&id=%d-%s",$project_board_column->id, DevblocksPlatform::strToPermalink($project_board_column->name)), true);
		}
		
		return true;
	}
	
	function getKeyToDaoFieldMap() {
		return [
			'board_id' => DAO_ProjectBoardColumn::BOARD_ID,
			'id' => DAO_ProjectBoardColumn::ID,
			'links' => '_links',
			'name' => DAO_ProjectBoardColumn::NAME,
			'updated_at' => DAO_ProjectBoardColumn::UPDATED_AT,
		];
	}
	
	function getKeyMeta() {
		$keys = parent::getKeyMeta();
		
		$keys['cards'] = [
			'is_immutable' => false,
			'is_required' => false,
			'notes' => 'An array of record `type:id` tuples to add to this column',
			'type' => 'links',
		];
		
		$keys['params'] = [
			'is_immutable' => false,
			'is_required' => false,
			'notes' => 'JSON-encoded key/value object',
			'type' => 'object',
		];
		
		$keys['board_id']['notes'] = "The [project board](/docs/records/types/project_board/) containing this column";
		
		return $keys;
	}
	
	function getDaoFieldsFromKeyAndValue($key, $value, &$out_fields, &$error) {
		$dict_key = DevblocksPlatform::strLower($key);
		switch($dict_key) {
			case 'cards':
				if(!is_array($value)) {
					$error = 'must be an array of context:id pairs.';
					return false;
				}
				
				$links = [];
				
				foreach($value as &$tuple) {
					@list($context, $id) = explode(':', $tuple, 2);
					
					if(false == ($context_ext = Extension_DevblocksContext::getByAlias($context, false))) {
						$error = sprintf("has a card with an invalid context (%s)", $tuple);
						return false;
					}
					
					$context = $context_ext->id;
					
					$tuple = sprintf("%s:%d",
						$context,
						$id
					);
					
					$links[] = $tuple;
				}
				
				if(false == ($json = json_encode($value))) {
					$error = 'could not be JSON encoded.';
					return false;
				}
				
				$out_fields[DAO_ProjectBoardColumn::CARDS_JSON] = $json;
				$out_fields['_links'] = json_encode($links);
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
				
				$out_fields[DAO_ProjectBoardColumn::PARAMS_JSON] = $json;
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
		
		$context = Context_ProjectBoardColumn::ID;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true, true);
		}
		
		switch($token) {
			default:
				$defaults = $this->_lazyLoadDefaults($token, $context, $context_id);
				$values = array_merge($values, $defaults);
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
		$view->name = 'Project Board Column';
		/*
		$view->addParams(array(
			SearchFields_ProjectBoardColumn::UPDATED_AT => new DevblocksSearchCriteria(SearchFields_ProjectBoardColumn::UPDATED_AT,'=',0),
		), true);
		*/
		$view->renderSortBy = SearchFields_ProjectBoardColumn::UPDATED_AT;
		$view->renderSortAsc = false;
		$view->renderLimit = 10;
		$view->renderTemplate = 'contextlinks_chooser';
		
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Project Board Column';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_ProjectBoardColumn::VIRTUAL_CONTEXT_LINK,'in',array($context.':'.$context_id)),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='', $edit=false) {
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('view_id', $view_id);
		
		$context = Context_ProjectBoardColumn::ID;
		$model = null;
		
		if(!empty($context_id)) {
			$model = DAO_ProjectBoardColumn::get($context_id);
		}
		
		if(empty($context_id) || $edit) {
			if(!isset($model))
				$model = new Model_ProjectBoardColumn();
				
			if(!empty($edit)) {
				$tokens = explode(' ', trim($edit));
				
				foreach($tokens as $token) {
					@list($k,$v) = explode(':', $token);
					
					if(empty($k) || empty($v))
						continue;
					
					switch($k) {
						case 'board.id':
							$model->board_id = intval($v);
							break;
					}
				}
			}
			
			// Custom fields
			$custom_fields = DAO_CustomField::getByContext($context, false);
			$tpl->assign('custom_fields', $custom_fields);
	
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds($context, $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
			
			$types = Model_CustomField::getTypes();
			$tpl->assign('types', $types);
			
			$tpl->assign('model', $model);
			
			if(isset($model->params['behaviors'])) {
				$behaviors = DAO_TriggerEvent::getIds(array_keys($model->params['behaviors']));
				$tpl->assign('behaviors', $behaviors);
			}
			
			// View
			$tpl->assign('id', $context_id);
			$tpl->assign('view_id', $view_id);
			$tpl->display('devblocks:cerb.project_boards::project_board_column/peek_edit.tpl');
			
		} else {
			Page_Profiles::renderCard($context, $context_id, $model);
		}
	}
	
	/*
	function importGetKeys() {
		// [TODO] Translate
	
		$keys = array(
			'name' => array(
				'label' => 'Name',
				'type' => Model_CustomField::TYPE_SINGLE_LINE,
				'param' => SearchFields_ProjectBoardColumn::NAME,
				'required' => true,
			),
			'updated_at' => array(
				'label' => 'Updated Date',
				'type' => Model_CustomField::TYPE_DATE,
				'param' => SearchFields_ProjectBoardColumn::UPDATED_AT,
			),
		);
	
		$fields = SearchFields_ProjectBoardColumn::getFields();
		self::_getImportCustomFields($fields, $keys);
	
		DevblocksPlatform::sortObjects($keys, '[label]', true);
	
		return $keys;
	}
	
	function importKeyValue($key, $value) {
		switch($key) {
		}
	
		return $value;
	}
	
	function importSaveObject(array $fields, array $custom_fields, array $meta) {
		// If new...
		if(!isset($meta['object_id']) || empty($meta['object_id'])) {
			// Make sure we have a name
			if(!isset($fields[DAO_ProjectBoardColumn::NAME])) {
				$fields[DAO_ProjectBoardColumn::NAME] = 'New ' . $this->manifest->name;
			}
	
			// Create
			$meta['object_id'] = DAO_ProjectBoardColumn::create($fields);
	
		} else {
			// Update
			DAO_ProjectBoardColumn::update($meta['object_id'], $fields);
		}
	
		// Custom fields
		if(!empty($custom_fields) && !empty($meta['object_id'])) {
			DAO_CustomFieldValue::formatAndSetFieldValues($this->manifest->id, $meta['object_id'], $custom_fields, false, true, true); //$is_blank_unset (4th)
		}
	}
	*/
};
