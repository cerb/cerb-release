<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2014, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerb.ai/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://cerb.ai		http://webgroup.media
***********************************************************************/

class PageSection_ProfilesProfileWidget extends Extension_PageSection {
	function render() {
		$response = DevblocksPlatform::getHttpResponse();
		$stack = $response->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // profile_widget 
		@$context_id = intval(array_shift($stack)); // 123
		
		$context = CerberusContexts::CONTEXT_PROFILE_WIDGET;
		
		Page_Profiles::renderProfile($context, $context_id, $stack);
	}
	
	function savePeekJsonAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'], 'string', '');
		
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'integer', 0);
		@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'], 'string', '');
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		header('Content-Type: application/json; charset=utf-8');
		
		try {
			if(!empty($id) && !empty($do_delete)) { // Delete
				if(!$active_worker->hasPriv(sprintf("contexts.%s.delete", CerberusContexts::CONTEXT_PROFILE_WIDGET)))
					throw new Exception_DevblocksAjaxValidationError(DevblocksPlatform::translate('error.core.no_acl.delete'));
				
				DAO_ProfileWidget::delete($id);
				
				echo json_encode(array(
					'status' => true,
					'id' => $id,
					'view_id' => $view_id,
				));
				return;
				
			} else {
				@$import_json = DevblocksPlatform::importGPC($_REQUEST['import_json'], 'string', '');
				
				@$mode = (!$id && $import_json) ? 'import' : 'build';
				
				switch($mode) {
					case 'import':
						@$profile_tab_id = DevblocksPlatform::importGPC($_REQUEST['profile_tab_id'], 'integer', 0);
						@$import_json = DevblocksPlatform::importGPC($_REQUEST['import_json'], 'string', '');
						
						$error = null;
						
						if(
							empty($import_json)
							|| false == (@$widget_json = json_decode($import_json, true))
							)
							throw new Exception_DevblocksAjaxValidationError("Invalid JSON.");
						
						if(empty($profile_tab_id))
							throw new Exception_DevblocksAjaxValidationError("Invalid profile tab target");
						
						if(!isset($widget_json['widget']['extension_id']))
							throw new Exception_DevblocksAjaxValidationError("JSON doesn't contain widget extension info");
						
						if(!isset($widget_json['widget']['extension_params']))
							throw new Exception_DevblocksAjaxValidationError("JSON doesn't contain widget params");
						
						@$name = $widget_json['widget']['name'] ?: 'New widget';
						@$extension_id = $widget_json['widget']['extension_id'];
						
						if(empty($extension_id) || null == ($extension = Extension_ProfileWidget::get($extension_id)))
							throw new Exception_DevblocksAjaxValidationError("Invalid widget extension");
						
						$fields = [
							DAO_ProfileWidget::NAME => $name,
							DAO_ProfileWidget::EXTENSION_ID => $extension_id,
							DAO_ProfileWidget::EXTENSION_PARAMS_JSON => json_encode($widget_json['widget']['extension_params']),
							DAO_ProfileWidget::PROFILE_TAB_ID => $profile_tab_id,
							DAO_ProfileWidget::POS => @$widget_json['widget']['pos'] ?: 0,
							DAO_ProfileWidget::WIDTH_UNITS => @$widget_json['widget']['width_units'] ?: 4,
							DAO_ProfileWidget::ZONE => @$widget_json['widget']['zone'] ?: '',
						];
						
						if(!DAO_ProfileWidget::validate($fields, $error))
							throw new Exception_DevblocksAjaxValidationError($error);
						
						if(!DAO_ProfileWidget::onBeforeUpdateByActor($active_worker, $fields, null, $error))
							throw new Exception_DevblocksAjaxValidationError($error);
						
						$id = DAO_ProfileWidget::create($fields);
						DAO_ProfileWidget::onUpdateByActor($active_worker, $id, $fields);
						
						if(!empty($view_id) && !empty($id))
							C4_AbstractView::setMarqueeContextCreated($view_id, CerberusContexts::CONTEXT_PROFILE_WIDGET, $id);
						
						echo json_encode([
							'status' => true,
							'id' => $id,
							'label' => $name,
							'view_id' => $view_id,
						]);
						return;
						break;
					
					case 'build':
						@$name = DevblocksPlatform::importGPC($_REQUEST['name'], 'string', '');
						@$extension_id = DevblocksPlatform::importGPC($_REQUEST['extension_id'], 'string', '');
						@$profile_tab_id = DevblocksPlatform::importGPC($_REQUEST['profile_tab_id'], 'integer', 0);
						@$params = DevblocksPlatform::importGPC($_REQUEST['params'], 'array', []);
						@$width_units = DevblocksPlatform::importGPC($_REQUEST['width_units'], 'integer', 1);
						
						$error = null;
						
						// [TODO] format params
						
						if(empty($id)) { // New
							$fields = array(
								DAO_ProfileWidget::EXTENSION_ID => $extension_id,
								DAO_ProfileWidget::EXTENSION_PARAMS_JSON => json_encode($params),
								DAO_ProfileWidget::NAME => $name,
								DAO_ProfileWidget::PROFILE_TAB_ID => $profile_tab_id,
								DAO_ProfileWidget::UPDATED_AT => time(),
								DAO_ProfileWidget::WIDTH_UNITS => $width_units,
							);
							
							if(false == ($extension = Extension_ProfileWidget::get($extension_id)))
								throw new Exception_DevblocksAjaxValidationError("Invalid profile widget type.");
							
							if(!$extension->saveConfig($fields, null, $error))
								throw new Exception_DevblocksAjaxValidationError($error);
							
							if(!DAO_ProfileWidget::validate($fields, $error))
								throw new Exception_DevblocksAjaxValidationError($error);
							
							if(!DAO_ProfileWidget::onBeforeUpdateByActor($active_worker, $fields, null, $error))
								throw new Exception_DevblocksAjaxValidationError($error);
							
							$id = DAO_ProfileWidget::create($fields);
							DAO_ProfileWidget::onUpdateByActor($active_worker, $id, $fields);
							
							if(!empty($view_id) && !empty($id))
								C4_AbstractView::setMarqueeContextCreated($view_id, CerberusContexts::CONTEXT_PROFILE_WIDGET, $id);
							
						} else { // Edit
							$fields = array(
								DAO_ProfileWidget::EXTENSION_PARAMS_JSON => json_encode($params),
								DAO_ProfileWidget::NAME => $name,
								DAO_ProfileWidget::PROFILE_TAB_ID => $profile_tab_id,
								DAO_ProfileWidget::UPDATED_AT => time(),
								DAO_ProfileWidget::WIDTH_UNITS => $width_units,
							);
							
							if(false == ($widget = DAO_ProfileWidget::get($id)))
								throw new Exception_DevblocksAjaxValidationError("This profile widget no longer exists.");
							
							if(false == ($extension = $widget->getExtension()))
								throw new Exception_DevblocksAjaxValidationError("Invalid profile widget type.");
							
							if(!$extension->saveConfig($fields, $id, $error))
								throw new Exception_DevblocksAjaxValidationError($error);
							
							if(!DAO_ProfileWidget::validate($fields, $error, $id))
								throw new Exception_DevblocksAjaxValidationError($error);
								
							if(!DAO_ProfileWidget::onBeforeUpdateByActor($active_worker, $fields, $id, $error))
								throw new Exception_DevblocksAjaxValidationError($error);
							
							DAO_ProfileWidget::update($id, $fields);
							DAO_ProfileWidget::onUpdateByActor($active_worker, $id, $fields);
						}
						
						// Custom field saves
						@$field_ids = DevblocksPlatform::importGPC($_POST['field_ids'], 'array', []);
						if(!DAO_CustomFieldValue::handleFormPost(CerberusContexts::CONTEXT_PROFILE_WIDGET, $id, $field_ids, $error))
							throw new Exception_DevblocksAjaxValidationError($error);
						
						echo json_encode(array(
							'status' => true,
							'id' => $id,
							'label' => $name,
							'view_id' => $view_id,
						));
						return;
						break;
				}
			}
			
		} catch (Exception_DevblocksAjaxValidationError $e) {
			echo json_encode(array(
				'status' => false,
				'error' => $e->getMessage(),
				'field' => $e->getFieldName(),
			));
			return;
			
		} catch (Exception $e) {
			echo json_encode(array(
				'status' => false,
				'error' => 'An error occurred.',
			));
			return;
			
		}
	}
	
	function getFieldsTabsByContextAction() {
		@$context = DevblocksPlatform::importGPC($_REQUEST['context'],'string','');
		
		$tpl = DevblocksPlatform::services()->template();
		
		if(false == ($context_ext = Extension_DevblocksContext::get($context)))
			return;
		
		if(!($context_ext instanceof IDevblocksContextProfile))
			return;
		
		$tpl->assign('context_ext', $context_ext);
		
		// =================================================================
		// Properties
		
		$properties = $context_ext->profileGetFields();
		
		$tpl->assign('custom_field_values', []);
		
		$properties_cfields = Page_Profiles::getProfilePropertiesCustomFields($context, null);
		
		if(!empty($properties_cfields))
			$properties = array_merge($properties, $properties_cfields);
		
		$tpl->assign('properties', $properties);
		
		$properties_custom_fieldsets = Page_Profiles::getProfilePropertiesCustomFieldsets($context, null, [], true);
		$tpl->assign('properties_custom_fieldsets', $properties_custom_fieldsets);
		
		// =================================================================
		// Search buttons
		
		$search_contexts = Extension_DevblocksContext::getAll(false, ['search']);
		$tpl->assign('search_contexts', $search_contexts);
		
		// =================================================================
		// Template
		$tpl->display('devblocks:cerberusweb.core::internal/profiles/widgets/fields/fields_config_tabs.tpl');
	}
	
	function getExtensionsByTabContextJsonAction() {
		@$tab_id = DevblocksPlatform::importGPC($_REQUEST['tab_id'],'string','');
		
		header('Content-Type: application/json; charset=utf-8');
		
		if(!$tab_id || false == ($profile_tab = DAO_ProfileTab::get($tab_id))) {
			echo json_encode([]);
			return;
		}
		
		$widget_manifests = Extension_ProfileWidget::getByContext($profile_tab->context, false);
		
		echo json_encode(array_column(DevblocksPlatform::objectsToArrays($widget_manifests), 'name', 'id'));
	}
	
	function testWidgetTemplateAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'int', 0);
		@$profile_tab_id = DevblocksPlatform::importGPC($_REQUEST['profile_tab_id'], 'int', 0);
		@$params = DevblocksPlatform::importGPC($_REQUEST['params'], 'array', []);
		@$template_key = DevblocksPlatform::importGPC($_REQUEST['template_key'], 'string', '');
		@$index = DevblocksPlatform::importGPC($_REQUEST['index'], 'integer', 0);
		
		$tpl_builder = DevblocksPlatform::services()->templateBuilder();
		$tpl = DevblocksPlatform::services()->template();
		
		$template = null;

		if(DevblocksPlatform::strStartsWith($template_key, 'params[')) {
			$template_key = trim(substr($template_key, 6),'[]');
			$json_key = str_replace(['[',']'],['.',''],$template_key);
			$json_var = DevblocksPlatform::jsonGetPointerFromPath($params, $json_key);
			
			if(is_string($json_var)) {
				@$template = $json_var;
			} elseif (is_array($json_var)) {
				if(array_key_exists($index, $json_var)) {
					@$template = $json_var[$index];
				}
			}
		}
		
		if(false == $template)
			return;
		
		if(false == ($profile_tab = DAO_ProfileTab::get($profile_tab_id)))
			return;
		
		if(false == ($context_ext = $profile_tab->getContextExtension(true)))
			return;
		
		$dict = DevblocksDictionaryDelegate::instance([
			'record_id' => $context_ext->getRandom(),
			'record__context' => $context_ext->id,
			'widget_id' => $id,
			'widget__context' => CerberusContexts::CONTEXT_PROFILE_WIDGET,
		]);
		
		$success = false;
		$output = '';
		
		if(!is_string($template) || false === (@$out = $tpl_builder->build($template, $dict))) {
			// If we failed, show the compile errors
			$errors = $tpl_builder->getErrors();
			$success = false;
			$output = @array_shift($errors);
			
		} else {
			$success = true;
			$output = $out;
		}
		
		$tpl->assign('success', $success);
		$tpl->assign('output', $output);
		$tpl->display('devblocks:cerberusweb.core::internal/renderers/test_results.tpl');
	}
	
	function exportWidgetAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'int', 0);
		
		$tpl = DevblocksPlatform::services()->template();
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(!$active_worker->is_superuser)
			return;
		
		if(false == ($widget = DAO_ProfileWidget::get($id)))
			return;
		
		if(false == ($extension = $widget->getExtension()))
			return;
		
		$json = $extension->export($widget);
		
		$tpl->assign('widget', $widget);
		$tpl->assign('json', DevblocksPlatform::strFormatJson($json));
		
		$tpl->display('devblocks:cerberusweb.core::internal/profiles/widgets/export_widget.tpl');
	}
	
	function viewExploreAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$url_writer = DevblocksPlatform::services()->url();
		
		// Generate hash
		$hash = md5($view_id.$active_worker->id.time());
		
		// Loop through view and get IDs
		$view = C4_AbstractViewLoader::getView($view_id);
		$view->setAutoPersist(false);

		// Page start
		@$explore_from = DevblocksPlatform::importGPC($_REQUEST['explore_from'],'integer',0);
		if(empty($explore_from)) {
			$orig_pos = 1+($view->renderPage * $view->renderLimit);
		} else {
			$orig_pos = 1;
		}

		$view->renderPage = 0;
		$view->renderLimit = 250;
		$pos = 0;
		
		do {
			$models = [];
			list($results, $total) = $view->getData();

			// Summary row
			if(0==$view->renderPage) {
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'title' => $view->name,
					'created' => time(),
//					'worker_id' => $active_worker->id,
					'total' => $total,
					'return_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $url_writer->writeNoProxy('c=search&type=profile_widget', true),
				);
				$models[] = $model;
				
				$view->renderTotal = false; // speed up subsequent pages
			}
			
			if(is_array($results))
			foreach($results as $opp_id => $row) {
				if($opp_id==$explore_from)
					$orig_pos = $pos;
				
				$url = $url_writer->writeNoProxy(sprintf("c=profiles&type=profile_widget&id=%d-%s", $row[SearchFields_ProfileWidget::ID], DevblocksPlatform::strToPermalink($row[SearchFields_ProfileWidget::NAME])), true);
				
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'id' => $row[SearchFields_ProfileWidget::ID],
					'url' => $url,
				);
				$models[] = $model;
			}
			
			DAO_ExplorerSet::createFromModels($models);
			
			$view->renderPage++;
			
		} while(!empty($results));
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('explore',$hash,$orig_pos)));
	}
};
