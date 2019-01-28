<?php
/**
 * Class Question 
 * 提问数据接口 
 *
 * 程序作者: XpmSE机器人
 * 最后修改: 2019-01-27 19:59:31
 * 程序母版: /data/stor/private/templates/xpmsns/model/code/api/Name.php
 */
namespace Xpmsns\Qanda\Api;
                            

use \Xpmse\Loader\App;
use \Xpmse\Excp;
use \Xpmse\Utils;
use \Xpmse\Api;

class Question extends Api {

	/**
	 * 提问数据接口
	 */
	function __construct() {
		parent::__construct();
	}

	/**
	 * 自定义函数 
	 */

    // @KEEP BEGIN

    /**
     * 读取提问
     */
    protected function get( $query, $data ) {

        // 支持POST和GET查询
        $data = array_merge( $query, $data );

        // 检查必填项目
        if ( empty($data["question_id"]) ) {
            throw new Excp("请提供问题id", 402, ["query"=>$query, "data"=>$data]);
        }

		// 读取字段
		$select = empty($data['select']) ? ["question.question_id","question.user_id","question.title","question.summary","question.cover", "question.content", "question.category_ids","question.series_ids","question.tags","question.publish_time","question.coin","question.money","question.coin_view","question.money_view","question.policies","question.policies_detail","question.anonymous","question.view_cnt","question.agree_cnt","question.answer_cnt","question.priority","question.status","question.created_at","question.updated_at","user.name","user.nickname","category.name","series.name"] : $data['select'];
		if ( is_string($select) ) {
			$select = explode(',', $select);
		}
		$data['select'] = $select;

		$inst = new \Xpmsns\Qanda\Model\Question;
        $question = $inst->getByQuestionId( $data["question_id"], $select );

        // 查询回答列表
        if ( $data["withanswer"] ) {
            $an = new \Xpmsns\Qanda\Model\Answer;
            $data["select"] = $data["answer_select"];

            // 查询置顶的回答
            if ( !empty($data["answer_id"]) ) {
                $answer = $an->getByAnswerId( $data["answer_id"],  $data["select"]);
                $question["answer"] = $answer;
                $data["exclude"] = [$data["answer_id"]];
                unset($data["answer_id"]);
            }
            $answers = $an->search( $data );
            $question["answers"] = $answers;
        }
        
        
        return $question;
        
    }

    /**
     * 发布提问
     */
    protected function create( $query, $data ) {

        // 检查必填项目
        if ( empty( $data["title"]) ) {
            throw new Excp("请填写问题标题", 402, ["query"=>$query, "data"=>$data]);
        }

        // 检查用户登录
        $user = \Xpmsns\User\Model\User::Info();
        $user_id = $user["user_id"];
        if ( empty($user_id) ) {
            throw new Excp("用户尚未登录", 402, ["query"=>$query, "data"=>$data]);
        }

        // 读取用户ID 
        $data["user_id"] = $user_id;

        // 许可字段清单
		$allowed =  [
			"user_id",  // 用户ID
			"title",  // 标题
			"summary",  // 摘要
			"cover",  // 封面
			"content",  //  正文
			"category_ids",  // 类目
			"series_ids",  // 系列
			"tags",  // 标签
			"publish_time",  // 发布时间
			"policies",  // 访问策略
			"policies_detail",  // 访问策略详情
			"anonymous",  // 是否匿名
			"status",  // 状态
		];
		$data = array_filter(
			$data,
			function ($key) use ($allowed) {
				return in_array($key, $allowed);
			},
			ARRAY_FILTER_USE_KEY
        );
        
        // 处理特殊数值
        if ( array_key_exists('cover', $data) && is_string($data['cover']) ) {
			$data['cover'] = json_decode($data['cover'], true);
        }
        if ( array_key_exists('policies_detail', $data) && is_string($data['policies_detail']) ) {
			$data['policies_detail'] = json_decode($data['policies_detail'], true);
        }
        
        if ( array_key_exists('category_ids', $data) && is_string($data['category_ids']) ) {
            $data['category_ids'] = explode(",", $data['category_ids']);
            foreach( $data['category_ids'] as & $val ) {
                $val = trim($val);
            }
        }
        if ( array_key_exists('series_ids', $data) && is_string($data['series_ids']) ) {
            $data['series_ids'] = explode(",", $data['series_ids']);
            foreach( $data['series_ids'] as & $val ) {
                $val = trim($val);
            }
        }
        if ( array_key_exists('tags', $data) && is_string($data['tags']) ) {
            $data['tags'] = explode(",", $data['tags']);
            foreach( $data['tags'] as & $val ) {
                $val = trim($val);
            }
        }
        
        // 状态
        if (empty($data["status"])) {
            $data["status"] = "opened";
        }
    
        if ( !empty($data["publish_time"]) ) {
            $data["publish_time"] = date("Y-m-d H:i:s", strtotime($data["publish_time"]));
        } else {
            $data["publish_time"] = date("Y-m-d H:i:s");
        }
    
        if ( $data["status"] != "opened"  ) {
            unset($data["publish_time"]);
        }

        $qu = new \Xpmsns\Qanda\Model\Question;
        return $qu->create( $data );
    }


    /**
     * 修改提问
     */
    function update( $query, $data ) {
        
        // 检查必填项目
        if ( empty( $data["question_id"] ) ) {
            throw new Excp("请填写问题ID内容", 402, ["query"=>$query, "data"=>$data]);
        }

        // 检查用户登录
        $user = \Xpmsns\User\Model\User::Info();
        $user_id = $user["user_id"];
        if ( empty($user_id) ) {
            throw new Excp("用户尚未登录", 402, ["query"=>$query, "data"=>$data]);
        }

        // 读取用户ID 
        $data["user_id"] = $user_id;

        // 验证修改权限
        $qu = new \Xpmsns\Qanda\Model\Question;
        $question = $qu->getByQuestionId($data["question_id"]);
        if ( empty($question) ) { 
            throw new Excp("提问不存在或已被删除", 404, ["query"=>$query, "data"=>$data]);
        }
        if ( $question["user_id"] != $user_id ) {
            throw new Excp("没有该提问的修改权限(不是该问题作者)", 403, ["query"=>$query, "data"=>$data]);
        }
        if ( $question["status"] == "forbidden") {
            throw new Excp("没有该提问的修改权限(已被封禁)", 403, ["query"=>$query, "data"=>$data]);
        }

        // 许可字段清单
		$allowed =  [
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
			"policies",  // 访问策略
			"policies_detail",  // 访问策略详情
			"anonymous",  // 是否匿名
			"status",  // 状态
		];
		$data = array_filter(
			$data,
			function ($key) use ($allowed) {
				return in_array($key, $allowed);
			},
			ARRAY_FILTER_USE_KEY
        );
        
        // 记录修改历史
        $history = [];
        if ( is_array($question["history"]) ) {
            $history = $question["history"];
        }
        unset( $question["history"] );
        $question["updated_time"] = time();
        array_push( $history, $question );
        $data["history"] = $history;


        // 处理特殊数值
        if ( array_key_exists('cover', $data) && is_string($data['cover']) ) {
			$data['cover'] = json_decode($data['cover'], true);
        }
        if ( array_key_exists('policies_detail', $data) && is_string($data['policies_detail']) ) {
			$data['policies_detail'] = json_decode($data['policies_detail'], true);
        }
        
        if ( array_key_exists('category_ids', $data) && is_string($data['category_ids']) ) {
            $data['category_ids'] = explode(",", $data['category_ids']);
            foreach( $data['category_ids'] as & $val ) {
                $val = trim($val);
            }
        }
        if ( array_key_exists('series_ids', $data) && is_string($data['series_ids']) ) {
            $data['series_ids'] = explode(",", $data['series_ids']);
            foreach( $data['series_ids'] as & $val ) {
                $val = trim($val);
            }
        }
        if ( array_key_exists('tags', $data) && is_string($data['tags']) ) {
            $data['tags'] = explode(",", $data['tags']);
            foreach( $data['tags'] as & $val ) {
                $val = trim($val);
            }
        }
        
        return $qu->saveBy("question_id", $data);
    }

    // @KEEP END







	/**
	 * 根据条件检索提问记录
	 * @param  array $query GET 参数
	 *         	      $query['select'] 选取字段，默认选择 ["question.question_id","question.user_id","question.title","question.summary","question.cover","question.category_ids","question.series_ids","question.tags","question.publish_time","question.coin","question.money","question.coin_view","question.money_view","question.policies","question.policies_detail","question.anonymous","question.view_cnt","question.agree_cnt","question.answer_cnt","question.priority","question.status","question.created_at","question.updated_at","user.name","user.nickname","category.name","series.name"]
	 *         	      $query['page'] 页码，默认为 1
	 *         	      $query['perpage'] 每页显示记录数，默认为 20
	 *			      $query["keyword"] 按关键词查询
	 *			      $query["question_ids"] 按问题ID查询 ( AND IN )
	 *			      $query["user_id"] 按用户ID查询 ( AND = )
	 *			      $query["category_ids"] 按类目查询 ( AND LIKE-MULTIPLE )
	 *			      $query["series_ids"] 按系列查询 ( AND LIKE-MULTIPLE )
	 *			      $query["tags"] 按标签查询 ( AND LIKE-MULTIPLE )
	 *			      $query["status"] 按状态查询 ( AND = )
	 *			      $query["status_not"] 按状态查询 ( AND <> )
	 *			      $query["policies_not"] 按访问策略查询 ( AND <> )
	 *			      $query["policies"] 按访问策略查询 ( AND = )
	 *			      $query["coin"] 按悬赏积分查询 ( AND > )
	 *			      $query["money"] 按悬赏金额查询 ( AND > )
	 *			      $query["coin_view"] 按围观积分查询 ( AND > )
	 *			      $query["money_view"] 按围观金额查询 ( AND > )
	 *			      $query["anonymous"] 按是否匿名查询 ( AND = )
	 *			      $query["before"] 按发布时间查询 ( AND <= )
	 *			      $query["after"] 按发布时间查询 ( AND >= )
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
	 * @param  array $data  POST 参数
	 *         	      $data['select'] 选取字段，默认选择 ["name=question_id","name=user_id","name=title","name=summary","name=cover","name=category_ids","name=series_ids","name=tags","name=publish_time","name=coin","name=money","name=coin_view","name=money_view","name=policies","name=policies_detail","name=anonymous","name=view_cnt","name=agree_cnt","name=answer_cnt","name=priority","name=status","name=created_at","name=updated_at","model=%5CXpmsns%5CUser%5CModel%5CUser&name=name&table=user&prefix=xpmsns_user_&alias=user&type=leftJoin","model=%5CXpmsns%5CUser%5CModel%5CUser&name=nickname&table=user&prefix=xpmsns_user_&alias=user&type=leftJoin","model=%5CXpmsns%5CPages%5CModel%5CCategory&name=name&table=category&prefix=xpmsns_pages_&alias=category&type=inWhere","model=%5CXpmsns%5CPages%5CModel%5CSeries&name=name&table=series&prefix=xpmsns_pages_&alias=series&type=inWhere"]
	 *         	      $data['page'] 页码，默认为 1
	 *         	      $data['perpage'] 每页显示记录数，默认为 20
	 *			      $data["keyword"] 按关键词查询
	 *			      $data["question_ids"] 按问题ID查询 ( AND IN )
	 *			      $data["user_id"] 按用户ID查询 ( AND = )
	 *			      $data["category_ids"] 按类目查询 ( AND LIKE-MULTIPLE )
	 *			      $data["series_ids"] 按系列查询 ( AND LIKE-MULTIPLE )
	 *			      $data["tags"] 按标签查询 ( AND LIKE-MULTIPLE )
	 *			      $data["status"] 按状态查询 ( AND = )
	 *			      $data["status_not"] 按状态查询 ( AND <> )
	 *			      $data["policies_not"] 按访问策略查询 ( AND <> )
	 *			      $data["policies"] 按访问策略查询 ( AND = )
	 *			      $data["coin"] 按悬赏积分查询 ( AND > )
	 *			      $data["money"] 按悬赏金额查询 ( AND > )
	 *			      $data["coin_view"] 按围观积分查询 ( AND > )
	 *			      $data["money_view"] 按围观金额查询 ( AND > )
	 *			      $data["anonymous"] 按是否匿名查询 ( AND = )
	 *			      $data["before"] 按发布时间查询 ( AND <= )
	 *			      $data["after"] 按发布时间查询 ( AND >= )
	 *			      $data["publish_desc"]  按发布时间倒序 DESC 排序
	 *			      $data["created_desc"]  按创建时间倒序 DESC 排序
	 *			      $data["created_asc"]  按创建时间正序 ASC 排序
	 *			      $data["publish_asc"]  按发布时间正序 ASC 排序
	 *			      $data["answer_desc"]  按答案数量倒序 DESC 排序
	 *			      $data["agree_desc"]  按赞同数量倒序 DESC 排序
	 *			      $data["view_desc"]  按浏览数量倒序 DESC 排序
	 *			      $data["money_desc"]  按悬赏金额倒序 DESC 排序
	 *			      $data["coin_desc"]  按悬赏积分倒序 DESC 排序
	 *			      $data["priority_asc"]  按优先级正序 ASC 排序
	 *
	 * @return array 提问记录集 {"total":100, "page":1, "perpage":20, data:[{"key":"val"}...], "from":1, "to":1, "prev":false, "next":1, "curr":10, "last":20}
	 *               data:[{"key":"val"}...] 字段
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
	protected function search( $query, $data ) {


		// 支持POST和GET查询
		$data = array_merge( $query, $data );

		// 读取字段
		$select = empty($data['select']) ? ["question.question_id","question.user_id","question.title","question.summary","question.cover","question.category_ids","question.series_ids","question.tags","question.publish_time","question.coin","question.money","question.coin_view","question.money_view","question.policies","question.policies_detail","question.anonymous","question.view_cnt","question.agree_cnt","question.answer_cnt","question.priority","question.status","question.created_at","question.updated_at","user.name","user.nickname","category.name","series.name"] : $data['select'];
		if ( is_string($select) ) {
			$select = explode(',', $select);
		}
		$data['select'] = $select;

		$inst = new \Xpmsns\Qanda\Model\Question;
		return $inst->search( $data );
	}


}