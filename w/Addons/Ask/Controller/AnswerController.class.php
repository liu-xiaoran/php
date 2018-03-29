<?php

namespace Addons\Ask\Controller;

use Think\ManageBaseController;

class AnswerController extends ManageBaseController {
	var $model;
	var $ask_id;
	function _initialize() {
		parent::_initialize ();
		$this->model = $this->getModel ( 'ask_answer' );
		$param ['mdm'] =  I( 'mdm' );
		$param ['ask_id'] = $this->ask_id = intval ( $_REQUEST ['ask_id'] );
		
		$res ['title'] = '微抢答';
		$res ['url'] = addons_url ( 'Ask://Ask/lists', $this->get_param );
		$nav [] = $res;
		
		$res ['title'] = '数据管理';
		$res ['url'] = addons_url ( 'Ask://Answer/lists', $param );
		$res ['class'] = 'current';
		$nav [] = $res;
		
		$this->assign ( 'nav', $nav );
	}
	// 通用插件的列表模型
	public function lists() {
		$this->assign ( 'add_button', false );
		$this->assign ( 'search_button', false );
		$this->assign ( 'del_button', false );
		$this->assign ( 'check_all', false );
		
		$top_more_button [] = array (
				'title' => '导出数据',
				'url' => U ( 'export', array (
						'ask_id' => $this->ask_id 
				) ) 
		);
		$this->assign ( 'top_more_button', $top_more_button );
		
		$map ['ask_id'] = $this->ask_id;
		session ( 'common_condition', $map );
		
		$list = $this->_get_model_list ( $this->model );
		$qdao = D ( 'AskQuestion' );
		foreach ( $list ['list_data'] as &$vo ) {
			// $user = get_followinfo ( $vo ['uid'] );
			// $vo ['nickname'] = $user ['nickname'];
			$question_id = $vo ['question_id'];
			$question=$qdao->getQuestionsByAskid( $this->ask_id,$question_id);
			$vo ['nickname'] = get_nickname ( $vo ['uid'] );
			$vo ['times'] += 1;
			$vo ['question_id'] = $qdao->getQuestionTitle ( $question_id, $this->ask_id );
// 			$vo ['true_answer'] = $qdao->getQuestionAnswer ( $question_id, $this->ask_id );
			$vo ['true_answer'] = $question['answer'];
			// $vo ['answer']= implode ( ', ', unserialize ( $vo ['answer'] ) );
			$vo ['answer'] =  unserialize ( $vo ['answer'] ) ;
			if (is_array($vo['answer'])){
			    $extra=preg_split('/[;\r\n|]+/s', $question['extra']);
			    $strAns='';
			    foreach ($vo['answer'] as $vv){
			        foreach ($extra as $key => $value) {
			            $ex=explode(':',$value);
			            if ($ex[0] && $ex[0] == $vv){
			                $strAns.=$vv.':'.$ex[1].' ';
			                break;
			            }
			            unset($ex);
			        }
			    }
			   $vo['answer']=$strAns;
			}
// 			$vo ['answer'] = $qdao->getQuestionAnswerExtra ( $question_id, $this->ask_id, $vo ['answer'] );
			
// 			$vo ['is_correct'] = $vo ['is_correct'] == 1 ? '是' : '否';
		}
		
// 		dump($list['list_data']);
		$this->assign ( $list );
		// dump($list);exit;
		
		$this->display ();
	}
	function export() {
		set_time_limit ( 0 );
		// 获取模型信息
		$model = $this->model;
		// 解析列表规则
		$list_data = $this->_list_grid ( $model );
		$grids = $list_data ['list_grids'];
		$fields = $list_data ['fields'];
		
		foreach ( $grids as $v ) {
			if ($v ['title'] == '操作') {
				array_pop ( $grids );
			} else {
				
				$ht [$v ['name']] = $v ['title'];
			}
		}
		$dataArr [0] = $ht;
		
		// 搜索条件
		$map = $this->_search_map ( $model, $fields );
		$map ['ask_id'] = $this->ask_id;
		
		$name = parse_name ( get_table_name ( $model ['id'] ), true );
		$data = M ( $name )->field ( empty ( $fields ) ? true : $fields )->where ( $map )->order ( 'id desc' )->select ();
		
		$dataTable = D ( 'Common/Model' )->getFileInfo ( $model );
		$data = $this->parseData ( $data, $dataTable->fields, $dataTable->list_grid, $dataTable->config );
		
		if ($data) {
			$qdao = D ( 'AskQuestion' );
			foreach ( $data as &$vv ) {
				$user = get_followinfo ( $vv ['uid'] );
				$vv ['nickname'] = $user ['nickname'];
				
				$vv ['times'] += 1;
				$vv ['question_id'] = $qdao->getQuestionTitle ( $vv ['question_id'], $this->ask_id );
				$vv ['answer'] = implode ( ', ', unserialize ( $vv ['answer'] ) );
				$vv ['is_correct'] = $vv ['is_correct'] == 1 ? '是' : '否';
			}
			
			foreach ( $data as &$vo ) {
				
				foreach ( $ht as $key => $val ) {
					$newArr [$key] = empty ( $vo [$key] ) ? ' ' : $vo [$key];
				}
				$vo = $newArr;
			}
			
			$dataArr = array_merge ( $dataArr, $data );
		}
		
		outExcel ( $dataArr );
	}
	function detail() {
		$this->assign ( 'add_button', false );
		$this->assign ( 'search_button', false );
		$this->assign ( 'del_button', false );
		$this->assign ( 'check_all', false );
		
		// 解析列表规则
		$fields [] = 'question';
		$fields [] = 'answer';
		
		$girds ['field'] [0] = 'question';
		$girds ['title'] = '问题';
		$list_data ['list_grids'] [] = $girds;
		
		$girds ['field'] [0] = 'answer';
		$girds ['title'] = '回答内容';
		$list_data ['list_grids'] [] = $girds;
		
		$girds ['field'] [0] = 'cTime';
		$girds ['title'] = '回答时间';
		$list_data ['list_grids'] [] = $girds;
		
		$list_data ['fields'] = $fields;
		$this->assign ( $list_data );
		
		$map ['ask_id'] = intval ( $_REQUEST ['ask_id'] );
		$questions = M ( 'ask_question' )->where ( $map )->select ();
		foreach ( $questions as $q ) {
			$title [$q ['id']] = $q ['title'];
			$type [$q ['id']] = $q ['type'];
			$extra [$q ['id']] = parse_config_attr ( $q ['extra'] );
		}
		
		$map ['uid'] = intval ( $_REQUEST ['uid'] );
		$answers = D ( 'AskAnswer' )->where ( $map )->order ( 'id desc' )->select ();
		
		foreach ( $answers as $a ) {
			$qid = $a ['question_id'];
			if (! $title [$qid])
				continue;
			
			$data ['question'] = $title [$qid];
			$value = unserialize ( $a ['answer'] );
			
			switch ($type [$qid]) {
				case 'radio' :
				case 'checkbox' :
					foreach ( $value as $v ) {
						$data ['answer'] [] = $extra [$qid] [$v];
					}
					$data ['answer'] = implode ( ',', $data ['answer'] );
					break;
				default :
					$data ['answer'] = $value;
			}
			$data ['cTime'] = time_format ( $a ['cTime'] );
			$list [] = $data;
			unset ( $data );
		}
		
		$this->assign ( 'list_data', $list );
		
		$this->display ( T ( 'Addons/lists' ) );
	}
	
	// 通用插件的删除模型
	public function del() {
		parent::common_del ( $this->model );
	}
}
