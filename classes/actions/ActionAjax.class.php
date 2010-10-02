<?php
/*-------------------------------------------------------
*
*   LiveStreet Engine Social Networking
*   Copyright © 2008 Mzhelskiy Maxim
*
*--------------------------------------------------------
*
*   Official site: www.livestreet.ru
*   Contact e-mail: rus.engine@gmail.com
*
*   GNU General Public License, version 2:
*   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*
---------------------------------------------------------
*/

/**
 * Класс обработки ajax запросов
 *
 */
class ActionAjax extends Action {	
	
	
	public function Init() {
		$this->Viewer_SetResponseAjax('json');
		$this->Security_ValidateSendForm();
		
		$this->oUserCurrent=$this->User_GetUserCurrent();
	}
	
	protected function RegisterEvent() {	
		$this->AddEventPreg('/^vote$/i','/^comment$/','EventVoteComment');
		$this->AddEventPreg('/^vote$/i','/^topic$/','EventVoteTopic');
		$this->AddEventPreg('/^vote$/i','/^blog$/','EventVoteBlog');
		$this->AddEventPreg('/^vote$/i','/^user$/','EventVoteUser');
		
		$this->AddEventPreg('/^favourite$/i','/^topic$/','EventFavouriteTopic');
		$this->AddEventPreg('/^favourite$/i','/^comment$/','EventFavouriteComment');
		$this->AddEventPreg('/^favourite$/i','/^talk$/','EventFavouriteTalk');
		
		$this->AddEventPreg('/^stream$/i','/^comment$/','EventStreamComment');
		$this->AddEventPreg('/^stream$/i','/^topic$/','EventStreamTopic');
		
		$this->AddEventPreg('/^blogs$/i','/^top$/','EventBlogsTop');
		$this->AddEventPreg('/^blogs$/i','/^self$/','EventBlogsSelf');
		$this->AddEventPreg('/^blogs$/i','/^join$/','EventBlogsJoin');
	}
		
	
	/**********************************************************************************
	 ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
	 **********************************************************************************
	 */	
	
	/**
	 * Голосование за комментарий
	 *
	 */
	protected function EventVoteComment() {
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		
		if (!($oComment=$this->Comment_GetCommentById(getRequest('idComment',null,'post')))) {
			$this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error_noexists'),$this->Lang_Get('error'));
			return;
		}
		
		if ($oComment->getUserId()==$this->oUserCurrent->getId()) {
			$this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error_self'),$this->Lang_Get('attention'));
			return;
		}
		
		if ($oTopicCommentVote=$this->Vote_GetVote($oComment->getId(),'comment',$this->oUserCurrent->getId())) {
			$this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error_already'),$this->Lang_Get('attention'));
			return;
		}
		
		if (strtotime($oComment->getDate())<=time()-Config::Get('acl.vote.comment.limit_time')) {
			$this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error_time'),$this->Lang_Get('attention'));
			return;
		}
		
		if (!$this->ACL_CanVoteComment($this->oUserCurrent,$oComment)) {
			$this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error_acl'),$this->Lang_Get('attention'));
			return;
		}
		
		$iValue=getRequest('value',null,'post');
		if (!in_array($iValue,array('1','-1'))) {
			$this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error_value'),$this->Lang_Get('attention'));
			return;
		}
		
		$oTopicCommentVote=Engine::GetEntity('Vote');
		$oTopicCommentVote->setTargetId($oComment->getId());
		$oTopicCommentVote->setTargetType('comment');
		$oTopicCommentVote->setVoterId($this->oUserCurrent->getId());
		$oTopicCommentVote->setDirection($iValue);
		$oTopicCommentVote->setDate(date("Y-m-d H:i:s"));
		$iVal=(float)$this->Rating_VoteComment($this->oUserCurrent,$oComment,$iValue);
		$oTopicCommentVote->setValue($iVal);

		$oComment->setCountVote($oComment->getCountVote()+1);
		if ($this->Vote_AddVote($oTopicCommentVote) and $this->Comment_UpdateComment($oComment)) {			
			$this->Message_AddNoticeSingle($this->Lang_Get('comment_vote_ok'),$this->Lang_Get('attention'));
			$this->Viewer_AssignAjax('iRating',$oComment->getRating());
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error'),$this->Lang_Get('error'));
			return;
		}
	}
	
	
	/**
	 * Голосование за топик
	 *
	 */
	protected function EventVoteTopic() {
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		
		if (!($oTopic=$this->Topic_GetTopicById(getRequest('idTopic',null,'post')))) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
		
		if ($oTopic->getUserId()==$this->oUserCurrent->getId()) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_vote_error_self'),$this->Lang_Get('attention'));
			return;
		}
		
		if ($oTopicVote=$this->Vote_GetVote($oTopic->getId(),'topic',$this->oUserCurrent->getId())) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_vote_error_already'),$this->Lang_Get('attention'));
			return;
		}
		
		if (strtotime($oTopic->getDateAdd())<=time()-Config::Get('acl.vote.topic.limit_time')) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_vote_error_time'),$this->Lang_Get('attention'));
			return;
		}
		
		if (!$this->ACL_CanVoteTopic($this->oUserCurrent,$oTopic) and $iValue) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_vote_error_acl'),$this->Lang_Get('attention'));
			return;
		}
		
		$iValue=getRequest('value',null,'post');
		if (!in_array($iValue,array('1','-1','0'))) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('attention'));
			return;
		}
		
		
		$oTopicVote=Engine::GetEntity('Vote');
		$oTopicVote->setTargetId($oTopic->getId());
		$oTopicVote->setTargetType('topic');
		$oTopicVote->setVoterId($this->oUserCurrent->getId());
		$oTopicVote->setDirection($iValue);
		$oTopicVote->setDate(date("Y-m-d H:i:s"));
		$iVal=0;
		if ($iValue!=0) {
			$iVal=(float)$this->Rating_VoteTopic($this->oUserCurrent,$oTopic,$iValue);
		}
		$oTopicVote->setValue($iVal);
		$oTopic->setCountVote($oTopic->getCountVote()+1);
		if ($this->Vote_AddVote($oTopicVote) and $this->Topic_UpdateTopic($oTopic)) {			
			if ($iValue) {
				$this->Message_AddNoticeSingle($this->Lang_Get('topic_vote_ok'),$this->Lang_Get('attention'));
			} else {
				$this->Message_AddNoticeSingle($this->Lang_Get('topic_vote_ok_abstain'),$this->Lang_Get('attention'));
			}
			$this->Viewer_AssignAjax('iRating',$oTopic->getRating());
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}		
	}
	
	
	
	/**
	 * Голосование за блог
	 *
	 */
	protected function EventVoteBlog() {
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		
		if (!($oBlog=$this->Blog_GetBlogById(getRequest('idBlog',null,'post')))) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
		
		if ($oBlog->getOwnerId()==$this->oUserCurrent->getId()) {
			$this->Message_AddErrorSingle($this->Lang_Get('blog_vote_error_self'),$this->Lang_Get('attention'));
			return;
		}
		
		if ($oBlogVote=$this->Vote_GetVote($oBlog->getId(),'blog',$this->oUserCurrent->getId())) {
			$this->Message_AddErrorSingle($this->Lang_Get('blog_vote_error_already'),$this->Lang_Get('attention'));
			return;
		}
				
		switch($this->ACL_CanVoteBlog($this->oUserCurrent,$oBlog)) {
			case ModuleACL::CAN_VOTE_BLOG_TRUE:
				$iValue=getRequest('value',null,'post');
				if (in_array($iValue,array('1','-1'))) {
					$oBlogVote=Engine::GetEntity('Vote');
					$oBlogVote->setTargetId($oBlog->getId());
					$oBlogVote->setTargetType('blog');
					$oBlogVote->setVoterId($this->oUserCurrent->getId());
					$oBlogVote->setDirection($iValue);
					$oBlogVote->setDate(date("Y-m-d H:i:s"));
					$iVal=(float)$this->Rating_VoteBlog($this->oUserCurrent,$oBlog,$iValue);
					$oBlogVote->setValue($iVal);
					$oBlog->setCountVote($oBlog->getCountVote()+1);
					if ($this->Vote_AddVote($oBlogVote) and $this->Blog_UpdateBlog($oBlog)) {						
						$this->Viewer_AssignAjax('iCountVote',$oBlog->getCountVote());
						$this->Viewer_AssignAjax('iRating',$oBlog->getRating());
						$this->Message_AddNoticeSingle($this->Lang_Get('blog_vote_ok'),$this->Lang_Get('attention'));
					} else {
						$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('attention'));
						return;
					}
				} else {
					$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('attention'));
					return;
				}
				break;
			case ModuleACL::CAN_VOTE_BLOG_ERROR_CLOSE:
				$this->Message_AddErrorSingle($this->Lang_Get('blog_vote_error_close'),$this->Lang_Get('attention'));
				return;
				break;

			default:
			case ModuleACL::CAN_VOTE_BLOG_FALSE:
				$this->Message_AddErrorSingle($this->Lang_Get('blog_vote_error_acl'),$this->Lang_Get('attention'));
				return;
				break;
		}
	}
	
	
	
	/**
	 * Голосование за блог
	 *
	 */
	protected function EventVoteUser() {
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		
		if (!($oUser=$this->User_GetUserById(getRequest('idUser',null,'post')))) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
		
		if ($oUser->getId()==$this->oUserCurrent->getId()) {
			$this->Message_AddErrorSingle($this->Lang_Get('user_vote_error_self'),$this->Lang_Get('attention'));
			return;
		}
		
		if ($oUserVote=$this->Vote_GetVote($oUser->getId(),'user',$this->oUserCurrent->getId())) {
			$this->Message_AddErrorSingle($this->Lang_Get('user_vote_error_already'),$this->Lang_Get('attention'));
			return;
		}
		
		if (!$this->ACL_CanVoteUser($this->oUserCurrent,$oUser)) {
			$this->Message_AddErrorSingle($this->Lang_Get('user_vote_error_acl'),$this->Lang_Get('attention'));
			return;
		}
		
		$iValue=getRequest('value',null,'post');
		if (!in_array($iValue,array('1','-1'))) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('attention'));
			return;
		}
		
		
		$oUserVote=Engine::GetEntity('Vote');
		$oUserVote->setTargetId($oUser->getId());
		$oUserVote->setTargetType('user');
		$oUserVote->setVoterId($this->oUserCurrent->getId());
		$oUserVote->setDirection($iValue);
		$oUserVote->setDate(date("Y-m-d H:i:s"));
		$iVal=(float)$this->Rating_VoteUser($this->oUserCurrent,$oUser,$iValue);
		$oUserVote->setValue($iVal);
		//$oUser->setRating($oUser->getRating()+$iValue);
		$oUser->setCountVote($oUser->getCountVote()+1);
		if ($this->Vote_AddVote($oUserVote) and $this->User_Update($oUser)) {			
			$this->Message_AddNoticeSingle($this->Lang_Get('user_vote_ok'),$this->Lang_Get('attention'));			
			$this->Viewer_AssignAjax('iRating',$oUser->getRating());
			$this->Viewer_AssignAjax('iSkill',$oUser->getSkill());
			$this->Viewer_AssignAjax('iCountVote',$oUser->getCountVote());			
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
	}
	
	
	/**
	 * Обработка избранного - топик
	 *
	 */
	protected function EventFavouriteTopic() {
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		
		$iType=getRequest('type',null,'post');
		if (!in_array($iType,array('1','0'))) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
		
		if (!($oTopic=$this->Topic_GetTopicById(getRequest('idTopic',null,'post')))) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
		
		
		$oFavouriteTopic=$this->Topic_GetFavouriteTopic($oTopic->getId(),$this->oUserCurrent->getId());
		if (!$oFavouriteTopic and $iType) {
			$oFavouriteTopicNew=Engine::GetEntity('Favourite',
				array(
					'target_id'      => $oTopic->getId(),
					'user_id'        => $this->oUserCurrent->getId(),
					'target_type'    => 'topic',
					'target_publish' => $oTopic->getPublish()
				)
			);
			if ($this->Topic_AddFavouriteTopic($oFavouriteTopicNew)) {				
				$this->Message_AddNoticeSingle($this->Lang_Get('topic_favourite_add_ok'),$this->Lang_Get('attention'));				
				$this->Viewer_AssignAjax('bState',true);
			} else {
				$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
				return;
			}
		}
		if (!$oFavouriteTopic and !$iType) {			
			$this->Message_AddErrorSingle($this->Lang_Get('topic_favourite_add_no'),$this->Lang_Get('error'));
			return;
		}
		if ($oFavouriteTopic and $iType) {			
			$this->Message_AddErrorSingle($this->Lang_Get('topic_favourite_add_already'),$this->Lang_Get('error'));
			return;
		}
		if ($oFavouriteTopic and !$iType) {
			if ($this->Topic_DeleteFavouriteTopic($oFavouriteTopic)) {				
				$this->Message_AddNoticeSingle($this->Lang_Get('topic_favourite_del_ok'),$this->Lang_Get('attention'));
				$this->Viewer_AssignAjax('bState',false);
			} else {				
				$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
				return;
			}
		}		
	}
	
	
	
	/**
	 * Обработка избранного - комментарий
	 *
	 */
	protected function EventFavouriteComment() {
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		
		$iType=getRequest('type',null,'post');
		if (!in_array($iType,array('1','0'))) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
		
		if (!($oComment=$this->Comment_GetCommentById(getRequest('idComment',null,'post')))) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
		
		$oFavouriteComment=$this->Comment_GetFavouriteComment($oComment->getId(),$this->oUserCurrent->getId());
		if (!$oFavouriteComment and $iType) {
			$oFavouriteCommentNew=Engine::GetEntity('Favourite',
				array(
					'target_id'      => $oComment->getId(),
					'target_type'    => 'comment',
					'user_id'        => $this->oUserCurrent->getId(),
					'target_publish' => $oComment->getPublish()
				)
			);
			if ($this->Comment_AddFavouriteComment($oFavouriteCommentNew)) {				
				$this->Message_AddNoticeSingle($this->Lang_Get('comment_favourite_add_ok'),$this->Lang_Get('attention'));
				$this->Viewer_AssignAjax('bState',true);
			} else {
				$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
				return;
			}
		}
		if (!$oFavouriteComment and !$iType) {			
			$this->Message_AddErrorSingle($this->Lang_Get('comment_favourite_add_no'),$this->Lang_Get('error'));
			return;
		}
		if ($oFavouriteComment and $iType) {			
			$this->Message_AddErrorSingle($this->Lang_Get('comment_favourite_add_already'),$this->Lang_Get('error'));
			return;
		}
		if ($oFavouriteComment and !$iType) {
			if ($this->Comment_DeleteFavouriteComment($oFavouriteComment)) {				
				$this->Message_AddNoticeSingle($this->Lang_Get('comment_favourite_del_ok'),$this->Lang_Get('attention'));
				$this->Viewer_AssignAjax('bState',false);
			} else {
				$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
				return;
			}
		}
	}
	
	
	/**
	 * Обработка избранного - письмо
	 *
	 */
	protected function EventFavouriteTalk() {
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		
		$iType=getRequest('type',null,'post');		
		if (!in_array($iType,array('1','0'))) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
		
		if (!($oTalk=$this->Talk_GetTalkById(getRequest('idTalk',null,'post')))) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}

			
		$oFavouriteTalk=$this->Talk_GetFavouriteTalk($oTalk->getId(),$this->oUserCurrent->getId());
		if (!$oFavouriteTalk and $iType) {
			$oFavouriteTalkNew=Engine::GetEntity('Favourite',
				array(
					'target_id'      => $oTalk->getId(),
					'target_type'    => 'talk',
					'user_id'        => $this->oUserCurrent->getId(),
					'target_publish' => '1'
				)
			);
			if ($this->Talk_AddFavouriteTalk($oFavouriteTalkNew)) {				
				$this->Message_AddNoticeSingle($this->Lang_Get('talk_favourite_add_ok'),$this->Lang_Get('attention'));
				$this->Viewer_AssignAjax('bState',true);
			} else {
				$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
				return;
			}
		}
		if (!$oFavouriteTalk and !$iType) {			
			$this->Message_AddErrorSingle($this->Lang_Get('talk_favourite_add_no'),$this->Lang_Get('error'));
			return;
		}
		if ($oFavouriteTalk and $iType) {			
			$this->Message_AddErrorSingle($this->Lang_Get('talk_favourite_add_already'),$this->Lang_Get('error'));
			return;
		}
		if ($oFavouriteTalk and !$iType) {
			if ($this->Talk_DeleteFavouriteTalk($oFavouriteTalk)) {				
				$this->Message_AddNoticeSingle($this->Lang_Get('talk_favourite_del_ok'),$this->Lang_Get('attention'));
				$this->Viewer_AssignAjax('bState',false);
			} else {
				$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
				return;
			}
		}
		
	}
	
	
	/**
	 * Обработка получения последних комментов
	 *
	 */
	protected function EventStreamComment() {
		if ($aComments=$this->Comment_GetCommentsOnline('topic',Config::Get('block.stream.row'))) {
			$oViewer=$this->Viewer_GetLocalViewer();
			$oViewer->Assign('aComments',$aComments);			
			$sTextResult=$oViewer->Fetch("block.stream_comment.tpl");
			$this->Viewer_AssignAjax('sText',$sTextResult);
		} else {			
			$this->Message_AddErrorSingle($this->Lang_Get('block_stream_comments_no'),$this->Lang_Get('attention'));
			return;
		}
	}
	
	
	/**
	 * Обработка получения последних топиков
	 *
	 */
	protected function EventStreamTopic() {
		if ($oTopics=$this->Topic_GetTopicsLast(Config::Get('block.stream.row'))) {
			$oViewer=$this->Viewer_GetLocalViewer();
			$oViewer->Assign('oTopics',$oTopics);			
			$sTextResult=$oViewer->Fetch("block.stream_topic.tpl");
			$this->Viewer_AssignAjax('sText',$sTextResult);
		} else {			
			$this->Message_AddErrorSingle($this->Lang_Get('block_stream_topics_no'),$this->Lang_Get('attention'));
			return;
		}
	}
	
	
	/**
	 * Обработка получения TOP блогов
	 *
	 */
	protected function EventBlogsTop() {
		if ($aResult=$this->Blog_GetBlogsRating(1,Config::Get('block.blogs.row'))) {
			$aBlogs=$aResult['collection'];
			$oViewer=$this->Viewer_GetLocalViewer();
			$oViewer->Assign('aBlogs',$aBlogs);			
			$sTextResult=$oViewer->Fetch("block.blogs_top.tpl");
			$this->Viewer_AssignAjax('sText',$sTextResult);
		} else {			
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
	}
	
	/**
	 * Обработка получения своих блогов
	 *
	 */
	protected function EventBlogsSelf() {
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		
		if ($aBlogs=$this->Blog_GetBlogsRatingSelf($this->oUserCurrent->getId(),Config::Get('block.blogs.row'))) {
			$oViewer=$this->Viewer_GetLocalViewer();
			$oViewer->Assign('aBlogs',$aBlogs);			
			$sTextResult=$oViewer->Fetch("block.blogs_top.tpl");
			$this->Viewer_AssignAjax('sText',$sTextResult);
		} else {			
			$this->Message_AddErrorSingle($this->Lang_Get('block_blogs_self_error'),$this->Lang_Get('attention'));
			return;
		}		
	}
	
	/**
	 * Обработка получения подключенных блогов
	 *
	 */
	protected function EventBlogsJoin() {
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
				
		if ($aBlogs=$this->Blog_GetBlogsRatingJoin($this->oUserCurrent->getId(),Config::Get('block.blogs.row'))) {
			$oViewer=$this->Viewer_GetLocalViewer();
			$oViewer->Assign('aBlogs',$aBlogs);			
			$sTextResult=$oViewer->Fetch("block.blogs_top.tpl");
			$this->Viewer_AssignAjax('sText',$sTextResult);
		} else {			
			$this->Message_AddErrorSingle($this->Lang_Get('block_blogs_join_error'),$this->Lang_Get('attention'));
			return;
		}		
	}
	
}
?>