<?php
		
/**
* jPList DataBase Class
*/
class jplist_db{
	
	//status (from client)
	public $statuses;
	
	//wordpress db
	public $wpdb;
	
	/**
	* constructor
	*/
	public function jplist_db(){
		
		//init properties
		$this->wpdb = $GLOBALS['wpdb'];
	}
	
	/**
	* get checkbox group filter query
	* @param {Array.<string>} pathGroup - paths list
	* @return {string} query
	* @param {Array.<string>} $preparedParams - array of params for prepare statement
	*/
	function getCheckboxGroupFilterQuery($keyword, $pathGroup, &$preparedParams){
		
		$path = "";
		$length = count($pathGroup);
		$query = "";
		
		for($i=0; $i<$length; $i++){
			
			//get path
			$path = $pathGroup[$i];
			
			//replace dot
			$path = str_replace(array("."), "", $path);
			
			if($i !== 0){
				$query .= " or ";
			}
			
			$query .= " " . $keyword . " like '%%%s%%' ";
			array_push($preparedParams, "$path");
		}
		
		return $query;
	}
	
	/**
	* get sort query
	* @param {Object} $status
	* @param {Array.<string>} $preparedParams - array of params for prepare statement
	* @return {string}
	* sort example
	* {
    *     "action": "sort",
    *     "name": "sort",
    *     "type": "drop-down",
    *     "data": {
    *         "path": ".like",
    *         "type": "number",
    *         "order": "asc",
    *         "dateTimeFormat": "{month}/{day}/{year}"
    *     },
    *     "cookies": true
    * }
	*/
	function getSortQuery($status, &$preparedParams){
		
		$query = "";
		$data = $status->data;
		$order = "asc";
		
		if(isset($data) && isset($data->path) && $data->path){
		
			switch($data->path){
			
				case ".jplist-title":{
					$query = "order by post_title";
					break;
				}
				
				case ".jplist-comments":{
					$query = "order by comment_count";
					break;
				}
				
				case ".jplist-date":{
					$query = "order by post_date";
					break;
				}
			}
			
			if(isset($data->order)){
				$order = strtolower($data->order);
			}
			
			($order == "desc") ? "desc" : "asc";
			
			if($query){
				$query = $query . " " . $order;
			}
		}
		
		return $query;
	}
	
	/**
	* get filter query
	* @param {Object} $status
	* @param {string} $prevQuery - prev filter query
	* @param {Array.<string>} $preparedParams - array of params for prepare statement
	* @return {string}
	* status example
	* {
    *     "action": "filter",
    *     "name": "title-filter",
    *     "type": "textbox",
    *     "data": {
    *         "path": ".jplist-title",
    *         "ignore": "[~!@#$%^&*()+=`'\"/\\_]+",
    *         "value": "",
    *         "filterType": "text"
    *     },
    *     "cookies": true
    * }
	*/
	function getFilterQuery($status, $prevQuery, &$preparedParams){
		
		$query = "";
		$name = $status->name;
		$data = $status->data;		
		
		if(isset($name) && isset($data)){
				
			switch($name){
			
				case "title-filter":{
				
					if(isset($data->path) && isset($data->value) && $data->value){
						$prevQueryNotEmpty = strrpos($prevQuery, "where");
						if($prevQueryNotEmpty === false){						
							$query = "where `post_title` like '%%%s%%' ";
							array_push($preparedParams, $data->value);
						}
						else{
							$query = " and `post_title` like '%%%s%%' ";
							array_push($preparedParams, $data->value);
						}
					}
									
					break;
				}
				
				case "content-filter":{
					if(isset($data->path) && isset($data->value) && $data->value){
						$prevQueryNotEmpty = strrpos($prevQuery, "where");
						if($prevQueryNotEmpty === false){
							$query = "where `post_content` like '%%%s%%' ";
							array_push($preparedParams, $data->value);
						}
						else{
							$query = " and `post_content` like '%%%s%%' ";
							array_push($preparedParams, $data->value);
						}
					}
					break;
				}
				
				case "categories":{
					if(isset($data->pathGroup) && is_array($data->pathGroup)){
						$prevQueryNotEmpty = strrpos($prevQuery, "where");
						$query = "";
						$filter = $this->getCheckboxGroupFilterQuery("slug", $data->pathGroup, $preparedParams);
						
						if($filter){
							if($prevQueryNotEmpty === false){
								$query = "where " . $filter;
							}
							else{
								$query = " and (" . $filter . ")";
							}
						}						
					}
					break;
				}
			}
		}
		
		return $query;
	}
	
	/**
	* get pagination query
	* @param {Object} $status
	* @param {number} $count - all items number (after the filters were applied)
	* @param {Array.<string>} $preparedParams - array of params for prepare statement
	* @return {string}
	* status example
	* {
    *     "action": "paging",
    *     "name": "paging",
    *     "type": "placeholder",
    *     "data": {
    *         "number": "10",
    *         "currentPage": 0,
    *         "paging": null
    *     },
    *     "cookies": true
    * }
	*/
	function getPagingQuery($status, $count, &$preparedParams){
		
		$query = "";
		$data = $status->data;
		$currentPage = 0;
		$number = 0;
		
		if(isset($data)){
		
			if(is_numeric($data->currentPage)){
				$currentPage = intval($data->currentPage);
			}
			
			if(is_numeric($data->number)){
				$number = intval($data->number);
			}
			
			if($count > $data->number){
				$query = "LIMIT " . $currentPage * $number . ", " . $number;
			}
		}
		
		return $query;
	}
	
	/**
	* get the whole content with wrapper 
	* it used for pagination count
	* @param {string} $itemsJSON - items json
	* @param {number} $count - all items number
	* @return {string} html
	*/
	function getWrapper($itemsJSON, $count){
		
		$json = "";
		
		$json .= "{";
		$json .= "\"count\":" . $count;
		$json .= ",\"data\":" . $itemsJSON;
		$json .= "}";		
		
		return $json;
	}
	
	/**
	* get json by statuses
	* @param {Object} $statuses - json from client - controls statuses
	* @return {Object} json - posts
	*/
	public function get_posts_json($statuses){
		
		//init properties
		$this->statuses = $statuses;	
		$json = "[]";		
		$preparedParams = array();
		$pagingStatus = null;
		$filter = "where `post_status` = 'publish' and `post_type` = 'post' ";
		$sort = "";
		$query = "";
		$count = 0;
		$counter = 0;
		
		try{			
			if(isset($statuses)){
								
				//statuses => array
				$statuses = json_decode(urldecode($statuses));	
								
				foreach($statuses as $key => $value){
					
					switch($value->action){
					
						case "paging":{
							$pagingStatus = $value;
							break; 
						}
						
						case "filter":{							
							$filter .= $this->getFilterQuery($value, $filter, $preparedParams);	
							break; 
						}
						
						case "sort":{
							$sort = $this->getSortQuery($value, $preparedParams);
							break; 
						}
					}
				}	
			}
			
			//count database items for pagination
			//$query = "SELECT count(ID) FROM wp_posts " . $filter . " " . $sort;			
			$query = "";
			$query .= "SELECT count(ID) ";
			$query .= "FROM wp_posts ";
			$query .= "INNER JOIN wp_term_relationships ON wp_posts.ID  = wp_term_relationships.object_id ";
			$query .= "INNER JOIN wp_terms ON wp_term_relationships.term_taxonomy_id = wp_terms.term_id ";
			$query .= $filter . " " . $sort;
			
			if(count($preparedParams) > 0){
				
				$count = $this->wpdb->get_var(
					$this->wpdb->prepare(
						$query
						,$preparedParams
					)
				);
			}
			else{
				$count = $this->wpdb->get_var($query);
			}
			
			//init pagination query
			if($pagingStatus){
				$paging = $this->getPagingQuery($pagingStatus, $count, $preparedParams);
			}
			
			//init query with sort and filter
			/*
			SELECT wp_posts.ID, wp_posts.post_date, wp_posts.post_content, wp_posts.post_title, wp_posts.post_name, wp_posts.comment_count, wp_terms.name, wp_terms.slug
			FROM wp_posts 
			INNER JOIN wp_term_relationships ON wp_posts.ID  = wp_term_relationships.object_id
			INNER JOIN wp_terms ON wp_term_relationships.term_taxonomy_id = wp_terms.term_id
			WHERE `post_status` = 'publish' and `post_type` = 'post' 
			*/
			$query = "";
			$query .= "SELECT wp_posts.ID, wp_posts.post_date, wp_posts.post_content, wp_posts.post_title, wp_posts.post_name, wp_posts.comment_count, wp_terms.name, wp_terms.slug ";
			$query .= "FROM wp_posts ";
			$query .= "INNER JOIN wp_term_relationships ON wp_posts.ID  = wp_term_relationships.object_id ";
			$query .= "INNER JOIN wp_terms ON wp_term_relationships.term_taxonomy_id = wp_terms.term_id ";
			$query .= $filter . " " . $sort . " " . $paging;
			//$query = "SELECT * FROM wp_posts " . $filter . " " . $sort . " " . $paging;
									
			if(count($preparedParams) > 0){
			
				$items = $this->wpdb->get_results(
					$this->wpdb->prepare(
						$query
						,$preparedParams
					)
				);				
			}
			else{
				$items = $this->wpdb->get_results($query, OBJECT);
			}
			
			$json = "[";
			foreach($items as $post){
				
				if($counter > 0){
					$json .= ",";
				}
				
				//add additional properties
				$link = get_permalink($post->ID);
				$thumb = get_the_post_thumbnail($post->ID, 'thumbnail', '');
				$excerpt = wp_trim_words($post->post_content);
				$date = get_the_date('', $post->ID);
				$time = get_the_time('', $post->ID);
				$hidden_date = get_the_date('Y-n-j ', $post->ID);
				$hidden_time = get_the_time('G-i-s', $post->ID);
				
				$post->link = $link;
				$post->thumb = $thumb;
				$post->excerpt = $excerpt;
				$post->date = $date;
				$post->time = $time;
				$post->hidden_date = $hidden_date;
				$post->hidden_time = $hidden_time;
				
				$json .= json_encode($post);
				
				$counter++;
			}
			$json .= "]";
			
		}
		catch(Exception $ex){
			print 'Exception: ' . $ex->getMessage();
		}
		
		return $this->getWrapper($json, $count);
	}
}
?>