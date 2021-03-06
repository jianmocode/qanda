<?php
/**
 * Class Answer 
 * 回答数据接口 
 *
 * 程序作者: XpmSE机器人
 * 最后修改: 2019-01-28 18:19:16
 * 程序母版: /data/stor/private/templates/xpmsns/model/code/api/Name.php
 */
namespace Xpmsns\Qanda\Api;
                       

use \Xpmse\Loader\App;
use \Xpmse\Excp;
use \Xpmse\Utils;
use \Xpmse\Api;

class Answer extends Api {

	/**
	 * 回答数据接口
	 */
	function __construct() {
		parent::__construct();
	}

	/**
	 * 自定义函数 
	 */

    // @KEEP BEGIN

    /**
     * 发布回答
     */
    protected function create( $query, $data ) {

        // 检查必填项目
        if ( empty( $data["question_id"] ) ) {
            throw new Excp("请填写问题ID", 402, ["query"=>$query, "data"=>$data]);
        }

        if ( empty( $data["content"]) ) {
            throw new Excp("请填写回答内容", 402, ["query"=>$query, "data"=>$data]);
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
            "question_id",  // 问题ID
            "user_id",  // 用户ID
            "summary",  // 摘要
			"content",  //  正文
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
        if ( array_key_exists('policies_detail', $data) && is_string($data['policies_detail']) ) {
			$data['policies_detail'] = json_decode($data['policies_detail'], true);
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

        // 摘要
        if ( empty($data["summary"]) && !empty($data["content"]) ) {
            $data["summary"] = \Xpmsns\Qanda\Model\Question::summary( $data["content"], 64) ;
        }

        $qu = new \Xpmsns\Qanda\Model\Answer;
        return $qu->createByUserId( $user_id, $data );
    }


    /**
     * 修改提问
     */
    protected function update( $query, $data ) {
        
        // 检查必填项目
        if ( empty( $data["answer_id"] ) ) {
            throw new Excp("请填写回答ID", 402, ["query"=>$query, "data"=>$data]);
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
        $an = new \Xpmsns\Qanda\Model\Answer;
        $answer = $an->getByAnswerId($data["answer_id"]);
        if ( empty($answer) ) { 
            throw new Excp("回答不存在或已被删除", 404, ["query"=>$query, "data"=>$data]);
        }
        if ( $answer["user_id"] != $user_id ) {
            throw new Excp("没有该回答的修改权限(不是该回答的作者)", 403, ["query"=>$query, "data"=>$data]);
        }
        if ( $answer["status"] == "forbidden") {
            throw new Excp("没有该回答的修改权限(已被封禁)", 403, ["query"=>$query, "data"=>$data]);
        }

        // 许可字段清单
		$allowed =  [
            "answer_id",  // 回答ID
            "summary",  // 摘要
			"content",  //  正文
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
        if ( is_array($answer["history"]) ) {
            $history = $answer["history"];
        }
        unset( $answer["history"] );
        $answer["updated_time"] = time();
        array_push( $history, $answer );
        $data["history"] = $history;


        // 处理特殊数值
        if ( array_key_exists('policies_detail', $data) && is_string($data['policies_detail']) ) {
			$data['policies_detail'] = json_decode($data['policies_detail'], true);
        }
        
        // 摘要
        if ( empty($data["summary"]) && !empty($data["content"]) ) {
            $data["summary"] = \Xpmsns\Qanda\Model\Question::summary( $data["content"], 64) ;
        }
        return $an->saveBy("answer_id", $data);
    }


    
    protected function search( $query, $data ) {

		// 支持POST和GET查询
		$data = array_merge( $query, $data );

		// 读取字段
		$select = empty($data['select']) ? ["question.question_id","question.title","answer.user_id","answer.summary","answer.content","answer.accepted","answer.status","answer.status","answer.publish_time","user.name","user.nickname","user.bio","user.headimgurl","user.follower_cnt","user.following_cnt","user.question_cnt","user.answer_cnt"] : $data['select'];
		if ( is_string($select) ) {
			$select = explode(',', $select);
		}
		$data['select'] = $select;

		$an = new \Xpmsns\Qanda\Model\Answer;
        $resp = $an->search( $data );
        
        // 关联用户赞赏
        $user = \Xpmsns\User\Model\User::info();
        if ( !empty($user["user_id"]) && $query["withagree"] == 1 ) {
            $an->withAgree( $resp["data"], $user["user_id"] );
        }

        // 关联用户关系
        if ( !empty($user["user_id"]) && $query["withrelation"] == 1 ) {
            \Xpmsns\User\Model\User::withRelation( $resp["data"], $user["user_id"] );
        }

        return $resp;
    }
    

    /**
     * 标记为离开回答(一般为当浏览器关闭/小程序/APP页面切换时调用)
     * @param string $answer_id 回答ID
     */
    protected function leave( $query ){
       
        $answer_id = $query['answer_id'];
        $ans = new \Xpmsns\Qanda\Model\Answer;
        // 标记为关闭并记录阅读时长
        $duration = $ans->closed( $answer_id );

        try {  // 触发关闭文章行为
            \Xpmsns\User\Model\Behavior::trigger("xpmsns/qanda/answer/close", [
                "answer_id"=>$answer_id,
                "inviter" => \Xpmsns\User\Model\User::inviter(),
                "duration" => $duration,
                "time"=>time()
            ]);
        } catch(Excp $e) { $e->log(); }

        return $duration;
    }

    // @KEEP END

}