<?php
/**
 * Class Question 
 * 提问数据模型
 *
 * 程序作者: XpmSE机器人
 * 最后修改: 2019-01-27 19:59:33
 * 程序母版: /data/stor/private/templates/xpmsns/model/code/model/Name.php
 */
namespace Xpmsns\Qanda\Model;
                            
use \Xpmse\Excp;
use \Xpmse\Model;
use \Xpmse\Utils;
use \Xpmse\Conf;
use \Xpmse\Media;
use \Mina\Cache\Redis as Cache;
use \Xpmse\Loader\App as App;
use \Xpmse\Job;


class Question extends Model {


	/**
	 * 公有媒体文件对象
	 * @var \Xpmse\Meida
	 */
	protected $media = null;

	/**
	 * 私有媒体文件对象
	 * @var \Xpmse\Meida
	 */
	protected $mediaPrivate = null;

    /**
     * 数据缓存对象
     */
    protected $cache = null;

	/**
	 * 提问数据模型【3】
	 * @param array $param 配置参数
	 *              $param['prefix']  数据表前缀，默认为 xpmsns_qanda_
	 */
	function __construct( $param=[] ) {

		parent::__construct(array_merge(['prefix'=>'xpmsns_qanda_'],$param));
        $this->table('question'); // 数据表名称 xpmsns_qanda_question
         // + Redis缓存
        $this->cache = new Cache([
            "prefix" => "xpmsns_qanda_question:",
            "host" => Conf::G("mem/redis/host"),
            "port" => Conf::G("mem/redis/port"),
            "passwd"=> Conf::G("mem/redis/password")
        ]);

		$this->media = new Media(['host'=>Utils::getHome()]);  // 公有媒体文件实例

       
	}

	/**
	 * 自定义函数 
	 */


	/**
	 * 创建数据表
	 * @return $this
	 */
	public function __schema() {

		// 问题ID
		$this->putColumn( 'question_id', $this->type("string", ["length"=>128, "unique"=>true, "null"=>true]));
		// 用户ID
		$this->putColumn( 'user_id', $this->type("string", ["length"=>128, "index"=>true, "null"=>true]));
		// 标题
		$this->putColumn( 'title', $this->type("string", ["length"=>200, "index"=>true, "null"=>true]));
		// 摘要
		$this->putColumn( 'summary', $this->type("string", ["length"=>600, "index"=>true, "null"=>true]));
		// 封面
		$this->putColumn( 'cover', $this->type("string", ["length"=>600, "json"=>true, "null"=>true]));
		//  正文
		$this->putColumn( 'content', $this->type("longText", ["null"=>true]));
		// 类目
		$this->putColumn( 'category_ids', $this->type("string", ["length"=>400, "index"=>true, "json"=>true, "null"=>true]));
		// 系列
		$this->putColumn( 'series_ids', $this->type("string", ["length"=>400, "index"=>true, "json"=>true, "null"=>true]));
		// 标签
		$this->putColumn( 'tags', $this->type("string", ["length"=>400, "index"=>true, "json"=>true, "null"=>true]));
		// 发布时间
		$this->putColumn( 'publish_time', $this->type("timestamp", ["index"=>true, "null"=>true]));
		// 悬赏积分
		$this->putColumn( 'coin', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 悬赏金额
		$this->putColumn( 'money', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 围观积分
		$this->putColumn( 'coin_view', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 围观金额
		$this->putColumn( 'money_view', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 访问策略
		$this->putColumn( 'policies', $this->type("string", ["length"=>32, "index"=>true, "default"=>"public", "null"=>true]));
		// 访问策略详情
		$this->putColumn( 'policies_detail', $this->type("string", ["length"=>600, "json"=>true, "null"=>true]));
		// 是否匿名
		$this->putColumn( 'anonymous', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 浏览量
		$this->putColumn( 'view_cnt', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 赞同量
		$this->putColumn( 'agree_cnt', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 答案量
		$this->putColumn( 'answer_cnt', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 优先级
		$this->putColumn( 'priority', $this->type("integer", ["length"=>1, "index"=>true, "null"=>true]));
		// 状态
		$this->putColumn( 'status', $this->type("string", ["length"=>32, "index"=>true, "null"=>true]));
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
		// 格式化: 封面
		// 返回值: [{"url":"访问地址...", "path":"文件路径...", "origin":"原始文件访问地址..." }]
		if ( array_key_exists('cover', $rs ) ) {
            array_push($fileFields, 'cover');
		}

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
		  			"style" => "muted"
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
		  			"name" => "公开的",
		  			"style" => "success"
		  		],
		  		"partially" => [
		  			"value" => "partially",
		  			"name" => "部分可见",
		  			"style" => "warning"
		  		],
		  		"private" => [
		  			"value" => "private",
		  			"name" => "私密的",
		  			"style" => "danger"
		  		],
			];
			$rs["_policies_name"] = "policies";
			$rs["_policies"] = $rs["_policies_types"][$rs["policies"]];
		}

 
		// <在这里添加更多数据格式化逻辑>
		
		return $rs;
	}

	
	/**
	 * 按问题ID查询一条提问记录
	 * @param string $question_id 唯一主键
	 * @return array $rs 结果集 
	 *          	  $rs["question_id"],  // 问题ID 
	 *          	  $rs["user_id"],  // 用户ID 
	 *                $rs["user_user_id"], // user.user_id
	 *          	  $rs["title"],  // 标题 
	 *          	  $rs["summary"],  // 摘要 
	 *          	  $rs["cover"],  // 封面 
	 *          	  $rs["content"],  //  正文 
	 *          	  $rs["category_ids"],  // 类目 
	 *                $rs["_map_category"][$category_ids[n]]["category_id"], // category.category_id
	 *          	  $rs["series_ids"],  // 系列 
	 *                $rs["_map_series"][$series_ids[n]]["series_id"], // series.series_id
	 *          	  $rs["tags"],  // 标签 
	 *                $rs["_map_tag"][$tags[n]]["name"], // tag.name
	 *          	  $rs["publish_time"],  // 发布时间 
	 *          	  $rs["coin"],  // 悬赏积分 
	 *          	  $rs["money"],  // 悬赏金额 
	 *          	  $rs["coin_view"],  // 围观积分 
	 *          	  $rs["money_view"],  // 围观金额 
	 *          	  $rs["policies"],  // 访问策略 
	 *          	  $rs["policies_detail"],  // 访问策略详情 
	 *          	  $rs["anonymous"],  // 是否匿名 
	 *          	  $rs["view_cnt"],  // 浏览量 
	 *          	  $rs["agree_cnt"],  // 赞同量 
	 *          	  $rs["answer_cnt"],  // 答案量 
	 *          	  $rs["priority"],  // 优先级 
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
	 *                $rs["_map_category"][$category_ids[n]]["created_at"], // category.created_at
	 *                $rs["_map_category"][$category_ids[n]]["updated_at"], // category.updated_at
	 *                $rs["_map_category"][$category_ids[n]]["slug"], // category.slug
	 *                $rs["_map_category"][$category_ids[n]]["project"], // category.project
	 *                $rs["_map_category"][$category_ids[n]]["page"], // category.page
	 *                $rs["_map_category"][$category_ids[n]]["wechat"], // category.wechat
	 *                $rs["_map_category"][$category_ids[n]]["wechat_offset"], // category.wechat_offset
	 *                $rs["_map_category"][$category_ids[n]]["name"], // category.name
	 *                $rs["_map_category"][$category_ids[n]]["fullname"], // category.fullname
	 *                $rs["_map_category"][$category_ids[n]]["link"], // category.link
	 *                $rs["_map_category"][$category_ids[n]]["root_id"], // category.root_id
	 *                $rs["_map_category"][$category_ids[n]]["parent_id"], // category.parent_id
	 *                $rs["_map_category"][$category_ids[n]]["priority"], // category.priority
	 *                $rs["_map_category"][$category_ids[n]]["hidden"], // category.hidden
	 *                $rs["_map_category"][$category_ids[n]]["isnav"], // category.isnav
	 *                $rs["_map_category"][$category_ids[n]]["param"], // category.param
	 *                $rs["_map_category"][$category_ids[n]]["status"], // category.status
	 *                $rs["_map_category"][$category_ids[n]]["issubnav"], // category.issubnav
	 *                $rs["_map_category"][$category_ids[n]]["highlight"], // category.highlight
	 *                $rs["_map_category"][$category_ids[n]]["isfootnav"], // category.isfootnav
	 *                $rs["_map_category"][$category_ids[n]]["isblank"], // category.isblank
	 *                $rs["_map_series"][$series_ids[n]]["created_at"], // series.created_at
	 *                $rs["_map_series"][$series_ids[n]]["updated_at"], // series.updated_at
	 *                $rs["_map_series"][$series_ids[n]]["name"], // series.name
	 *                $rs["_map_series"][$series_ids[n]]["slug"], // series.slug
	 *                $rs["_map_series"][$series_ids[n]]["category_id"], // series.category_id
	 *                $rs["_map_series"][$series_ids[n]]["summary"], // series.summary
	 *                $rs["_map_series"][$series_ids[n]]["orderby"], // series.orderby
	 *                $rs["_map_series"][$series_ids[n]]["param"], // series.param
	 *                $rs["_map_series"][$series_ids[n]]["status"], // series.status
	 *                $rs["_map_tag"][$tags[n]]["created_at"], // tag.created_at
	 *                $rs["_map_tag"][$tags[n]]["updated_at"], // tag.updated_at
	 *                $rs["_map_tag"][$tags[n]]["tag_id"], // tag.tag_id
	 *                $rs["_map_tag"][$tags[n]]["param"], // tag.param
	 *                $rs["_map_tag"][$tags[n]]["article_cnt"], // tag.article_cnt
	 *                $rs["_map_tag"][$tags[n]]["album_cnt"], // tag.album_cnt
	 *                $rs["_map_tag"][$tags[n]]["event_cnt"], // tag.event_cnt
	 *                $rs["_map_tag"][$tags[n]]["goods_cnt"], // tag.goods_cnt
	 *                $rs["_map_tag"][$tags[n]]["question_cnt"], // tag.question_cnt
	 */
	public function getByQuestionId( $question_id, $select=['*']) {
		
		if ( is_string($select) ) {
			$select = explode(',', $select);
		}


		// 增加表单查询索引字段
		array_push($select, "question.question_id");
		$inwhereSelect = $this->formatSelect( $select ); // 过滤 inWhere 查询字段

		// 创建查询构造器
		$qb = Utils::getTab("xpmsns_qanda_question as question", "{none}")->query();
 		$qb->leftJoin("xpmsns_user_user as user", "user.user_id", "=", "question.user_id"); // 连接用户
   		$qb->where('question.question_id', '=', $question_id );
		$qb->limit( 1 );
		$qb->select($select);
		$rows = $qb->get()->toArray();
		if( empty($rows) ) {
			return [];
		}

		$rs = current( $rows );
		$this->format($rs);

  		$category_ids = []; // 读取 inWhere category 数据
		$category_ids = array_merge($category_ids, is_array($rs["category_ids"]) ? $rs["category_ids"] : [$rs["category_ids"]]);
 		$series_ids = []; // 读取 inWhere series 数据
		$series_ids = array_merge($series_ids, is_array($rs["series_ids"]) ? $rs["series_ids"] : [$rs["series_ids"]]);
 		$names = []; // 读取 inWhere tag 数据
		$names = array_merge($names, is_array($rs["tags"]) ? $rs["tags"] : [$rs["tags"]]);

  		// 读取 inWhere category 数据
		if ( !empty($inwhereSelect["category"]) && method_exists("\\Xpmsns\\Pages\\Model\\Category", 'getInByCategoryId') ) {
			$category_ids = array_unique($category_ids);
			$selectFields = $inwhereSelect["category"];
			$rs["_map_category"] = (new \Xpmsns\Pages\Model\Category)->getInByCategoryId($category_ids, $selectFields);
		}
 		// 读取 inWhere series 数据
		if ( !empty($inwhereSelect["series"]) && method_exists("\\Xpmsns\\Pages\\Model\\Series", 'getInBySeriesId') ) {
			$series_ids = array_unique($series_ids);
			$selectFields = $inwhereSelect["series"];
			$rs["_map_series"] = (new \Xpmsns\Pages\Model\Series)->getInBySeriesId($series_ids, $selectFields);
		}
 		// 读取 inWhere tag 数据
		if ( !empty($inwhereSelect["tag"]) && method_exists("\\Xpmsns\\Pages\\Model\\Tag", 'getInByName') ) {
			$names = array_unique($names);
			$selectFields = $inwhereSelect["tag"];
			$rs["_map_tag"] = (new \Xpmsns\Pages\Model\Tag)->getInByName($names, $selectFields);
		}

		return $rs;
	}

		

	/**
	 * 按问题ID查询一组提问记录
	 * @param array   $question_ids 唯一主键数组 ["$question_id1","$question_id2" ...]
	 * @param array   $order        排序方式 ["field"=>"asc", "field2"=>"desc"...]
	 * @param array   $select       选取字段，默认选取所有
	 * @return array 提问记录MAP {"question_id1":{"key":"value",...}...}
	 */
	public function getInByQuestionId($question_ids, $select=["question.question_id","question.title","user.name","user.nickname","category.name","tag.name","question.policies","question.status","question.created_at","question.updated_at"], $order=["question.publish_time"=>"desc"] ) {
		
		if ( is_string($select) ) {
			$select = explode(',', $select);
		}

		// 增加表单查询索引字段
		array_push($select, "question.question_id");
		$inwhereSelect = $this->formatSelect( $select ); // 过滤 inWhere 查询字段

		// 创建查询构造器
		$qb = Utils::getTab("xpmsns_qanda_question as question", "{none}")->query();
 		$qb->leftJoin("xpmsns_user_user as user", "user.user_id", "=", "question.user_id"); // 连接用户
   		$qb->whereIn('question.question_id', $question_ids);
		
		// 排序
		foreach ($order as $field => $order ) {
			$qb->orderBy( $field, $order );
		}
		$qb->select( $select );
		$data = $qb->get()->toArray(); 

		$map = [];

  		$category_ids = []; // 读取 inWhere category 数据
 		$series_ids = []; // 读取 inWhere series 数据
 		$names = []; // 读取 inWhere tag 数据
		foreach ($data as & $rs ) {
			$this->format($rs);
			$map[$rs['question_id']] = $rs;
			
  			// for inWhere category
			$category_ids = array_merge($category_ids, is_array($rs["category_ids"]) ? $rs["category_ids"] : [$rs["category_ids"]]);
 			// for inWhere series
			$series_ids = array_merge($series_ids, is_array($rs["series_ids"]) ? $rs["series_ids"] : [$rs["series_ids"]]);
 			// for inWhere tag
			$names = array_merge($names, is_array($rs["tags"]) ? $rs["tags"] : [$rs["tags"]]);
		}

  		// 读取 inWhere category 数据
		if ( !empty($inwhereSelect["category"]) && method_exists("\\Xpmsns\\Pages\\Model\\Category", 'getInByCategoryId') ) {
			$category_ids = array_unique($category_ids);
			$selectFields = $inwhereSelect["category"];
			$map["_map_category"] = (new \Xpmsns\Pages\Model\Category)->getInByCategoryId($category_ids, $selectFields);
		}
 		// 读取 inWhere series 数据
		if ( !empty($inwhereSelect["series"]) && method_exists("\\Xpmsns\\Pages\\Model\\Series", 'getInBySeriesId') ) {
			$series_ids = array_unique($series_ids);
			$selectFields = $inwhereSelect["series"];
			$map["_map_series"] = (new \Xpmsns\Pages\Model\Series)->getInBySeriesId($series_ids, $selectFields);
		}
 		// 读取 inWhere tag 数据
		if ( !empty($inwhereSelect["tag"]) && method_exists("\\Xpmsns\\Pages\\Model\\Tag", 'getInByName') ) {
			$names = array_unique($names);
			$selectFields = $inwhereSelect["tag"];
			$map["_map_tag"] = (new \Xpmsns\Pages\Model\Tag)->getInByName($names, $selectFields);
		}


		return $map;
	}


	/**
	 * 按问题ID保存提问记录。(记录不存在则创建，存在则更新)
	 * @param array $data 记录数组 (key:value 结构)
	 * @param array $select 返回的字段，默认返回全部
	 * @return array 数据记录数组
	 */
	public function saveByQuestionId( $data, $select=["*"] ) {

		if ( is_string($select) ) {
			$select = explode(',', $select);
		}

		// 增加表单查询索引字段
		array_push($select, "question.question_id");
		$inwhereSelect = $this->formatSelect( $select ); // 过滤 inWhere 查询字段
		$rs = $this->saveBy("question_id", $data, ["question_id"], ['_id', 'question_id']);
		return $this->getByQuestionId( $rs['question_id'], $select );
	}

	/**
	 * 根据问题ID上传封面。
	 * @param string $question_id 问题ID
	 * @param string $file_path 文件路径
	 * @param mix $index 如果是数组，替换当前 index
	 * @return array 已上传文件信息 {"url":"访问地址...", "path":"文件路径...", "origin":"原始文件访问地址..." }
	 */
	public function uploadCoverByQuestionId($question_id, $file_path, $index=null, $upload_only=false ) {

		$rs = $this->getBy('question_id', $question_id, ["cover"]);
		$paths = empty($rs["cover"]) ? [] : $rs["cover"];
		$fs = $this->media->uploadFile( $file_path );
		if ( $index === null ) {
			array_push($paths, $fs['path']);
		} else {
			$paths[$index] = $fs['path'];
		}

		if ( $upload_only !== true ) {
			$this->updateBy('question_id', ["question_id"=>$question_id, "cover"=>$paths] );
		}

		return $fs;
	}


	/**
	 * 添加提问记录
	 * @param  array $data 记录数组  (key:value 结构)
	 * @return array 数据记录数组 (key:value 结构)
	 */
	function create( $data ) {
		if ( empty($data["question_id"]) ) { 
			$data["question_id"] = $this->genId();
		}
		return parent::create( $data );
	}


	/**
	 * 查询前排提问记录
	 * @param integer $limit 返回记录数，默认100
	 * @param array   $select  选取字段，默认选取所有
	 * @param array   $order   排序方式 ["field"=>"asc", "field2"=>"desc"...]
	 * @return array 提问记录数组 [{"key":"value",...}...]
	 */
	public function top( $limit=100, $select=["question.question_id","question.title","user.name","user.nickname","category.name","tag.name","question.policies","question.status","question.created_at","question.updated_at"], $order=["question.publish_time"=>"desc"] ) {

		if ( is_string($select) ) {
			$select = explode(',', $select);
		}

		// 增加表单查询索引字段
		array_push($select, "question.question_id");
		$inwhereSelect = $this->formatSelect( $select ); // 过滤 inWhere 查询字段

		// 创建查询构造器
		$qb = Utils::getTab("xpmsns_qanda_question as question", "{none}")->query();
 		$qb->leftJoin("xpmsns_user_user as user", "user.user_id", "=", "question.user_id"); // 连接用户
   

		foreach ($order as $field => $order ) {
			$qb->orderBy( $field, $order );
		}
		$qb->limit($limit);
		$qb->select( $select );
		$data = $qb->get()->toArray();


  		$category_ids = []; // 读取 inWhere category 数据
 		$series_ids = []; // 读取 inWhere series 数据
 		$names = []; // 读取 inWhere tag 数据
		foreach ($data as & $rs ) {
			$this->format($rs);
			
  			// for inWhere category
			$category_ids = array_merge($category_ids, is_array($rs["category_ids"]) ? $rs["category_ids"] : [$rs["category_ids"]]);
 			// for inWhere series
			$series_ids = array_merge($series_ids, is_array($rs["series_ids"]) ? $rs["series_ids"] : [$rs["series_ids"]]);
 			// for inWhere tag
			$names = array_merge($names, is_array($rs["tags"]) ? $rs["tags"] : [$rs["tags"]]);
		}

  		// 读取 inWhere category 数据
		if ( !empty($inwhereSelect["category"]) && method_exists("\\Xpmsns\\Pages\\Model\\Category", 'getInByCategoryId') ) {
			$category_ids = array_unique($category_ids);
			$selectFields = $inwhereSelect["category"];
			$data["_map_category"] = (new \Xpmsns\Pages\Model\Category)->getInByCategoryId($category_ids, $selectFields);
		}
 		// 读取 inWhere series 数据
		if ( !empty($inwhereSelect["series"]) && method_exists("\\Xpmsns\\Pages\\Model\\Series", 'getInBySeriesId') ) {
			$series_ids = array_unique($series_ids);
			$selectFields = $inwhereSelect["series"];
			$data["_map_series"] = (new \Xpmsns\Pages\Model\Series)->getInBySeriesId($series_ids, $selectFields);
		}
 		// 读取 inWhere tag 数据
		if ( !empty($inwhereSelect["tag"]) && method_exists("\\Xpmsns\\Pages\\Model\\Tag", 'getInByName') ) {
			$names = array_unique($names);
			$selectFields = $inwhereSelect["tag"];
			$data["_map_tag"] = (new \Xpmsns\Pages\Model\Tag)->getInByName($names, $selectFields);
		}

		return $data;
	
	}


	/**
	 * 按条件检索提问记录
	 * @param  array  $query
	 *         	      $query['select'] 选取字段，默认选择 ["question.question_id","question.title","user.name","user.nickname","category.name","tag.name","question.policies","question.status","question.created_at","question.updated_at"]
	 *         	      $query['page'] 页码，默认为 1
	 *         	      $query['perpage'] 每页显示记录数，默认为 20
	 *			      $query["keyword"] 按关键词查询
	 *			      $query["question_ids"] 按问题IDS查询 ( IN )
	 *			      $query["user_id"] 按用户ID查询 ( = )
	 *			      $query["category_ids"] 按类目查询 ( LIKE-MULTIPLE )
	 *			      $query["series_ids"] 按系列查询 ( LIKE-MULTIPLE )
	 *			      $query["tags"] 按标签查询 ( LIKE-MULTIPLE )
	 *			      $query["status"] 按状态查询 ( = )
	 *			      $query["status_not"] 按状态不等于查询 ( <> )
	 *			      $query["policies_not"] 按访问策略不等于查询 ( <> )
	 *			      $query["policies"] 按访问策略查询 ( = )
	 *			      $query["coin"] 按悬赏积分查询 ( > )
	 *			      $query["money"] 按悬赏金额查询 ( > )
	 *			      $query["coin_view"] 按围观积分查询 ( > )
	 *			      $query["money_view"] 按围观金额查询 ( > )
	 *			      $query["anonymous"] 按是否匿名查询 ( = )
	 *			      $query["before"] 按发布时间之前查询 ( <= )
	 *			      $query["after"] 按发布时间之后查询 ( >= )
	 *			      $query["publish_desc"]  按发布时间倒序 DESC 排序
	 *			      $query["created_desc"]  按创建时间倒序 DESC 排序
	 *			      $query["created_asc"]  按创建时间正序 ASC 排序
	 *			      $query["publish_asc"]  按发布时间正序 ASC 排序
	 *			      $query["answer_desc"]  按答案数量倒序 DESC 排序
	 *			      $query["agree_desc"]  按赞同数量倒序 DESC 排序
	 *			      $query["view_desc"]  按浏览数量倒序 DESC 排序
	 *			      $query["money_desc"]  按悬赏金额倒序 DESC 排序
	 *			      $query["coin_desc"]  按悬赏积分倒序 DESC 排序
	 *			      $query["priority_asc"]  按优先级正序 ASC 排序
	 *           
	 * @return array 提问记录集 {"total":100, "page":1, "perpage":20, data:[{"key":"val"}...], "from":1, "to":1, "prev":false, "next":1, "curr":10, "last":20}
	 *               	["question_id"],  // 问题ID 
	 *               	["user_id"],  // 用户ID 
	 *               	["user_user_id"], // user.user_id
	 *               	["title"],  // 标题 
	 *               	["summary"],  // 摘要 
	 *               	["cover"],  // 封面 
	 *               	["content"],  //  正文 
	 *               	["category_ids"],  // 类目 
	 *               	["category"][$category_ids[n]]["category_id"], // category.category_id
	 *               	["series_ids"],  // 系列 
	 *               	["series"][$series_ids[n]]["series_id"], // series.series_id
	 *               	["tags"],  // 标签 
	 *               	["tag"][$tags[n]]["name"], // tag.name
	 *               	["publish_time"],  // 发布时间 
	 *               	["coin"],  // 悬赏积分 
	 *               	["money"],  // 悬赏金额 
	 *               	["coin_view"],  // 围观积分 
	 *               	["money_view"],  // 围观金额 
	 *               	["policies"],  // 访问策略 
	 *               	["policies_detail"],  // 访问策略详情 
	 *               	["anonymous"],  // 是否匿名 
	 *               	["view_cnt"],  // 浏览量 
	 *               	["agree_cnt"],  // 赞同量 
	 *               	["answer_cnt"],  // 答案量 
	 *               	["priority"],  // 优先级 
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
	 *               	["category"][$category_ids[n]]["created_at"], // category.created_at
	 *               	["category"][$category_ids[n]]["updated_at"], // category.updated_at
	 *               	["category"][$category_ids[n]]["slug"], // category.slug
	 *               	["category"][$category_ids[n]]["project"], // category.project
	 *               	["category"][$category_ids[n]]["page"], // category.page
	 *               	["category"][$category_ids[n]]["wechat"], // category.wechat
	 *               	["category"][$category_ids[n]]["wechat_offset"], // category.wechat_offset
	 *               	["category"][$category_ids[n]]["name"], // category.name
	 *               	["category"][$category_ids[n]]["fullname"], // category.fullname
	 *               	["category"][$category_ids[n]]["link"], // category.link
	 *               	["category"][$category_ids[n]]["root_id"], // category.root_id
	 *               	["category"][$category_ids[n]]["parent_id"], // category.parent_id
	 *               	["category"][$category_ids[n]]["priority"], // category.priority
	 *               	["category"][$category_ids[n]]["hidden"], // category.hidden
	 *               	["category"][$category_ids[n]]["isnav"], // category.isnav
	 *               	["category"][$category_ids[n]]["param"], // category.param
	 *               	["category"][$category_ids[n]]["status"], // category.status
	 *               	["category"][$category_ids[n]]["issubnav"], // category.issubnav
	 *               	["category"][$category_ids[n]]["highlight"], // category.highlight
	 *               	["category"][$category_ids[n]]["isfootnav"], // category.isfootnav
	 *               	["category"][$category_ids[n]]["isblank"], // category.isblank
	 *               	["series"][$series_ids[n]]["created_at"], // series.created_at
	 *               	["series"][$series_ids[n]]["updated_at"], // series.updated_at
	 *               	["series"][$series_ids[n]]["name"], // series.name
	 *               	["series"][$series_ids[n]]["slug"], // series.slug
	 *               	["series"][$series_ids[n]]["category_id"], // series.category_id
	 *               	["series"][$series_ids[n]]["summary"], // series.summary
	 *               	["series"][$series_ids[n]]["orderby"], // series.orderby
	 *               	["series"][$series_ids[n]]["param"], // series.param
	 *               	["series"][$series_ids[n]]["status"], // series.status
	 *               	["tag"][$tags[n]]["created_at"], // tag.created_at
	 *               	["tag"][$tags[n]]["updated_at"], // tag.updated_at
	 *               	["tag"][$tags[n]]["tag_id"], // tag.tag_id
	 *               	["tag"][$tags[n]]["param"], // tag.param
	 *               	["tag"][$tags[n]]["article_cnt"], // tag.article_cnt
	 *               	["tag"][$tags[n]]["album_cnt"], // tag.album_cnt
	 *               	["tag"][$tags[n]]["event_cnt"], // tag.event_cnt
	 *               	["tag"][$tags[n]]["goods_cnt"], // tag.goods_cnt
	 *               	["tag"][$tags[n]]["question_cnt"], // tag.question_cnt
	 */
	public function search( $query = [] ) {

		$select = empty($query['select']) ? ["question.question_id","question.title","user.name","user.nickname","category.name","tag.name","question.policies","question.status","question.created_at","question.updated_at"] : $query['select'];
		if ( is_string($select) ) {
			$select = explode(',', $select);
		}

		// 增加表单查询索引字段
		array_push($select, "question.question_id");
		$inwhereSelect = $this->formatSelect( $select ); // 过滤 inWhere 查询字段

		// 创建查询构造器
		$qb = Utils::getTab("xpmsns_qanda_question as question", "{none}")->query();
 		$qb->leftJoin("xpmsns_user_user as user", "user.user_id", "=", "question.user_id"); // 连接用户
   
		// 按关键词查找
		if ( array_key_exists("keyword", $query) && !empty($query["keyword"]) ) {
			$qb->where(function ( $qb ) use($query) {
				$qb->where("question.question_id", "like", "%{$query['keyword']}%");
				$qb->orWhere("question.user_id","like", "%{$query['keyword']}%");
				$qb->orWhere("question.title","like", "%{$query['keyword']}%");
				$qb->orWhere("question.summary","like", "%{$query['keyword']}%");
				$qb->orWhere("question.tags","like", "%{$query['keyword']}%");
				$qb->orWhere("user.name","like", "%{$query['keyword']}%");
			});
		}


		// 按问题IDS查询 (IN)  
		if ( array_key_exists("question_ids", $query) &&!empty($query['question_ids']) ) {
			if ( is_string($query['question_ids']) ) {
				$query['question_ids'] = explode(',', $query['question_ids']);
			}
			$qb->whereIn("question.question_id",  $query['question_ids'] );
		}
		  
		// 按用户ID查询 (=)  
		if ( array_key_exists("user_id", $query) &&!empty($query['user_id']) ) {
			$qb->where("question.user_id", '=', "{$query['user_id']}" );
		}
		  
		// 按类目查询 (LIKE-MULTIPLE)  
		if ( array_key_exists("category_ids", $query) &&!empty($query['category_ids']) ) {
            $query['category_ids'] = explode(',', $query['category_ids']);
            $qb->where(function ( $qb ) use($query) {
                foreach( $query['category_ids'] as $idx=>$val )  {
                    $val = trim($val);
                    if ( $idx == 0 ) {
                        $qb->where("question.category_ids", 'like', "%{$val}%" );
                    } else {
                        $qb->orWhere("question.category_ids", 'like', "%{$val}%");
                    }
                }
            });
		}
		  
		// 按系列查询 (LIKE-MULTIPLE)  
		if ( array_key_exists("series_ids", $query) &&!empty($query['series_ids']) ) {
            $query['series_ids'] = explode(',', $query['series_ids']);
            $qb->where(function ( $qb ) use($query) {
                foreach( $query['series_ids'] as $idx=>$val )  {
                    $val = trim($val);
                    if ( $idx == 0 ) {
                        $qb->where("question.series_ids", 'like', "%{$val}%" );
                    } else {
                        $qb->orWhere("question.series_ids", 'like', "%{$val}%");
                    }
                }
            });
		}
		  
		// 按标签查询 (LIKE-MULTIPLE)  
		if ( array_key_exists("tags", $query) &&!empty($query['tags']) ) {
            $query['tags'] = explode(',', $query['tags']);
            $qb->where(function ( $qb ) use($query) {
                foreach( $query['tags'] as $idx=>$val )  {
                    $val = trim($val);
                    if ( $idx == 0 ) {
                        $qb->where("question.tags", 'like', "%{$val}%" );
                    } else {
                        $qb->orWhere("question.tags", 'like', "%{$val}%");
                    }
                }
            });
		}
		  
		// 按状态查询 (=)  
		if ( array_key_exists("status", $query) &&!empty($query['status']) ) {
			$qb->where("question.status", '=', "{$query['status']}" );
		}
		  
		// 按状态不等于查询 (<>)  
		if ( array_key_exists("status_not", $query) &&!empty($query['status_not']) ) {
			$qb->where("question.status", '<>', "{$query['status_not']}" );
		}
		  
		// 按访问策略不等于查询 (<>)  
		if ( array_key_exists("policies_not", $query) &&!empty($query['policies_not']) ) {
			$qb->where("question.policies", '<>', "{$query['policies_not']}" );
		}
		  
		// 按访问策略查询 (=)  
		if ( array_key_exists("policies", $query) &&!empty($query['policies']) ) {
			$qb->where("question.policies", '=', "{$query['policies']}" );
		}
		  
		// 按悬赏积分查询 (>)  
		if ( array_key_exists("coin", $query) &&!empty($query['coin']) ) {
			$qb->where("question.coin", '>', "{$query['coin']}" );
		}
		  
		// 按悬赏金额查询 (>)  
		if ( array_key_exists("money", $query) &&!empty($query['money']) ) {
			$qb->where("question.money", '>', "{$query['money']}" );
		}
		  
		// 按围观积分查询 (>)  
		if ( array_key_exists("coin_view", $query) &&!empty($query['coin_view']) ) {
			$qb->where("question.coin_view", '>', "{$query['coin_view']}" );
		}
		  
		// 按围观金额查询 (>)  
		if ( array_key_exists("money_view", $query) &&!empty($query['money_view']) ) {
			$qb->where("question.money_view", '>', "{$query['money_view']}" );
		}
		  
		// 按是否匿名查询 (=)  
		if ( array_key_exists("anonymous", $query) &&!empty($query['anonymous']) ) {
			$qb->where("question.anonymous", '=', "{$query['anonymous']}" );
		}
		  
		// 按发布时间之前查询 (<=)  
		if ( array_key_exists("before", $query) &&!empty($query['before']) ) {
			$qb->where("question.publish_time", '<=', "{$query['before']}" );
		}
		  
		// 按发布时间之后查询 (>=)  
		if ( array_key_exists("after", $query) &&!empty($query['after']) ) {
			$qb->where("question.publish_time", '>=', "{$query['after']}" );
		}
		  

		// 按发布时间倒序 DESC 排序
		if ( array_key_exists("publish_desc", $query) &&!empty($query['publish_desc']) ) {
			$qb->orderBy("question.publish_time", "desc");
		}

		// 按创建时间倒序 DESC 排序
		if ( array_key_exists("created_desc", $query) &&!empty($query['created_desc']) ) {
			$qb->orderBy("question.created_at", "desc");
		}

		// 按创建时间正序 ASC 排序
		if ( array_key_exists("created_asc", $query) &&!empty($query['created_asc']) ) {
			$qb->orderBy("question.created_at", "asc");
		}

		// 按发布时间正序 ASC 排序
		if ( array_key_exists("publish_asc", $query) &&!empty($query['publish_asc']) ) {
			$qb->orderBy("question.publish_time", "asc");
		}

		// 按答案数量倒序 DESC 排序
		if ( array_key_exists("answer_desc", $query) &&!empty($query['answer_desc']) ) {
			$qb->orderBy("question.answer_cnt", "desc");
		}

		// 按赞同数量倒序 DESC 排序
		if ( array_key_exists("agree_desc", $query) &&!empty($query['agree_desc']) ) {
			$qb->orderBy("question.agree_cnt", "desc");
		}

		// 按浏览数量倒序 DESC 排序
		if ( array_key_exists("view_desc", $query) &&!empty($query['view_desc']) ) {
			$qb->orderBy("question.view_cnt", "desc");
		}

		// 按悬赏金额倒序 DESC 排序
		if ( array_key_exists("money_desc", $query) &&!empty($query['money_desc']) ) {
			$qb->orderBy("question.money", "desc");
		}

		// 按悬赏积分倒序 DESC 排序
		if ( array_key_exists("coin_desc", $query) &&!empty($query['coin_desc']) ) {
			$qb->orderBy("question.coin", "desc");
		}

		// 按优先级正序 ASC 排序
		if ( array_key_exists("priority_asc", $query) &&!empty($query['priority_asc']) ) {
			$qb->orderBy("question.priority", "asc");
		}


		// 页码
		$page = array_key_exists('page', $query) ?  intval( $query['page']) : 1;
		$perpage = array_key_exists('perpage', $query) ?  intval( $query['perpage']) : 20;

		// 读取数据并分页
		$questions = $qb->select( $select )->pgArray($perpage, ['question._id'], 'page', $page);

  		$category_ids = []; // 读取 inWhere category 数据
 		$series_ids = []; // 读取 inWhere series 数据
 		$names = []; // 读取 inWhere tag 数据
		foreach ($questions['data'] as & $rs ) {
			$this->format($rs);
			
  			// for inWhere category
			$category_ids = array_merge($category_ids, is_array($rs["category_ids"]) ? $rs["category_ids"] : [$rs["category_ids"]]);
 			// for inWhere series
			$series_ids = array_merge($series_ids, is_array($rs["series_ids"]) ? $rs["series_ids"] : [$rs["series_ids"]]);
 			// for inWhere tag
			$names = array_merge($names, is_array($rs["tags"]) ? $rs["tags"] : [$rs["tags"]]);
		}

  		// 读取 inWhere category 数据
		if ( !empty($inwhereSelect["category"]) && method_exists("\\Xpmsns\\Pages\\Model\\Category", 'getInByCategoryId') ) {
			$category_ids = array_unique($category_ids);
			$selectFields = $inwhereSelect["category"];
			$questions["category"] = (new \Xpmsns\Pages\Model\Category)->getInByCategoryId($category_ids, $selectFields);
		}
 		// 读取 inWhere series 数据
		if ( !empty($inwhereSelect["series"]) && method_exists("\\Xpmsns\\Pages\\Model\\Series", 'getInBySeriesId') ) {
			$series_ids = array_unique($series_ids);
			$selectFields = $inwhereSelect["series"];
			$questions["series"] = (new \Xpmsns\Pages\Model\Series)->getInBySeriesId($series_ids, $selectFields);
		}
 		// 读取 inWhere tag 数据
		if ( !empty($inwhereSelect["tag"]) && method_exists("\\Xpmsns\\Pages\\Model\\Tag", 'getInByName') ) {
			$names = array_unique($names);
			$selectFields = $inwhereSelect["tag"];
			$questions["tag"] = (new \Xpmsns\Pages\Model\Tag)->getInByName($names, $selectFields);
		}
	
		// for Debug
		if ($_GET['debug'] == 1) { 
			$questions['_sql'] = $qb->getSql();
			$questions['query'] = $query;
		}

		return $questions;
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
				$select[$idx] = "question." .$select[$idx];
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
						array_push($linkSelect, "question.*");
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

			
			// 连接类目 (category as category )
			if ( strpos( $fd, "category." ) === 0 || strpos("category.", $fd ) === 0  || trim($fd) == "*" ) {
				$arr = explode( ".", $fd );
				$arr[1]  = !empty($arr[1]) ? $arr[1] : "*";
				$inwhereSelect["category"][] = trim($arr[1]);
				$inwhereSelect["category"][] = "category_id";
				if ( trim($fd) != "*" ) {
					unset($select[$idx]);
					array_push($linkSelect, "question.category_ids");
				}
			}
			
			// 连接系列 (series as series )
			if ( strpos( $fd, "series." ) === 0 || strpos("series.", $fd ) === 0  || trim($fd) == "*" ) {
				$arr = explode( ".", $fd );
				$arr[1]  = !empty($arr[1]) ? $arr[1] : "*";
				$inwhereSelect["series"][] = trim($arr[1]);
				$inwhereSelect["series"][] = "series_id";
				if ( trim($fd) != "*" ) {
					unset($select[$idx]);
					array_push($linkSelect, "question.series_ids");
				}
			}
			
			// 连接标签 (tag as tag )
			if ( strpos( $fd, "tag." ) === 0 || strpos("tag.", $fd ) === 0  || trim($fd) == "*" ) {
				$arr = explode( ".", $fd );
				$arr[1]  = !empty($arr[1]) ? $arr[1] : "*";
				$inwhereSelect["tag"][] = trim($arr[1]);
				$inwhereSelect["tag"][] = "name";
				if ( trim($fd) != "*" ) {
					unset($select[$idx]);
					array_push($linkSelect, "question.tags");
				}
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
			"question_id",  // 问题ID
			"user_id",  // 用户ID
			"title",  // 标题
			"summary",  // 摘要
			"cover",  // 封面
			"content",  //  正文
			"category_ids",  // 类目
			"series_ids",  // 系列
			"tags",  // 标签
			"publish_time",  // 发布时间
			"coin",  // 悬赏积分
			"money",  // 悬赏金额
			"coin_view",  // 围观积分
			"money_view",  // 围观金额
			"policies",  // 访问策略
			"policies_detail",  // 访问策略详情
			"anonymous",  // 是否匿名
			"view_cnt",  // 浏览量
			"agree_cnt",  // 赞同量
			"answer_cnt",  // 答案量
			"priority",  // 优先级
			"status",  // 状态
			"history",  // 修改历史
			"created_at",  // 创建时间
			"updated_at",  // 更新时间
		];
	}

}

?>