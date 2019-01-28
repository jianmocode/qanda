<?php
/**
 * Class Answer 
 * 回答数据模型
 *
 * 程序作者: XpmSE机器人
 * 最后修改: 2019-01-28 18:19:17
 * 程序母版: /data/stor/private/templates/xpmsns/model/code/model/Name.php
 */
namespace Xpmsns\Qanda\Model;
                       
use \Xpmse\Excp;
use \Xpmse\Model;
use \Xpmse\Utils;
use \Xpmse\Conf;
use \Mina\Cache\Redis as Cache;
use \Xpmse\Loader\App as App;
use \Xpmse\Job;


class Answer extends Model {




    /**
     * 数据缓存对象
     */
    protected $cache = null;

	/**
	 * 回答数据模型【3】
	 * @param array $param 配置参数
	 *              $param['prefix']  数据表前缀，默认为 xpmsns_qanda_
	 */
	function __construct( $param=[] ) {

		parent::__construct(array_merge(['prefix'=>'xpmsns_qanda_'],$param));
        $this->table('answer'); // 数据表名称 xpmsns_qanda_answer
         // + Redis缓存
        $this->cache = new Cache([
            "prefix" => "xpmsns_qanda_answer:",
            "host" => Conf::G("mem/redis/host"),
            "port" => Conf::G("mem/redis/port"),
            "passwd"=> Conf::G("mem/redis/password")
        ]);


       
	}

	/**
	 * 自定义函数 
	 */

    // @KEEP BEGIN
    /**
     * 关联某人赞同数据
     * @param array &$rows 回答数据
     * @param string $user_id 用户ID 
     * @return null
     */
    function withAgree( & $rows, $user_id, $select=["agree.origin_outer_id","agree.agree_id","user.user_id","user.name","user.nickname","user.mobile","agree.origin","agree.outer_id","agree.created_at","agree.updated_at"]) {

        $ids = array_column( $rows, "answer_id");
        if ( empty( $ids) ) {
            return;
        }

        // 读取赞同信息
        $ag = new \Xpmsns\Comment\Model\Agree;
        $origin_outer_ids = array_map(function($id) use( $user_id ){ return "answer_{$user_id}_{$id}"; }, $ids);
        $agrees = $ag->getInByOriginOuterId($origin_outer_ids, $select);

        // 合并到数据表
        foreach($rows as & $rs ) {
            $origin_outer_id = "answer_{$user_id}_{$rs['answer_id']}";
            $rs["agree"] = $agrees[$origin_outer_id];
            if (is_null($rs["agree"]) ){
                $rs["agree"] = [];
                $rs["has_agreed"] = false;
            }  else {
                $rs["has_agreed"] = true;
            }
        }
    }
    // @KEEP END


	/**
	 * 创建数据表
	 * @return $this
	 */
	public function __schema() {

		// 回答ID
		$this->putColumn( 'answer_id', $this->type("string", ["length"=>128, "unique"=>true, "null"=>true]));
		// 提问ID
		$this->putColumn( 'question_id', $this->type("string", ["length"=>128, "index"=>true, "null"=>true]));
		// 用户ID
		$this->putColumn( 'user_id', $this->type("string", ["length"=>128, "index"=>true, "null"=>true]));
		// 摘要
		$this->putColumn( 'summary', $this->type("string", ["length"=>400, "index"=>true, "null"=>true]));
		// 正文
		$this->putColumn( 'content', $this->type("longText", ["null"=>true]));
		// 发布时间
		$this->putColumn( 'publish_time', $this->type("timestamp", ["index"=>true, "null"=>true]));
		// 访问策略
		$this->putColumn( 'policies', $this->type("string", ["length"=>128, "index"=>true, "default"=>"public", "null"=>true]));
		// 访问策略明细
		$this->putColumn( 'policies_detail', $this->type("text", ["json"=>true, "null"=>true]));
		// 优先级
		$this->putColumn( 'priority', $this->type("integer", ["length"=>1, "index"=>true, "default"=>"9999", "null"=>true]));
		// 浏览量
		$this->putColumn( 'view_cnt', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 赞同量
		$this->putColumn( 'agree_cnt', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 获得积分
		$this->putColumn( 'coin', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 获得金额
		$this->putColumn( 'money', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 围观积分
		$this->putColumn( 'coin_view', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 围观金额
		$this->putColumn( 'money_view', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 是否匿名
		$this->putColumn( 'anonymous', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 是否采纳
		$this->putColumn( 'accepted', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 状态
		$this->putColumn( 'status', $this->type("string", ["length"=>32, "index"=>true, "default"=>"opened", "null"=>true]));
		// 修改历史
		$this->putColumn( 'history', $this->type("longText", ["json"=>true, "null"=>true]));

		return $this;
	}


	/**
	 * 处理读取记录数据，用于输出呈现
	 * @param  array $rs 待处理记录
	 * @return
	 */
	public function format( & $rs ) {
     
		$fileFields = []; 

        // 处理图片和文件字段 
        $this->__fileFields( $rs, $fileFields );

		// 格式化: 状态
		// 返回值: "_status_types" 所有状态表述, "_status_name" 状态名称,  "_status" 当前状态表述, "status" 当前状态数值
		if ( array_key_exists('status', $rs ) && !empty($rs['status']) ) {
			$rs["_status_types"] = [
		  		"opened" => [
		  			"value" => "opened",
		  			"name" => "开放",
		  			"style" => "success"
		  		],
		  		"closed" => [
		  			"value" => "closed",
		  			"name" => "关闭",
		  			"style" => "danger"
		  		],
		  		"forbidden" => [
		  			"value" => "forbidden",
		  			"name" => "封禁",
		  			"style" => "danger"
		  		],
		  		"drafted" => [
		  			"value" => "drafted",
		  			"name" => "草稿",
		  			"style" => "muted"
		  		],
			];
			$rs["_status_name"] = "status";
			$rs["_status"] = $rs["_status_types"][$rs["status"]];
		}

		// 格式化: 访问策略
		// 返回值: "_policies_types" 所有状态表述, "_policies_name" 状态名称,  "_policies" 当前状态表述, "policies" 当前状态数值
		if ( array_key_exists('policies', $rs ) && !empty($rs['policies']) ) {
			$rs["_policies_types"] = [
		  		"public" => [
		  			"value" => "public",
		  			"name" => "公开",
		  			"style" => "success"
		  		],
		  		"poster-only" => [
		  			"value" => "poster-only",
		  			"name" => "仅提问者可见",
		  			"style" => "danger"
		  		],
		  		"paid-only" => [
		  			"value" => "paid-only",
		  			"name" => "付费用户可见",
		  			"style" => "warning"
		  		],
			];
			$rs["_policies_name"] = "policies";
			$rs["_policies"] = $rs["_policies_types"][$rs["policies"]];
		}

		// 格式化: 是否采纳
		// 返回值: "_accepted_types" 所有状态表述, "_accepted_name" 状态名称,  "_accepted" 当前状态表述, "accepted" 当前状态数值
		if ( array_key_exists('accepted', $rs ) && !empty($rs['accepted']) ) {
			$rs["_accepted_types"] = [
		  		"0" => [
		  			"value" => "0",
		  			"name" => "暂未采纳",
		  			"style" => "muted"
		  		],
		  		"1" => [
		  			"value" => "1",
		  			"name" => "已采纳",
		  			"style" => "succes"
		  		],
			];
			$rs["_accepted_name"] = "accepted";
			$rs["_accepted"] = $rs["_accepted_types"][$rs["accepted"]];
		}

 
		// <在这里添加更多数据格式化逻辑>
		
		return $rs;
	}

	
	/**
	 * 按回答ID查询一条回答记录
	 * @param string $answer_id 唯一主键
	 * @return array $rs 结果集 
	 *          	  $rs["answer_id"],  // 回答ID 
	 *          	  $rs["question_id"],  // 提问ID 
	 *                $rs["question_question_id"], // question.question_id
	 *          	  $rs["user_id"],  // 用户ID 
	 *                $rs["user_user_id"], // user.user_id
	 *          	  $rs["summary"],  // 摘要 
	 *          	  $rs["content"],  // 正文 
	 *          	  $rs["publish_time"],  // 发布时间 
	 *          	  $rs["policies"],  // 访问策略 
	 *          	  $rs["policies_detail"],  // 访问策略明细 
	 *          	  $rs["priority"],  // 优先级 
	 *          	  $rs["view_cnt"],  // 浏览量 
	 *          	  $rs["agree_cnt"],  // 赞同量 
	 *          	  $rs["coin"],  // 获得积分 
	 *          	  $rs["money"],  // 获得金额 
	 *          	  $rs["coin_view"],  // 围观积分 
	 *          	  $rs["money_view"],  // 围观金额 
	 *          	  $rs["anonymous"],  // 是否匿名 
	 *          	  $rs["accepted"],  // 是否采纳 
	 *          	  $rs["status"],  // 状态 
	 *          	  $rs["history"],  // 修改历史 
	 *          	  $rs["created_at"],  // 创建时间 
	 *          	  $rs["updated_at"],  // 更新时间 
	 *                $rs["user_created_at"], // user.created_at
	 *                $rs["user_updated_at"], // user.updated_at
	 *                $rs["user_group_id"], // user.group_id
	 *                $rs["user_name"], // user.name
	 *                $rs["user_idno"], // user.idno
	 *                $rs["user_idtype"], // user.idtype
	 *                $rs["user_iddoc"], // user.iddoc
	 *                $rs["user_nickname"], // user.nickname
	 *                $rs["user_sex"], // user.sex
	 *                $rs["user_city"], // user.city
	 *                $rs["user_province"], // user.province
	 *                $rs["user_country"], // user.country
	 *                $rs["user_headimgurl"], // user.headimgurl
	 *                $rs["user_language"], // user.language
	 *                $rs["user_birthday"], // user.birthday
	 *                $rs["user_bio"], // user.bio
	 *                $rs["user_bgimgurl"], // user.bgimgurl
	 *                $rs["user_mobile"], // user.mobile
	 *                $rs["user_mobile_nation"], // user.mobile_nation
	 *                $rs["user_mobile_full"], // user.mobile_full
	 *                $rs["user_email"], // user.email
	 *                $rs["user_contact_name"], // user.contact_name
	 *                $rs["user_contact_tel"], // user.contact_tel
	 *                $rs["user_title"], // user.title
	 *                $rs["user_company"], // user.company
	 *                $rs["user_zip"], // user.zip
	 *                $rs["user_address"], // user.address
	 *                $rs["user_remark"], // user.remark
	 *                $rs["user_tag"], // user.tag
	 *                $rs["user_user_verified"], // user.user_verified
	 *                $rs["user_name_verified"], // user.name_verified
	 *                $rs["user_verify"], // user.verify
	 *                $rs["user_verify_data"], // user.verify_data
	 *                $rs["user_mobile_verified"], // user.mobile_verified
	 *                $rs["user_email_verified"], // user.email_verified
	 *                $rs["user_extra"], // user.extra
	 *                $rs["user_password"], // user.password
	 *                $rs["user_pay_password"], // user.pay_password
	 *                $rs["user_status"], // user.status
	 *                $rs["user_inviter"], // user.inviter
	 *                $rs["user_follower_cnt"], // user.follower_cnt
	 *                $rs["user_following_cnt"], // user.following_cnt
	 *                $rs["user_name_message"], // user.name_message
	 *                $rs["user_verify_message"], // user.verify_message
	 *                $rs["user_client_token"], // user.client_token
	 *                $rs["user_user_name"], // user.user_name
	 *                $rs["question_created_at"], // question.created_at
	 *                $rs["question_updated_at"], // question.updated_at
	 *                $rs["question_user_id"], // question.user_id
	 *                $rs["question_title"], // question.title
	 *                $rs["question_summary"], // question.summary
	 *                $rs["question_content"], // question.content
	 *                $rs["question_category_ids"], // question.category_ids
	 *                $rs["question_series_ids"], // question.series_ids
	 *                $rs["question_tags"], // question.tags
	 *                $rs["question_view_cnt"], // question.view_cnt
	 *                $rs["question_agree_cnt"], // question.agree_cnt
	 *                $rs["question_answer_cnt"], // question.answer_cnt
	 *                $rs["question_priority"], // question.priority
	 *                $rs["question_status"], // question.status
	 *                $rs["question_publish_time"], // question.publish_time
	 *                $rs["question_coin"], // question.coin
	 *                $rs["question_money"], // question.money
	 *                $rs["question_coin_view"], // question.coin_view
	 *                $rs["question_money_view"], // question.money_view
	 *                $rs["question_policies"], // question.policies
	 *                $rs["question_policies_detail"], // question.policies_detail
	 *                $rs["question_anonymous"], // question.anonymous
	 *                $rs["question_cover"], // question.cover
	 *                $rs["question_history"], // question.history
	 */
	public function getByAnswerId( $answer_id, $select=['*']) {
		
		if ( is_string($select) ) {
			$select = explode(',', $select);
		}


		// 增加表单查询索引字段
		array_push($select, "answer.answer_id");
		$inwhereSelect = $this->formatSelect( $select ); // 过滤 inWhere 查询字段

		// 创建查询构造器
		$qb = Utils::getTab("xpmsns_qanda_answer as answer", "{none}")->query();
 		$qb->leftJoin("xpmsns_user_user as user", "user.user_id", "=", "answer.user_id"); // 连接用户
 		$qb->leftJoin("xpmsns_qanda_question as question", "question.question_id", "=", "answer.question_id"); // 连接提问
		$qb->where('answer.answer_id', '=', $answer_id );
		$qb->limit( 1 );
		$qb->select($select);
		$rows = $qb->get()->toArray();
		if( empty($rows) ) {
			return [];
		}

		$rs = current( $rows );
		$this->format($rs);

  
  
		return $rs;
	}

		

	/**
	 * 按回答ID查询一组回答记录
	 * @param array   $answer_ids 唯一主键数组 ["$answer_id1","$answer_id2" ...]
	 * @param array   $order        排序方式 ["field"=>"asc", "field2"=>"desc"...]
	 * @param array   $select       选取字段，默认选取所有
	 * @return array 回答记录MAP {"answer_id1":{"key":"value",...}...}
	 */
	public function getInByAnswerId($answer_ids, $select=["answer.answer_id","question.title","user.name","user.nickname","answer.view_cnt","answer.agree_cnt","answer.policies","answer.accepted","answer.status","answer.created_at","answer.updated_at"], $order=["answer.view_cnt"=>"desc"] ) {
		
		if ( is_string($select) ) {
			$select = explode(',', $select);
		}

		// 增加表单查询索引字段
		array_push($select, "answer.answer_id");
		$inwhereSelect = $this->formatSelect( $select ); // 过滤 inWhere 查询字段

		// 创建查询构造器
		$qb = Utils::getTab("xpmsns_qanda_answer as answer", "{none}")->query();
 		$qb->leftJoin("xpmsns_user_user as user", "user.user_id", "=", "answer.user_id"); // 连接用户
 		$qb->leftJoin("xpmsns_qanda_question as question", "question.question_id", "=", "answer.question_id"); // 连接提问
		$qb->whereIn('answer.answer_id', $answer_ids);
		
		// 排序
		foreach ($order as $field => $order ) {
			$qb->orderBy( $field, $order );
		}
		$qb->select( $select );
		$data = $qb->get()->toArray(); 

		$map = [];

  		foreach ($data as & $rs ) {
			$this->format($rs);
			$map[$rs['answer_id']] = $rs;
			
  		}

  

		return $map;
	}


	/**
	 * 按回答ID保存回答记录。(记录不存在则创建，存在则更新)
	 * @param array $data 记录数组 (key:value 结构)
	 * @param array $select 返回的字段，默认返回全部
	 * @return array 数据记录数组
	 */
	public function saveByAnswerId( $data, $select=["*"] ) {

		if ( is_string($select) ) {
			$select = explode(',', $select);
		}

		// 增加表单查询索引字段
		array_push($select, "answer.answer_id");
		$inwhereSelect = $this->formatSelect( $select ); // 过滤 inWhere 查询字段
		$rs = $this->saveBy("answer_id", $data, ["answer_id"], ['_id', 'answer_id']);
		return $this->getByAnswerId( $rs['answer_id'], $select );
	}


	/**
	 * 添加回答记录
	 * @param  array $data 记录数组  (key:value 结构)
	 * @return array 数据记录数组 (key:value 结构)
	 */
	function create( $data ) {
		if ( empty($data["answer_id"]) ) { 
			$data["answer_id"] = $this->genId();
		}
		return parent::create( $data );
	}


	/**
	 * 查询前排回答记录
	 * @param integer $limit 返回记录数，默认100
	 * @param array   $select  选取字段，默认选取所有
	 * @param array   $order   排序方式 ["field"=>"asc", "field2"=>"desc"...]
	 * @return array 回答记录数组 [{"key":"value",...}...]
	 */
	public function top( $limit=100, $select=["answer.answer_id","question.title","user.name","user.nickname","answer.view_cnt","answer.agree_cnt","answer.policies","answer.accepted","answer.status","answer.created_at","answer.updated_at"], $order=["answer.view_cnt"=>"desc"] ) {

		if ( is_string($select) ) {
			$select = explode(',', $select);
		}

		// 增加表单查询索引字段
		array_push($select, "answer.answer_id");
		$inwhereSelect = $this->formatSelect( $select ); // 过滤 inWhere 查询字段

		// 创建查询构造器
		$qb = Utils::getTab("xpmsns_qanda_answer as answer", "{none}")->query();
 		$qb->leftJoin("xpmsns_user_user as user", "user.user_id", "=", "answer.user_id"); // 连接用户
 		$qb->leftJoin("xpmsns_qanda_question as question", "question.question_id", "=", "answer.question_id"); // 连接提问


		foreach ($order as $field => $order ) {
			$qb->orderBy( $field, $order );
		}
		$qb->limit($limit);
		$qb->select( $select );
		$data = $qb->get()->toArray();


  		foreach ($data as & $rs ) {
			$this->format($rs);
			
  		}

  
		return $data;
	
	}


	/**
	 * 按条件检索回答记录
	 * @param  array  $query
	 *         	      $query['select'] 选取字段，默认选择 ["answer.answer_id","question.title","user.name","user.nickname","answer.view_cnt","answer.agree_cnt","answer.policies","answer.accepted","answer.status","answer.created_at","answer.updated_at"]
	 *         	      $query['page'] 页码，默认为 1
	 *         	      $query['perpage'] 每页显示记录数，默认为 20
	 *			      $query["keyword"] 按关键词查询
	 *			      $query["answer_id"] 按回答ID查询 ( = )
	 *			      $query["question_id"] 按提问ID查询 ( = )
	 *			      $query["user_id"] 按用户ID查询 ( = )
	 *			      $query["after"] 按发布时间大于查询 ( >= )
	 *			      $query["before"] 按发布时间小于查询 ( <= )
	 *			      $query["coin"] 按获得积分查询 ( > )
	 *			      $query["money"] 按获得金额查询 ( > )
	 *			      $query["coin_view"] 按围观积分查询 ( > )
	 *			      $query["money_view"] 按围观金额查询 ( > )
	 *			      $query["policies"] 按访问策略查询 ( = )
	 *			      $query["accepted"] 按是否采纳查询 ( = )
	 *			      $query["exclude"] 按不包含查询 ( NOT-IN )
	 *			      $query["status"] 按状态查询 ( = )
	 *			      $query["view_desc"]  按浏览数量倒序 DESC 排序
	 *			      $query["agree_desc"]  按赞同数量倒序 DESC 排序
	 *			      $query["publish_desc"]  按发布时间倒序 DESC 排序
	 *			      $query["publish_asc"]  按发布时间正序 ASC 排序
	 *			      $query["priority_asc"]  按优先级正序 ASC 排序
	 *           
	 * @return array 回答记录集 {"total":100, "page":1, "perpage":20, data:[{"key":"val"}...], "from":1, "to":1, "prev":false, "next":1, "curr":10, "last":20}
	 *               	["answer_id"],  // 回答ID 
	 *               	["question_id"],  // 提问ID 
	 *               	["question_question_id"], // question.question_id
	 *               	["user_id"],  // 用户ID 
	 *               	["user_user_id"], // user.user_id
	 *               	["summary"],  // 摘要 
	 *               	["content"],  // 正文 
	 *               	["publish_time"],  // 发布时间 
	 *               	["policies"],  // 访问策略 
	 *               	["policies_detail"],  // 访问策略明细 
	 *               	["priority"],  // 优先级 
	 *               	["view_cnt"],  // 浏览量 
	 *               	["agree_cnt"],  // 赞同量 
	 *               	["coin"],  // 获得积分 
	 *               	["money"],  // 获得金额 
	 *               	["coin_view"],  // 围观积分 
	 *               	["money_view"],  // 围观金额 
	 *               	["anonymous"],  // 是否匿名 
	 *               	["accepted"],  // 是否采纳 
	 *               	["status"],  // 状态 
	 *               	["history"],  // 修改历史 
	 *               	["created_at"],  // 创建时间 
	 *               	["updated_at"],  // 更新时间 
	 *               	["user_created_at"], // user.created_at
	 *               	["user_updated_at"], // user.updated_at
	 *               	["user_group_id"], // user.group_id
	 *               	["user_name"], // user.name
	 *               	["user_idno"], // user.idno
	 *               	["user_idtype"], // user.idtype
	 *               	["user_iddoc"], // user.iddoc
	 *               	["user_nickname"], // user.nickname
	 *               	["user_sex"], // user.sex
	 *               	["user_city"], // user.city
	 *               	["user_province"], // user.province
	 *               	["user_country"], // user.country
	 *               	["user_headimgurl"], // user.headimgurl
	 *               	["user_language"], // user.language
	 *               	["user_birthday"], // user.birthday
	 *               	["user_bio"], // user.bio
	 *               	["user_bgimgurl"], // user.bgimgurl
	 *               	["user_mobile"], // user.mobile
	 *               	["user_mobile_nation"], // user.mobile_nation
	 *               	["user_mobile_full"], // user.mobile_full
	 *               	["user_email"], // user.email
	 *               	["user_contact_name"], // user.contact_name
	 *               	["user_contact_tel"], // user.contact_tel
	 *               	["user_title"], // user.title
	 *               	["user_company"], // user.company
	 *               	["user_zip"], // user.zip
	 *               	["user_address"], // user.address
	 *               	["user_remark"], // user.remark
	 *               	["user_tag"], // user.tag
	 *               	["user_user_verified"], // user.user_verified
	 *               	["user_name_verified"], // user.name_verified
	 *               	["user_verify"], // user.verify
	 *               	["user_verify_data"], // user.verify_data
	 *               	["user_mobile_verified"], // user.mobile_verified
	 *               	["user_email_verified"], // user.email_verified
	 *               	["user_extra"], // user.extra
	 *               	["user_password"], // user.password
	 *               	["user_pay_password"], // user.pay_password
	 *               	["user_status"], // user.status
	 *               	["user_inviter"], // user.inviter
	 *               	["user_follower_cnt"], // user.follower_cnt
	 *               	["user_following_cnt"], // user.following_cnt
	 *               	["user_name_message"], // user.name_message
	 *               	["user_verify_message"], // user.verify_message
	 *               	["user_client_token"], // user.client_token
	 *               	["user_user_name"], // user.user_name
	 *               	["question_created_at"], // question.created_at
	 *               	["question_updated_at"], // question.updated_at
	 *               	["question_user_id"], // question.user_id
	 *               	["question_title"], // question.title
	 *               	["question_summary"], // question.summary
	 *               	["question_content"], // question.content
	 *               	["question_category_ids"], // question.category_ids
	 *               	["question_series_ids"], // question.series_ids
	 *               	["question_tags"], // question.tags
	 *               	["question_view_cnt"], // question.view_cnt
	 *               	["question_agree_cnt"], // question.agree_cnt
	 *               	["question_answer_cnt"], // question.answer_cnt
	 *               	["question_priority"], // question.priority
	 *               	["question_status"], // question.status
	 *               	["question_publish_time"], // question.publish_time
	 *               	["question_coin"], // question.coin
	 *               	["question_money"], // question.money
	 *               	["question_coin_view"], // question.coin_view
	 *               	["question_money_view"], // question.money_view
	 *               	["question_policies"], // question.policies
	 *               	["question_policies_detail"], // question.policies_detail
	 *               	["question_anonymous"], // question.anonymous
	 *               	["question_cover"], // question.cover
	 *               	["question_history"], // question.history
	 */
	public function search( $query = [] ) {

		$select = empty($query['select']) ? ["answer.answer_id","question.title","user.name","user.nickname","answer.view_cnt","answer.agree_cnt","answer.policies","answer.accepted","answer.status","answer.created_at","answer.updated_at"] : $query['select'];
		if ( is_string($select) ) {
			$select = explode(',', $select);
		}

		// 增加表单查询索引字段
		array_push($select, "answer.answer_id");
		$inwhereSelect = $this->formatSelect( $select ); // 过滤 inWhere 查询字段

		// 创建查询构造器
		$qb = Utils::getTab("xpmsns_qanda_answer as answer", "{none}")->query();
 		$qb->leftJoin("xpmsns_user_user as user", "user.user_id", "=", "answer.user_id"); // 连接用户
 		$qb->leftJoin("xpmsns_qanda_question as question", "question.question_id", "=", "answer.question_id"); // 连接提问

		// 按关键词查找
		if ( array_key_exists("keyword", $query) && !empty($query["keyword"]) ) {
			$qb->where(function ( $qb ) use($query) {
				$qb->where("answer.answer_id", "like", "%{$query['keyword']}%");
				$qb->orWhere("answer.question_id","like", "%{$query['keyword']}%");
				$qb->orWhere("answer.summary","like", "%{$query['keyword']}%");
				$qb->orWhere("user.name","like", "%{$query['keyword']}%");
				$qb->orWhere("user.nickname","like", "%{$query['keyword']}%");
				$qb->orWhere("question.title","like", "%{$query['keyword']}%");
			});
		}


		// 按回答ID查询 (=)  
		if ( array_key_exists("answer_id", $query) &&!empty($query['answer_id']) ) {
			$qb->where("answer.answer_id", '=', "{$query['answer_id']}" );
		}
		  
		// 按提问ID查询 (=)  
		if ( array_key_exists("question_id", $query) &&!empty($query['question_id']) ) {
			$qb->where("answer.question_id", '=', "{$query['question_id']}" );
		}
		  
		// 按用户ID查询 (=)  
		if ( array_key_exists("user_id", $query) &&!empty($query['user_id']) ) {
			$qb->where("answer.user_id", '=', "{$query['user_id']}" );
		}
		  
		// 按发布时间大于查询 (>=)  
		if ( array_key_exists("after", $query) &&!empty($query['after']) ) {
			$qb->where("answer.publish_time", '>=', "{$query['after']}" );
		}
		  
		// 按发布时间小于查询 (<=)  
		if ( array_key_exists("before", $query) &&!empty($query['before']) ) {
			$qb->where("answer.publish_time", '<=', "{$query['before']}" );
		}
		  
		// 按获得积分查询 (>)  
		if ( array_key_exists("coin", $query) &&!empty($query['coin']) ) {
			$qb->where("answer.coin", '>', "{$query['coin']}" );
		}
		  
		// 按获得金额查询 (>)  
		if ( array_key_exists("money", $query) &&!empty($query['money']) ) {
			$qb->where("answer.money", '>', "{$query['money']}" );
		}
		  
		// 按围观积分查询 (>)  
		if ( array_key_exists("coin_view", $query) &&!empty($query['coin_view']) ) {
			$qb->where("answer.coin_view", '>', "{$query['coin_view']}" );
		}
		  
		// 按围观金额查询 (>)  
		if ( array_key_exists("money_view", $query) &&!empty($query['money_view']) ) {
			$qb->where("answer.money_view", '>', "{$query['money_view']}" );
		}
		  
		// 按访问策略查询 (=)  
		if ( array_key_exists("policies", $query) &&!empty($query['policies']) ) {
			$qb->where("answer.policies", '=', "{$query['policies']}" );
		}
		  
		// 按是否采纳查询 (=)  
		if ( array_key_exists("accepted", $query) &&!empty($query['accepted']) ) {
			$qb->where("answer.accepted", '=', "{$query['accepted']}" );
		}
		  
		// 按不包含查询 (NOT-IN)  
		if ( array_key_exists("exclude", $query) &&!empty($query['exclude']) ) {
			if ( is_string($query['exclude']) ) {
				$query['exclude'] = explode(',', $query['exclude']);
			}
			$qb->whereNotIn("answer.answer_id",  $query['exclude'] );
		}
		  
		// 按状态查询 (=)  
		if ( array_key_exists("status", $query) &&!empty($query['status']) ) {
			$qb->where("answer.status", '=', "{$query['status']}" );
		}
		  

		// 按浏览数量倒序 DESC 排序
		if ( array_key_exists("view_desc", $query) &&!empty($query['view_desc']) ) {
			$qb->orderBy("answer.view_cnt", "desc");
		}

		// 按赞同数量倒序 DESC 排序
		if ( array_key_exists("agree_desc", $query) &&!empty($query['agree_desc']) ) {
			$qb->orderBy("answer.agree_cnt", "desc");
		}

		// 按发布时间倒序 DESC 排序
		if ( array_key_exists("publish_desc", $query) &&!empty($query['publish_desc']) ) {
			$qb->orderBy("answer.publish_time", "desc");
		}

		// 按发布时间正序 ASC 排序
		if ( array_key_exists("publish_asc", $query) &&!empty($query['publish_asc']) ) {
			$qb->orderBy("answer.publish_time", "asc");
		}

		// 按优先级正序 ASC 排序
		if ( array_key_exists("priority_asc", $query) &&!empty($query['priority_asc']) ) {
			$qb->orderBy("answer.priority", "asc");
		}


		// 页码
		$page = array_key_exists('page', $query) ?  intval( $query['page']) : 1;
		$perpage = array_key_exists('perpage', $query) ?  intval( $query['perpage']) : 20;

		// 读取数据并分页
		$answers = $qb->select( $select )->pgArray($perpage, ['answer._id'], 'page', $page);

  		foreach ($answers['data'] as & $rs ) {
			$this->format($rs);
			
  		}

  	
		// for Debug
		if ($_GET['debug'] == 1) { 
			$answers['_sql'] = $qb->getSql();
			$answers['query'] = $query;
		}

		return $answers;
	}

	/**
	 * 格式化读取字段
	 * @param  array $select 选中字段
	 * @return array $inWhere 读取字段
	 */
	public function formatSelect( & $select ) {
		// 过滤 inWhere 查询字段
		$inwhereSelect = []; $linkSelect = [];
		foreach ($select as $idx=>$fd ) {
			
			// 添加本表前缀
			if ( !strpos( $fd, ".")  ) {
				$select[$idx] = "answer." .$select[$idx];
				continue;
			}
			
			//  连接用户 (user as user )
			if ( trim($fd) == "user.*" || trim($fd) == "user.*"  || trim($fd) == "*" ) {
				$fields = [];
				if ( method_exists("\\Xpmsns\\User\\Model\\User", 'getFields') ) {
					$fields = \Xpmsns\User\Model\User::getFields();
				}

				if ( !empty($fields) ) { 
					foreach ($fields as $field ) {
						$field = "user.{$field} as user_{$field}";
						array_push($linkSelect, $field);
					}

					if ( trim($fd) === "*" ) {
						array_push($linkSelect, "answer.*");
					}
					unset($select[$idx]);	
				}
			}

			else if ( strpos( $fd, "user." ) === 0 ) {
				$as = str_replace('user.', 'user_', $select[$idx]);
				$select[$idx] = $select[$idx] . " as {$as} ";
			}

			else if ( strpos( $fd, "user.") === 0 ) {
				$as = str_replace('user.', 'user_', $select[$idx]);
				$select[$idx] = $select[$idx] . " as {$as} ";
			}

			
			//  连接提问 (question as question )
			if ( trim($fd) == "question.*" || trim($fd) == "question.*"  || trim($fd) == "*" ) {
				$fields = [];
				if ( method_exists("\\Xpmsns\\Qanda\\Model\\Question", 'getFields') ) {
					$fields = \Xpmsns\Qanda\Model\Question::getFields();
				}

				if ( !empty($fields) ) { 
					foreach ($fields as $field ) {
						$field = "question.{$field} as question_{$field}";
						array_push($linkSelect, $field);
					}

					if ( trim($fd) === "*" ) {
						array_push($linkSelect, "answer.*");
					}
					unset($select[$idx]);	
				}
			}

			else if ( strpos( $fd, "question." ) === 0 ) {
				$as = str_replace('question.', 'question_', $select[$idx]);
				$select[$idx] = $select[$idx] . " as {$as} ";
			}

			else if ( strpos( $fd, "question.") === 0 ) {
				$as = str_replace('question.', 'question_', $select[$idx]);
				$select[$idx] = $select[$idx] . " as {$as} ";
			}

		}

		// filter 查询字段
		foreach ($inwhereSelect as & $iws ) {
			if ( is_array($iws) ) {
				$iws = array_unique(array_filter($iws));
			}
		}

		$select = array_unique(array_merge($linkSelect, $select));
		return $inwhereSelect;
	}

	/**
	 * 返回所有字段
	 * @return array 字段清单
	 */
	public static function getFields() {
		return [
			"answer_id",  // 回答ID
			"question_id",  // 提问ID
			"user_id",  // 用户ID
			"summary",  // 摘要
			"content",  // 正文
			"publish_time",  // 发布时间
			"policies",  // 访问策略
			"policies_detail",  // 访问策略明细
			"priority",  // 优先级
			"view_cnt",  // 浏览量
			"agree_cnt",  // 赞同量
			"coin",  // 获得积分
			"money",  // 获得金额
			"coin_view",  // 围观积分
			"money_view",  // 围观金额
			"anonymous",  // 是否匿名
			"accepted",  // 是否采纳
			"status",  // 状态
			"history",  // 修改历史
			"created_at",  // 创建时间
			"updated_at",  // 更新时间
		];
	}

}

?>