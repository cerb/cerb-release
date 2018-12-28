<?php
class _DevblocksTwigSecurityPolicy extends Twig_Sandbox_SecurityPolicy {
	function checkMethodAllowed($obj, $method) {
		if ($obj instanceof Twig_TemplateInterface || $obj instanceof Twig_Markup) {
			return true;
		}
		
		// Allow
		if($method == '__toString')
			return true;
		
		throw new Twig_Sandbox_SecurityError(sprintf('Calling "%s" method on a "%s" object is not allowed.', $method, get_class($obj)));
	}
	
	function checkPropertyAllowed($obj, $property) {
		// Everything in our dictionary is okay
		if($obj instanceof DevblocksDictionaryDelegate)
			return;
		
		// Allow SimpleXMLElement objects
		if($obj instanceof SimpleXMLElement)
			return;
		
		// Deny everything else
		throw new Twig_Sandbox_SecurityError(sprintf('Calling "%s" property on a "%s" object is not allowed.', $property, get_class($obj)));
	}
}

class _DevblocksTemplateBuilder {
	private $_twig = null;
	private $_errors = [];
	
	private function __construct() {
		$this->_twig = new Twig_Environment(new Twig_Loader_String(), array(
			'cache' => false,
			'debug' => false,
			'strict_variables' => false,
			'auto_reload' => true,
			'trim_blocks' => true,
			'autoescape' => false,
		));
		
		if(class_exists('_DevblocksTwigExtensions', true)) {
			$this->_twig->addExtension(new _DevblocksTwigExtensions());
			
			// Sandbox Twig
			
			$tags = [
				//'autoescape',
				//'block',
				'do',
				//'embed',
				//'extends',
				'filter',
				//'flush',
				'for',
				//'from',
				'if',
				//'import',
				//'include',
				//'macro',
				'sandbox',
				'set',
				'spaceless',
				//'use',
				'verbatim',
				'with',
			];
			
			$filters = [
				'alphanum',
				'base_convert',
				'base64_encode',
				'base64_decode',
				'bytes_pretty',
				'cerb_translate',
				'context_name',
				'date_pretty',
				'hash_hmac',
				'json_pretty',
				'md5',
				'parse_emails',
				'quote',
				'regexp',
				'secs_pretty',
				'sha1',
				'split_crlf',
				'split_csv',
				'truncate',
				'unescape',
				'url_decode',
				
				'abs',
				'batch',
				'capitalize',
				'convert_encoding',
				'date',
				'date_modify',
				'default',
				'escape',
				'first',
				'format',
				'join',
				'json_encode',
				'keys',
				'last',
				'length',
				'lower',
				'merge',
				'nl2br',
				'number_format',
				'raw',
				'replace',
				'reverse',
				'round',
				'slice',
				'sort',
				'split',
				'striptags',
				'title',
				'trim',
				'upper',
				'url_encode',
			];
			
			$functions = [
				'array_combine',
				'array_diff',
				'array_intersect',
				'array_sort_keys',
				'array_unique',
				'array_values',
				'cerb_avatar_image',
				'cerb_avatar_url',
				'cerb_file_url',
				'cerb_has_priv',
				'cerb_placeholders_list',
				'cerb_record_readable',
				'cerb_record_writeable',
				'cerb_url',
				'dict_set',
				'json_decode',
				'jsonpath_set',
				'placeholders_list',
				'random_string',
				'regexp_match_all',
				'shuffle',
				'validate_email',
				'validate_number',
				'xml_decode',
				'xml_encode',
				'xml_xpath',
				'xml_xpath_ns',
				
				'attribute',
				//'block',
				//'constant',
				'cycle',
				'date',
				//'dump',
				//'include',
				'max',
				'min',
				//'parent',
				'random',
				'range',
				//'source',
				//'template_from_string',
			];
			
			$methods = [];
			$properties = [];
			
			$policy = new _DevblocksTwigSecurityPolicy($tags, $filters, $methods, $properties, $functions);
			$sandbox = new Twig_Extension_Sandbox($policy, true);
			$this->_twig->addExtension($sandbox);
		}
	}
	
	/**
	 *
	 * @return _DevblocksTemplateBuilder
	 */
	static function getInstance() {
		static $instance = null;
		if(null == $instance) {
			$instance = new _DevblocksTemplateBuilder();
		}
		return $instance;
	}

	/**
	 * @return Twig_Environment
	 */
	public function getEngine() {
		return $this->_twig;
	}
	
	/**
	 * @return array
	 */
	public function getErrors() {
		return $this->_errors;
	}
	
	private function _setUp() {
		$this->_errors = [];
	}
	
	private function _tearDown() {
	}
	
	function getLexer() {
		return $this->_twig->getLexer();
	}
	
	function setLexer(Twig_Lexer $lexer) {
		$this->_twig->setLexer($lexer);
	}
	
	function tokenize($templates) {
		$tokens = [];
		
		if(!is_array($templates))
			$templates = array($templates);

		foreach($templates as $template) {
			try {
				$token_stream = $this->_twig->tokenize($template); /* @var $token_stream Twig_TokenStream */
				$node_stream = $this->_twig->parse($token_stream); /* @var $node_stream Twig_Node_Module */
	
				$visitor = new _DevblocksTwigExpressionVisitor();
				$traverser = new Twig_NodeTraverser($this->_twig);
				$traverser->addVisitor($visitor);
				$traverser->traverse($node_stream);
				
				//var_dump($visitor->getFoundTokens());
				$tokens = array_merge($tokens, $visitor->getFoundTokens());
				
			} catch(Exception $e) {
				//var_dump($e->getMessage());
			}
		}
		
		$tokens = array_unique($tokens);
		
		return $tokens;
	}
	
	function stripModifiers($array) {
		array_walk($array, array($this,'_stripModifiers'));
		return $array;
	}
	
	function _stripModifiers(&$item, $key) {
		if(false != ($pos = strpos($item, '|'))) {
			$item = substr($item, 0, $pos);
		}
	}
	
	function addFilter($name, $filter) {
		return $this->_twig->addFilter($name, $filter);
	}
	
	function addFunction($name, $function) {
		return $this->_twig->addFunction($name, $function);
	}
	
	/**
	 *
	 * @param string $template
	 * @param array $vars
	 * @return string
	 */
	function build($template, $dict, $lexer = null) {
		if($lexer && is_array($lexer)) {
			$this->setLexer(new Twig_Lexer($this->_twig, $lexer));
		}
		
		$this->_setUp();
		
		if(is_array($dict))
			$dict = new DevblocksDictionaryDelegate($dict);
		
		try {
			$template = $this->_twig->loadTemplate($template); /* @var $template Twig_Template */
			$this->_twig->registerUndefinedVariableCallback(array($dict, 'delegateUndefinedVariable'), true);
			$out = $template->render([]);
			
		} catch(Exception $e) {
			$this->_errors[] = $e->getMessage();
		}
		$this->_tearDown();
		
		if($lexer) {
			$this->setLexer(new Twig_Lexer($this->_twig));
		}

		if(!empty($this->_errors))
			return false;
		
		return $out;
	}
};

class DevblocksDictionaryDelegate implements JsonSerializable {
	private $_dictionary = null;
	private $_cached_contexts = null;
	private $_null = null;
	
	function __construct($dictionary) {
		if(is_array($dictionary))
		foreach($dictionary as $k => $v) {
			if(DevblocksPlatform::strStartsWith($k, 'var_') && is_array($v)) {
				foreach($v as $id => $values) {
					if(is_array($values) && isset($values['_context'])) {
						$dictionary[$k][$id] = new DevblocksDictionaryDelegate($values);
					}
				}
			}
		}
		
		$this->_dictionary = $dictionary;
	}
	
	public static function instance($values) {
		return new DevblocksDictionaryDelegate($values);
	}
	
	function __toString() {
		$dictionary = $this->getDictionary(null, false);
		return DevblocksPlatform::strFormatJson(json_encode($dictionary));
	}
	
	function jsonSerialize() {
		return $this->_dictionary;
	}

	public function __set($name, $value) {
		// Clear the context cache if we're dynamically adding new contexts
		if(DevblocksPlatform::strEndsWith($name, '__context'))
			$this->clearCaches();
		
		$this->_dictionary[$name] = $value;
	}
	
	public function __unset($name) {
		unset($this->_dictionary[$name]);
	}
	
	public function clearCaches() {
		$this->_cached_contexts = null;
	}
	
	private function _cacheContexts() {
		$contexts = [];
		
		// Match our root context
		if(isset($this->_dictionary['_context'])) {
			$contexts[''] = array(
				'key' => '_context',
				'prefix' => '',
				'context' => $this->_dictionary['_context'],
				'len' => 0,
			);
		}
		
		// Find the embedded contexts for each token
		foreach(array_keys($this->_dictionary) as $key) {
			$matches = [];
			
			if(preg_match('#(.*)__context#', $key, $matches)) {
				$contexts[$matches[1]] = array(
					'key' => $key,
					'prefix' => $matches[1] . '_',
					'context' => $this->_dictionary[$key],
					'len' => strlen($matches[1]),
				);
			}
		}
		
		DevblocksPlatform::sortObjects($contexts, '[len]', true);
		
		$this->_cached_contexts = $contexts;
	}
	
	public function getContextsForName($name) {
		if(is_null($this->_cached_contexts))
			$this->_cacheContexts();
		
		return array_filter($this->_cached_contexts, function($context) use ($name) {
			return substr($name, 0, strlen($context['prefix'])) == $context['prefix'];
		});
	}
	
	public function get($name, $default=null) {
		$value = $this->$name;
		
		if(!is_null($value))
			return $value;
		
		if($default)
			return $default;
		
		return null;
	}
	
	public function set($name, $value) {
		return $this->$name = $value;
	}
	
	public function &__get($name) {
		if($this->exists($name))
			return $this->_dictionary[$name];
		
		// Lazy load
		
		$contexts = $this->getContextsForName($name);
		
		$is_cache_invalid = false;
		
		if(is_array($contexts))
		foreach($contexts as $context_data) {
			$context_ext = $this->_dictionary[$context_data['key']];
			
			$token = substr($name, strlen($context_data['prefix']));
			
			if(null == ($context = Extension_DevblocksContext::get($context_ext)))
				continue;
			
			if(!method_exists($context, 'lazyLoadContextValues'))
				continue;
	
			$local = $this->getDictionary($context_data['prefix'], false);
			
			$loaded_values = $context->lazyLoadContextValues($token, $local);
			
			// Push the context into the stack so we can track ancestry
			CerberusContexts::pushStack($context_data['context']);
			
			if(empty($loaded_values))
				continue;
			
			if(is_array($loaded_values))
			foreach($loaded_values as $k => $v) {
				$new_key = $context_data['prefix'] . $k;
				
				// Only invalidate the cache if we loaded new contexts the first time
				if(DevblocksPlatform::strEndsWith($new_key, '__context')
					&& !array_key_exists($new_key, $this->_dictionary)) {
					$is_cache_invalid = true;
				}
				
				if($k == '_types') {
					// If the parent has a `_types` key, append these values to it
					if(array_key_exists('_types', $this->_dictionary)) {
						foreach($v as $type_k => $type_v) {
							$this->_dictionary['_types'][$context_data['prefix'] . $type_k] = $type_v;
						}
					}
					continue;
				}
				
				// The getDictionary() call above already filters out _labels and _types
				$this->_dictionary[$new_key] = $v;
			}
		}
		
		if($is_cache_invalid)
			$this->clearCaches();
		
		if(is_array($contexts))
		for($n=0; $n < count($contexts); $n++)
			CerberusContexts::popStack();
		
		if(!$this->exists($name)) {
			// If the key isn't found and we invalidated the cache, recurse
			if($is_cache_invalid) {
				return $this->__get($name);
			} else {
				return $this->_null;
			}
		}
		
		return $this->_dictionary[$name];
	}
	
	// This lazy loads, and 'exists' doesn't.
	public function __isset($name) {
		if(null !== (@$this->__get($name)))
			return true;
		
		return false;
	}
	
	public function exists($name) {
		return isset($this->_dictionary[$name]);
	}
	
	public function delegateUndefinedVariable($name, &$context) {
		$this->$name;
		
		$context = array_merge($context, $this->_dictionary);
		
		return $this->get($name);
	}
	
	public function getDictionary($with_prefix=null, $with_meta=true) {
		$dict = $this->_dictionary;
		
		if(!$with_meta) {
			unset($dict['_labels']);
			unset($dict['_types']);
			unset($dict['__simulator_output']);
			unset($dict['__trigger']);
			unset($dict['__exit']);
		}
		
		// Convert any nested dictionaries to arrays
		array_walk_recursive($dict, function(&$v) use ($with_meta) {
			if($v instanceof DevblocksDictionaryDelegate)
				$v = $v->getDictionary(null, $with_meta);
		});
		
		if(empty($with_prefix))
			return $dict;

		$new_dict = [];
		
		foreach($dict as $k => $v) {
			$len = strlen($with_prefix);
			if(0 == strcasecmp($with_prefix, substr($k,0,$len))) {
				$new_dict[substr($k,$len)] = $v;
			}
		}
		
		return $new_dict;
	}
	
	public function scrubKeys($prefix) {
		if(is_array($this->_dictionary))
		foreach(array_keys($this->_dictionary) as $key) {
			if(DevblocksPlatform::strStartsWith($key, $prefix))
				unset($this->_dictionary[$key]);
		}
	}
	
	public function scrubKeySuffix($suffix) {
		if(is_array($this->_dictionary))
		foreach(array_keys($this->_dictionary) as $key) {
			if(DevblocksPlatform::strEndsWith($key, $suffix))
				unset($this->_dictionary[$key]);
		}
	}
	
	public function extract($prefix) {
		$values = [];
		
		if(is_array($this->_dictionary))
		foreach(array_keys($this->_dictionary) as $key) {
			if(DevblocksPlatform::strStartsWith($key, $prefix)) {
				$new_key = substr($key, strlen($prefix));
				$values[$new_key] = $this->_dictionary[$key];
			}
		}
		
		return DevblocksDictionaryDelegate::instance($values);
	}
	
	public function merge($token_prefix, $label_prefix, $src_labels, $src_values) {
		$dst_labels =& $this->_dictionary['_labels'];
		assert(is_array($dst_labels));
		$dst_values =& $this->_dictionary;
		
		if(is_array($src_labels))
		foreach($src_labels as $token => $label) {
			$dst_labels[$token_prefix.$token] = $label_prefix.$label;
		}

		if(is_array($src_values))
		foreach($src_values as $token => $value) {
			if(in_array($token, array('_labels', '_types'))) {

				switch($token) {
					case '_labels':
						if(!isset($dst_values['_labels']))
							$dst_values['_labels'] = [];

						foreach($value as $key => $label) {
							$dst_values['_labels'][$token_prefix.$key] = $label_prefix.$label;
						}
						break;

					case '_types':
						if(!isset($dst_values['_types']))
							$dst_values['_types'] = [];

						foreach($value as $key => $type) {
							$dst_values['_types'][$token_prefix.$key] = $type;
						}
						break;
				}

			} else {
				$dst_values[$token_prefix.$token] = $value;
			}
		}
		
		return true;
	}
	
	public static function getDictionariesFromModels(array $models, $context, array $keys=[]) {
		$dicts = [];
		
		if(empty($models)) {
			return [];
		}
		
		foreach($models as $model_id => $model) {
			$labels = $values = [];
			
			if($context == CerberusContexts::CONTEXT_APPLICATION) {
				$values = ['_context' => $context, 'id' => 0, '_label' => 'Cerb'];
			} else {
				CerberusContexts::getContext($context, $model, $labels, $values, null, true, true);
			}
			
			if(isset($values['id']))
				$dicts[$model_id] = DevblocksDictionaryDelegate::instance($values);
		}
		
		// Batch load extra keys
		if(is_array($keys) && !empty($keys))
		foreach($keys as $key) {
			DevblocksDictionaryDelegate::bulkLazyLoad($dicts, $key);
		}
		
		return $dicts;
	}
	
	public static function bulkLazyLoad(array $dicts, $token, $skip_meta=false) {
		if(empty($dicts))
			return;
		
		// [TODO] Don't run (n) queries to lazy load custom fields
		
		// Examine contexts on the first dictionary
		$first_dict = reset($dicts);

		// Get the list of embedded contexts
		$contexts = $first_dict->getContextsForName($token);

		foreach($contexts as $context_prefix => $context_data) {
			// The top-level context is always loaded
			if(empty($context_prefix))
				continue;
			
			// If the context is already loaded, skip it
			$loaded_key = $context_prefix . '__loaded';
			if($first_dict->exists($loaded_key))
				continue;
			
			$id_counts = [];
			
			foreach($dicts as $dict) {
				$id_key = $context_prefix . '_id';
				$id = $dict->$id_key;
				
				if(!isset($id_counts[$id])) {
					$id_counts[$id] = 1;
					
				} else {
					$id_counts[$id]++;
				}
			}
			
			// Preload the contexts before lazy loading
			if(false != ($context_ext = Extension_DevblocksContext::get($context_data['context']))) {
				
				// Load model objects from the context
				$models = $context_ext->getModelObjects(array_keys($id_counts));
				
				$was_caching_loads = CerberusContexts::setCacheLoads(true);
				
				// These context loads will be cached
				if(is_array($models))
				foreach($models as $model) {
					$labels = $values = []; 
					CerberusContexts::getContext($context_data['context'], $model, $labels, $values, null, true, $skip_meta);
				}
				
				$prefix_key = $context_prefix . '_';
				
				// Load the contexts from the cache
				foreach($dicts as $dict) {
					$dict->$prefix_key;
				}
				
				// Flush the temporary cache
				CerberusContexts::setCacheLoads($was_caching_loads);
			}
		}
		
		// Now load the tokens, since we probably already lazy loaded the contexts
		foreach($dicts as $dict) { /* @var $dict DevblocksDictionaryDelegate */
			$dict->$token;
		}
	}
};

class _DevblocksTwigExpressionVisitor implements Twig_NodeVisitorInterface {
	protected $_tokens = [];
	
	public function enterNode(Twig_NodeInterface $node, Twig_Environment $env) {
		if($node instanceof Twig_Node_Expression_Name) {
			$this->_tokens[$node->getAttribute('name')] = true;
			
		} elseif($node instanceof Twig_Node_SetTemp) {
			$this->_tokens[$node->getAttribute('name')] = true;
			
		}
		return $node;
	}
	
	public function leaveNode(Twig_NodeInterface $node, Twig_Environment $env) {
		return $node;
	}
	
	function getPriority() {
		return 0;
	}
	
	function getFoundTokens() {
		return array_keys($this->_tokens);
	}
};

if(class_exists('Twig_Extension', true)):
class _DevblocksTwigExtensions extends Twig_Extension {
	public function getName() {
		return 'devblocks_twig';
	}
	
	public function getFunctions() {
		return array(
			new Twig_SimpleFunction('array_combine', [$this, 'function_array_combine']),
			new Twig_SimpleFunction('array_diff', [$this, 'function_array_diff']),
			new Twig_SimpleFunction('array_intersect', [$this, 'function_array_intersect']),
			new Twig_SimpleFunction('array_sort_keys', [$this, 'function_array_sort_keys']),
			new Twig_SimpleFunction('array_unique', [$this, 'function_array_unique']),
			new Twig_SimpleFunction('array_values', [$this, 'function_array_values']),
			new Twig_SimpleFunction('cerb_avatar_image', [$this, 'function_cerb_avatar_image']),
			new Twig_SimpleFunction('cerb_avatar_url', [$this, 'function_cerb_avatar_url']),
			new Twig_SimpleFunction('cerb_file_url', [$this, 'function_cerb_file_url']),
			new Twig_SimpleFunction('cerb_has_priv', [$this, 'function_cerb_has_priv']),
			new Twig_SimpleFunction('cerb_placeholders_list', [$this, 'function_placeholders_list'], ['needs_environment' => true]),
			new Twig_SimpleFunction('cerb_record_readable', [$this, 'function_cerb_record_readable']),
			new Twig_SimpleFunction('cerb_record_writeable', [$this, 'function_cerb_record_writeable']),
			new Twig_SimpleFunction('cerb_url', [$this, 'function_cerb_url']),
			new Twig_SimpleFunction('dict_set', [$this, 'function_dict_set']),
			new Twig_SimpleFunction('json_decode', [$this, 'function_json_decode']),
			new Twig_SimpleFunction('jsonpath_set', [$this, 'function_jsonpath_set']),
			new Twig_SimpleFunction('placeholders_list', [$this, 'function_placeholders_list'], ['needs_environment' => true]),
			new Twig_SimpleFunction('random_string', [$this, 'function_random_string']),
			new Twig_SimpleFunction('regexp_match_all', [$this, 'function_regexp_match_all']),
			new Twig_SimpleFunction('shuffle', [$this, 'function_shuffle']),
			new Twig_SimpleFunction('validate_email', [$this, 'function_validate_email']),
			new Twig_SimpleFunction('validate_number', [$this, 'function_validate_number']),
			new Twig_SimpleFunction('xml_decode', [$this, 'function_xml_decode']),
			new Twig_SimpleFunction('xml_encode', [$this, 'function_xml_encode']),
			new Twig_SimpleFunction('xml_xpath_ns', [$this, 'function_xml_xpath_ns']),
			new Twig_SimpleFunction('xml_xpath', [$this, 'function_xml_xpath']),
		);
	}
	
	function function_array_combine($keys, $values) {
		if(!is_array($keys) || !is_array($values))
			return;
		
		return array_combine($keys, $values);
	}
	
	function function_array_diff($arr1, $arr2) {
		if(!is_array($arr1) || !is_array($arr2))
			return;
		
		return array_diff($arr1, $arr2);
	}
	
	function function_array_intersect($arr1, $arr2) {
		if(!is_array($arr1) || !is_array($arr2))
			return;
		
		return array_intersect($arr1, $arr2);
	}
	
	function function_array_sort_keys($arr) {
		if(!is_array($arr))
			return;
		
		ksort($arr);
		
		return $arr;
	}
	
	function function_array_unique($arr) {
		if(!is_array($arr))
			return;
		
		return array_unique($arr);
	}
	
	function function_array_values($arr) {
		if(!is_array($arr))
			return;
		
		return array_values($arr);
	}
	
	function function_cerb_has_priv($priv, $actor_context=null, $actor_id=null) {
		if(is_null($actor_context) && is_null($actor_context)) {
			$active_worker = CerberusApplication::getActiveWorker();
			return $active_worker->hasPriv($priv);
		}
		
		if(false == ($context_ext = Extension_DevblocksContext::getByAlias($actor_context, true)))
		if(false == ($context_ext = Extension_DevblocksContext::get($actor_context)))
			return false;
		
		if(!($context_ext instanceof Context_Worker))
			return false;
		
		if(false == ($worker = DAO_Worker::get($actor_id)))
			return false;
		
		return $worker->hasPriv($priv);
	}
	
	function function_cerb_record_readable($record_context, $record_id, $actor_context=null, $actor_context_id=null) {
		if(is_null($actor_context) && is_null($actor_context)) {
			$actor = CerberusApplication::getActiveWorker();
		} else {
			$actor = [$actor_context, $actor_context_id];
		}
		
		return CerberusContexts::isReadableByActor($record_context, $record_id, $actor);
	}
	
	function function_cerb_record_writeable($record_context, $record_id, $actor_context=null, $actor_context_id=null) {
		if(is_null($actor_context) && is_null($actor_context)) {
			$actor = CerberusApplication::getActiveWorker();
		} else {
			$actor = [$actor_context, $actor_context_id];
		}
		
		return CerberusContexts::isWriteableByActor($record_context, $record_id, $actor);
	}
	
	function function_cerb_avatar_image($context, $id, $updated=0) {
		$url = $this->function_cerb_avatar_url($context, $id, $updated);
		
		return sprintf('<img src="%s" style="height:16px;width:16px;border-radius:16px;vertical-align:middle;">',
			$url
		);
	}
	
	function function_cerb_avatar_url($context, $id, $updated=0) {
		$url_writer = DevblocksPlatform::services()->url();
		
		if(false == ($context_ext = Extension_DevblocksContext::getByAlias($context, true)))
		if(false == ($context_ext = Extension_DevblocksContext::get($context)))
			return null;
		
		if(false == ($aliases = Extension_DevblocksContext::getAliasesForContext($context_ext->manifest)))
			return null;
		
		$type = @$aliases['uri'] ?: $context_ext->manifest->id;
		
		$url = $url_writer->write(sprintf('c=avatars&type=%s&id=%d', rawurlencode($type), $id), true, true);
		
		if($updated)
			$url .= '?v=' . intval($updated);
		
		return $url;
	}
	
	function function_cerb_file_url($id) {
		$url_writer = DevblocksPlatform::services()->url();
		
		if(false == ($file = DAO_Attachment::get($id)))
			return null;
		
		return $url_writer->write(sprintf('c=files&id=%d&name=%s', $id, rawurlencode($file->name)), true, true);
	}
	
	function function_cerb_url($url, $full=true, $proxy=true) {
		$url_writer = DevblocksPlatform::services()->url();
		return $url_writer->write($url, $full, $proxy);
	}
	
	function function_json_decode($str) {
		return json_decode($str, true);
	}
	
	function function_jsonpath_set($var, $path, $val) {
		if(empty($var))
			$var = [];
		
		$parts = explode('.', $path);
		$ptr =& $var;
		
		if(is_array($parts))
		foreach($parts as $part) {
			$is_array_set = false;
		
			if(substr($part,-2) == '[]') {
				$part = rtrim($part, '[]');
				$is_array_set = true;
			}
		
			if(!isset($ptr[$part]))
				$ptr[$part] = [];
			
			if($is_array_set) {
				$ptr =& $ptr[$part][];
				
			} else {
				$ptr =& $ptr[$part];
			}
		}
		
		$ptr = $val;
		
		return $var;
	}
	
	function function_placeholders_list(Twig_Environment $env) {
		if(false == (@$callback = $env->getUndefinedVariableCallbacks()[0]) || !is_array($callback))
			return [];
		
		if(false == (@$dict = $callback[0]))
			return [];
		
		return $dict->getDictionary('', false);
	}
	
	function function_random_string($length=8) {
		$length = DevblocksPlatform::intClamp($length, 1, 255);
		return CerberusApplication::generatePassword($length);
	}
	
	function function_dict_set($var, $path, $val) {
		return DevblocksPlatform::arrayDictSet($var, $path, $val);
	}
	
	function function_regexp_match_all($pattern, $text, $group = 0) {
		$group = intval($group);
		$matches = [];
		
		@preg_match_all($pattern, $text, $matches, PREG_PATTERN_ORDER);
		
		if(!empty($matches)) {
			
			if(empty($group))
				return $matches;
			
			if(is_array($matches) && isset($matches[$group])) {
				return $matches[$group];
			}
		}
		
		return [];
	}
	
	function function_xml_encode($xml) {
		if(!($xml instanceof SimpleXMLElement))
			return false;
		
		return $xml->asXML();
	}
	
	function function_xml_decode($str, $namespaces=[], $mode=null) {
		switch(DevblocksPlatform::strLower($mode)) {
			case 'html':
				$doc = new DOMDocument();
				$doc->loadHTML($str);
				$xml = simplexml_import_dom($doc);
				break;
				
			default:
				$xml = simplexml_load_string($str);
				break;
		}
		
		if(!($xml instanceof SimpleXMLElement))
			return false;
		
		if(is_array($namespaces))
		foreach($namespaces as $prefix => $ns)
			$xml->registerXPathNamespace($prefix, $ns);
		
		return $xml;
	}
	
	function function_xml_xpath_ns($xml, $prefix, $ns) {
		if(!($xml instanceof SimpleXMLElement))
			return false;
		
		$xml->registerXPathNamespace($prefix, $ns);
		
		return $xml;
	}
	
	function function_xml_xpath($xml, $path, $element=null) {
		if(!($xml instanceof SimpleXMLElement))
			return false;
		
		$result = $xml->xpath($path);
		
		if(!is_null($element) && isset($result[$element]))
			return $result[$element];
		
		return $result;
	}
	
	function function_shuffle($array) {
		if(!is_array($array))
			return false;
		
		shuffle($array);
		
		return $array;
	}
	
	function function_validate_email($string) {
		if(!is_string($string))
			return false;
		
		if(!stripos($string, '@'))
			return false;
		
		if(false == ($addresses = CerberusMail::parseRfcAddresses($string)))
			return false;
		
		if(!is_array($addresses) || 1 != count($addresses))
			return false;
		
		return true;
	}
	
	function function_validate_number($number) {
		if(!is_numeric($number))
			return false;
		
		return true;
	}
	
	public function getFilters() {
		return array(
			new Twig_SimpleFilter('alphanum', [$this, 'filter_alphanum']),
			new Twig_SimpleFilter('base_convert', [$this, 'filter_base_convert']),
			new Twig_SimpleFilter('base64_encode', [$this, 'filter_base64_encode']),
			new Twig_SimpleFilter('base64_decode', [$this, 'filter_base64_decode']),
			new Twig_SimpleFilter('bytes_pretty', [$this, 'filter_bytes_pretty']),
			new Twig_SimpleFilter('cerb_translate', [$this, 'filter_cerb_translate']),
			new Twig_SimpleFilter('context_name', [$this, 'filter_context_name']),
			new Twig_SimpleFilter('date_pretty', [$this, 'filter_date_pretty']),
			new Twig_SimpleFilter('hash_hmac', [$this, 'filter_hash_hmac']),
			new Twig_SimpleFilter('json_pretty', [$this, 'filter_json_pretty']),
			new Twig_SimpleFilter('md5', [$this, 'filter_md5']),
			new Twig_SimpleFilter('parse_emails', [$this, 'filter_parse_emails']),
			new Twig_SimpleFilter('quote', [$this, 'filter_quote']),
			new Twig_SimpleFilter('regexp', [$this, 'filter_regexp']),
			new Twig_SimpleFilter('secs_pretty', [$this, 'filter_secs_pretty']),
			new Twig_SimpleFilter('sha1', [$this, 'filter_sha1']),
			new Twig_SimpleFilter('split_crlf', [$this, 'filter_split_crlf']),
			new Twig_SimpleFilter('split_csv', [$this, 'filter_split_csv']),
			new Twig_SimpleFilter('truncate', [$this, 'filter_truncate']),
			new Twig_SimpleFilter('unescape', [$this, 'filter_unescape']),
			new Twig_SimpleFilter('url_decode', [$this, 'filter_url_decode']),
		);
	}
	
	function filter_alphanum($string, $also=null, $replace='') {
		if(!is_string($string))
			return '';
		
		return DevblocksPlatform::strAlphaNum($string, $also, $replace);
	}
	
	function filter_base_convert($string, $base_from, $base_to) {
		if(!is_string($string) && !is_numeric($string))
			return '';
		
		if(!is_numeric($base_from) || !is_numeric($base_to))
			return '';
		
		return base_convert($string, $base_from, $base_to);
	}
	
	function filter_base64_encode($string) {
		if(!is_string($string))
			return '';
		
		return base64_encode($string);
	}
	
	function filter_base64_decode($string) {
		if(!is_string($string))
			return '';
		
		return base64_decode($string);
	}
	
	function filter_bytes_pretty($string, $precision='0') {
		if(!is_string($string) && !is_numeric($string))
			return '';
		
		return DevblocksPlatform::strPrettyBytes($string, $precision);
	}
	
	function filter_cerb_translate($string) {
		return DevblocksPlatform::translate($string);
	}
	
	function filter_context_name($string, $type='plural') {
		if(!is_string($string))
			return '';
		
		if(false == ($ctx_manifest = Extension_DevblocksContext::get($string, false)))
			return '';
		
		if(false == ($aliases = Extension_DevblocksContext::getAliasesForContext($ctx_manifest)))
			return '';
		
		if(isset($aliases[$type]))
			return $aliases[$type];
		
		return '';
	}
	
	function filter_date_pretty($string, $is_delta=false) {
		if(!is_string($string) && !is_numeric($string))
			return '';
		
		return DevblocksPlatform::strPrettyTime($string, $is_delta);
	}
	
	function filter_hash_hmac($string, $key='', $algo='sha256') {
		if(!is_string($string) 
			|| !is_string($key) 
			|| !is_string($algo) 
			|| empty($string)
			)
			return '';
		
		if(false == ($hash = hash_hmac($algo, $string, $key)))
			return '';
		
		return $hash;
	}
	
	function filter_json_pretty($string) {
		if(!is_string($string))
			return '';
		
		return DevblocksPlatform::strFormatJson($string);
	}
	
	function filter_md5($string) {
		if(!is_string($string))
			return '';
		
		return md5($string);
	}
	
	function filter_parse_emails($string) {
		if(!is_string($string))
			return '';
		
		$results = CerberusMail::parseRfcAddresses($string);
		return $results;
	}
	
	function filter_quote($string, $wrap_to=76) {
		if(!is_string($string))
			return '';
		
		$lines = DevblocksPlatform::parseCrlfString(trim($string), true, false);
		
		array_walk($lines, function(&$line) {
			$line = '> ' . $line;
		});
		
		return _DevblocksTemplateManager::modifier_devblocks_email_quote(implode(PHP_EOL, $lines), $wrap_to);
	}

	function filter_regexp($string, $pattern, $group = 0) {
		if(!is_string($string))
			return '';
		
		$matches = [];
		@preg_match($pattern, $string, $matches);
		
		$string = '';
		
		if(is_array($matches) && isset($matches[$group])) {
			$string = $matches[$group];
		}
		
		return $string;
	}
	
	function filter_secs_pretty($string, $precision=0) {
		if(!is_numeric($string))
			return '';
		
		return DevblocksPlatform::strSecsToString($string, $precision);
	}
	
	function filter_sha1($string) {
		if(!is_string($string))
			return '';
		
		return sha1($string);
	}
	
	function filter_split_crlf($string) {
		if(!is_string($string))
			return '';
		
		return DevblocksPlatform::parseCrlfString($string);
	}
	
	function filter_split_csv($string) {
		if(!is_string($string))
			return '';
		
		return DevblocksPlatform::parseCsvString($string);
	}
	
	/**
	 * https://github.com/fabpot/Twig-extensions/blob/master/lib/Twig/Extensions/Extension/Text.php
	 *
	 * @param string $value
	 * @param integer $length
	 * @param boolean $preserve
	 * @param string $separator
	 *
	 */
	function filter_truncate($value, $length = 30, $preserve = false, $separator = '...') {
		if(!is_string($value))
			return '';
		
		if (mb_strlen($value, LANG_CHARSET_CODE) > $length) {
			if ($preserve) {
				if (false !== ($breakpoint = mb_strpos($value, ' ', $length, LANG_CHARSET_CODE))) {
					$length = $breakpoint;
				}
			}
			return mb_substr($value, 0, $length, LANG_CHARSET_CODE) . $separator;
		}
		return $value;
	}
	
	function filter_unescape($string, $mode='html', $flags=null) {
		if(!is_string($string))
			$string = strval($string);
		
		return html_entity_decode($string, ENT_HTML401 | ENT_QUOTES); // $flags, LANG_CHARSET_CODE
	}
	
	function filter_url_decode($string, $as='') {
		if(!is_string($string))
			return '';
		
		switch(DevblocksPlatform::strLower($as)) {
			case 'json':
				$array = DevblocksPlatform::strParseQueryString($string);
				return json_encode($array);
				break;
			
			default:
				return rawurldecode($string);
				break;
		}
	}
	
	public function getTests() {
		return array(
			new Twig_SimpleTest('numeric', [$this, 'test_numeric']),
		);
	}
	
	function test_numeric($value) {
		return is_numeric($value);
	}
};
endif;