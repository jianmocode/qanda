<?php
/**
 * Class AnswerController
 * 回答控制器
 *
 * 程序作者: XpmSE机器人
 * 最后修改: 2019-01-27 20:45:36
 * 程序母版: /data/stor/private/templates/xpmsns/model/code/controller/Name.php
 */

use \Xpmse\Loader\App;
use \Xpmse\Excp;
use \Xpmse\Utils;

class AnswerController extends \Xpmse\Loader\Controller {


	function __construct() {
	}

	/**
	 * 回答列表检索
	 */
	function index() {	

		$search  = $query = $_GET;
		$inst = new \Xpmsns\Qanda\Model\Answer;
		if ( !empty($search['order']) ) {
			$order = $search['order'];
			unset( $search['order'] );
			$search[$order] = 1;
		}

		$response = $inst->search($search);
		$data = [
			'_TITLE' => "回答列表检索",
			'query' => $query,
			'response' => $response
		];

		if ( $_GET['debug'] == 1 ) {
			Utils::out($data);
			return;
		}

		App::render($data,'answer','search.index');

		return [
			'js' => [
		 			"js/plugins/select2/select2.full.min.js",
		 			"js/plugins/select2/i18n/zh-CN.js",
		 			"js/plugins/jquery-validation/jquery.validate.min.js",
		 			"js/plugins/dropzonejs/dropzone.min.js",
		 			"js/plugins/cropper/cropper.min.js",
		 			'js/plugins/masked-inputs/jquery.maskedinput.min.js',
		 			'js/plugins/jquery-tags-input/jquery.tagsinput.min.js',
		    		'js/plugins/jquery-ui/jquery-ui.min.js',
	        		'js/plugins/bootstrap-datepicker/bootstrap-datepicker.min.js',
				],
			'css'=>[
	 			"js/plugins/select2/select2.min.css",
	 			"js/plugins/select2/select2-bootstrap.min.css"
	 		],
			'crumb' => [
	            "回答" => APP::R('answer','index'),
	            "回答管理" =>'',
	        ]
		];
	}


	/**
	 * 回答详情表单
	 */
	function detail() {

		$answer_id = trim($_GET['answer_id']);
		$action_name = '新建回答';
		$inst = new \Xpmsns\Qanda\Model\Answer;
		
		if ( !empty($answer_id) ) {
			$rs = $inst->getByAnswerId($answer_id);
			if ( !empty($rs) ) {
				$action_name =  $rs['answer_id'];
			}
		}

		$data = [
			'action_name' =>  $action_name,
			'answer_id'=>$answer_id,
			'rs' => $rs
		];

		if ( $_GET['debug'] == 1 ) {
			Utils::out($data);
			return;
		}


		App::render($data,'answer','form');

		return [
			'js' => [
		 			"js/plugins/select2/select2.full.min.js",
		 			"js/plugins/select2/i18n/zh-CN.js",
		 			"js/plugins/dropzonejs/dropzone.min.js",
		 			"js/plugins/cropper/cropper.min.js",
		 			"js/plugins/jquery-tags-input/jquery.tagsinput.min.js",
		 			"js/plugins/bootstrap-datepicker/bootstrap-datepicker.min.js",
		 			'js/plugins/masked-inputs/jquery.maskedinput.min.js',
		 			"js/plugins/jquery-validation/jquery.validate.min.js",
		    		"js/plugins/jquery-ui/jquery-ui.min.js",
		    		"js/plugins/summernote/summernote.min.js",
                    "js/plugins/summernote/lang/summernote-zh-CN.js",
                    "js/plugins/codemirror/lib/codemirror.js",
                    "js/plugins/codemirror/addon/search/searchcursor.js",
                    "js/plugins/codemirror/addon/search/search.js",
                    "js/plugins/codemirror/addon/dialog/dialog.js",
                    "js/plugins/codemirror/addon/edit/matchbrackets.js",
                    "js/plugins/codemirror/addon/edit/closebrackets.js",
                    "js/plugins/codemirror/addon/comment/comment.js",
                    "js/plugins/codemirror/addon/wrap/hardwrap.js",
                    "js/plugins/codemirror/addon/fold/foldcode.js",
                    "js/plugins/codemirror/addon/fold/brace-fold.js",
                    "js/plugins/codemirror/mode/javascript/javascript.js",
                    "js/plugins/codemirror/mode/shell/shell.js",
                    "js/plugins/codemirror/mode/sql/sql.js",
                    "js/plugins/codemirror/mode/python/python.js",
                    "js/plugins/codemirror/mode/go/go.js",
                    "js/plugins/codemirror/mode/php/php.js",
                    "js/plugins/codemirror/mode/htmlmixed/htmlmixed.js",
                    "js/plugins/codemirror/mode/xml/xml.js",
                    "js/plugins/codemirror/mode/css/css.js",
                    "js/plugins/codemirror/mode/sass/sass.js",
                    "js/plugins/codemirror/mode/vue/vue.js",
                    "js/plugins/codemirror/mode/textile/textile.js",
                    "js/plugins/codemirror/mode/clike/clike.js",
                    "js/plugins/codemirror/mode/markdown/markdown.js",
                    "js/plugins/codemirror/keymap/sublime.js",
				],
			'css'=>[
				"js/plugins/bootstrap-datepicker/bootstrap-datepicker3.min.css",
	 			"js/plugins/select2/select2.min.css",
	 			"js/plugins/select2/select2-bootstrap.min.css",
	 			"js/plugins/jquery-tags-input/jquery.tagsinput.min.css",
	 			"js/plugins/summernote/summernote.css",
                "js/plugins/summernote/summernote-bs3.min.css",
                "js/plugins/codemirror/lib/codemirror.css",
                "js/plugins/codemirror/addon/fold/foldgutter.css",
                "js/plugins/codemirror/addon/dialog/dialog.css",
                "js/plugins/codemirror/theme/monokai.css",
	 		],

			'crumb' => [
	            "回答" => APP::R('answer','index'),
	            "回答管理" =>APP::R('answer','index'),
	            "$action_name" => ''
	        ],
	        'active'=> [
	 			'slug'=>'xpmsns/qanda/answer/index'
	 		]
		];

	}



	/**
	 * 保存回答
	 * @return
	 */
	function save() {
        $data = $_POST;
        Utils::JsonFromInput( $data );
		$inst = new \Xpmsns\Qanda\Model\Answer;
		$rs = $inst->saveByAnswerId( $data );
		echo json_encode($rs);
	}

	/**
	 * 删除回答
	 * @return [type] [description]
	 */
	function remove(){
		$answer_id = $_POST['answer_id'];
		$inst = new \Xpmsns\Qanda\Model\Answer;
		$answer_ids =$inst->remove( $answer_id, "answer_id" );
		echo json_encode(['message'=>"删除成功", 'extra'=>['$answer_ids'=>$answer_ids]]);
	}

	/**
	 * 复制回答
	 * @return
	 */
	function duplicate(){
		$answer_id = $_GET['answer_id'];
		$inst = new \Xpmsns\Qanda\Model\Answer;
		$rs = $inst->getByAnswerId( $answer_id );
		$action_name =  $rs['answer_id'] . ' 副本';

		// 删除唯一索引字段
		unset($rs['answer_id']);

		// 复制图片

		$data = [
			'action_name' =>  $action_name,
			'answer_id'=>$answer_id,
			'rs' => $rs
		];

		if ( $_GET['debug'] == 1 ) {
			Utils::out($data);
			return;
		}

		
		App::render($data,'answer','form');

		return [
			'js' => [
		 			"js/plugins/select2/select2.full.min.js",
		 			"js/plugins/select2/i18n/zh-CN.js",
		 			"js/plugins/dropzonejs/dropzone.min.js",
		 			"js/plugins/cropper/cropper.min.js",
		 			"js/plugins/jquery-tags-input/jquery.tagsinput.min.js",
		 			"js/plugins/bootstrap-datepicker/bootstrap-datepicker.min.js",
		 			'js/plugins/masked-inputs/jquery.maskedinput.min.js',
		 			"js/plugins/jquery-validation/jquery.validate.min.js",
		    		"js/plugins/jquery-ui/jquery-ui.min.js",
		    		"js/plugins/summernote/summernote.min.js",
                    "js/plugins/summernote/lang/summernote-zh-CN.js",
                    "js/plugins/codemirror/lib/codemirror.js",
                    "js/plugins/codemirror/addon/search/searchcursor.js",
                    "js/plugins/codemirror/addon/search/search.js",
                    "js/plugins/codemirror/addon/dialog/dialog.js",
                    "js/plugins/codemirror/addon/edit/matchbrackets.js",
                    "js/plugins/codemirror/addon/edit/closebrackets.js",
                    "js/plugins/codemirror/addon/comment/comment.js",
                    "js/plugins/codemirror/addon/wrap/hardwrap.js",
                    "js/plugins/codemirror/addon/fold/foldcode.js",
                    "js/plugins/codemirror/addon/fold/brace-fold.js",
                    "js/plugins/codemirror/mode/javascript/javascript.js",
                    "js/plugins/codemirror/mode/shell/shell.js",
                    "js/plugins/codemirror/mode/sql/sql.js",
                    "js/plugins/codemirror/mode/python/python.js",
                    "js/plugins/codemirror/mode/go/go.js",
                    "js/plugins/codemirror/mode/php/php.js",
                    "js/plugins/codemirror/mode/htmlmixed/htmlmixed.js",
                    "js/plugins/codemirror/mode/xml/xml.js",
                    "js/plugins/codemirror/mode/css/css.js",
                    "js/plugins/codemirror/mode/sass/sass.js",
                    "js/plugins/codemirror/mode/vue/vue.js",
                    "js/plugins/codemirror/mode/textile/textile.js",
                    "js/plugins/codemirror/mode/clike/clike.js",
                    "js/plugins/codemirror/mode/markdown/markdown.js",
                    "js/plugins/codemirror/keymap/sublime.js",
				],
			'css'=>[
				"js/plugins/bootstrap-datepicker/bootstrap-datepicker3.min.css",
	 			"js/plugins/select2/select2.min.css",
	 			"js/plugins/select2/select2-bootstrap.min.css",
	 			"js/plugins/jquery-tags-input/jquery.tagsinput.min.css",
	 			"js/plugins/summernote/summernote.css",
                "js/plugins/summernote/summernote-bs3.min.css",
                "js/plugins/codemirror/lib/codemirror.css",
                "js/plugins/codemirror/addon/fold/foldgutter.css",
                "js/plugins/codemirror/addon/dialog/dialog.css",
                "js/plugins/codemirror/theme/monokai.css",
	 		],

			'crumb' => [
	            "回答" => APP::R('answer','index'),
	            "回答管理" =>APP::R('answer','index'),
	            "$action_name" => ''
	        ],
	        'active'=> [
	 			'slug'=>'xpmsns/qanda/answer/index'
	 		]
		];
	}



}