<?php

/**
 * @file WidgetCommon.php
 * This file is part of MOVIM.
 *
 * @brief The widgets commons methods.
 *
 * @author Timothée Jaussoin <edhelas@gmail.com>
 *
 * @date 08 march 2012
 *
 * Copyright (C)2010 MOVIM Project
 *
 * See COPYING for licensing information.
 */

class WidgetCommon extends WidgetBase {
    /*
     * @desc Prepare a group of messages
     * @param array of messages
     * @return generated HTML
     */
    protected function preparePosts($messages) {
        if($messages == false) {
			$html = false;
		} else {
			$html = '';

            // We create the array for the comments request
            $commentid = array();
            $i = 0;
            
            foreach($messages as $message) {
                if($i == 0)
                    array_push($commentid, $message[0]->getData('nodeid'));

                else
                    array_push($commentid, '|'.$message[0]->getData('nodeid'));
                $i++;
            }
            
            // We request all the comments relative to our messages
            $query = Post::query()
                                ->join('Contact', array('Post.uri' => 'Contact.jid'))
                                ->where(
                                    array(
                                        'Post`.`key' => $this->user->getLogin(), 
                                        //'Contact`.`key' => $this->user->getLogin(), 
                                        array('Post`.`parentid' => $commentid)))
                                ->orderby('Post.published', false);
            $comments = Post::run_query($query);
            
            $duplicate = array();
            
            foreach($messages as $message) {
                if(!in_array($message[0]->getData('nodeid'), $duplicate)) {

                    // We split the interesting comments for each messages
                    $i = 0;
                    $messagecomment = array();
                    foreach($comments as $comment) {
                        if($message[0]->getData('nodeid') == $comments[$i][0]->getData('parentid')) {
                            array_push($messagecomment, $comment);
                            unset($comment);

                        }
                        $i++;
                    }
                    array_push($duplicate, $message[0]->getData('nodeid'));

                    $html .= $this->preparePost($message, $messagecomment);
                }
			}
			
        }
		
		return $html;
    }
    
    protected function preparePost($message, $comments = false) {        
        $tmp = '';
        
        if(isset($message[1])) {
            $tmp = '<div class="post ';
                if($this->user->getLogin() == $message[0]->getData('jid'))
                    $tmp .= 'me';
            $tmp .= '" id="'.$message[0]->getData('nodeid').'" >
            
                    <a href="?q=friend&f='.$message[0]->getData('jid').'">
                        <img class="avatar" src="'.$message[1]->getPhoto('s').'">
                    </a>

                    <span>
                        <a href="?q=friend&f='.$message[0]->getData('jid').'">'.$message[1]->getTrueName().'</a>
                    </span>
                    <span class="date">
                        '.prepareDate(strtotime($message[0]->getData('updated'))).'
                    </span>';
                    
                    if($this->user->getLogin() == $message[0]->getData('jid')) {
                        $tmp .= '
                            <span 
                                class="delete" 
                                onclick="'.
                                    $this->genCallAjax(
                                        'ajaxDeletePost', 
                                        "'".$this->user->getLogin()."'", 
                                        "'".$message[0]->getData('nodeid')."'").'" 
                                title="'.t("Delete this post").'">
                                X
                            </span>';
                    }
                    
                    
            $tmp .= '<div class="content">
                        '.prepareString($message[0]->getData('content')). '</div>';
                        
            //$attachments = AttachmentHandler::getAttachment($this->user->getLogin(), $message[0]->getData('nodeid'));
            /*if($attachments) {
                $tmp .= '<div class="attachment">';
                foreach($attachments as $attachment)
                    $tmp .= '<a target="_blank" href="'.$attachment->getData('link').'"><img alt="'.$attachment->getData('title').'" title="'.$attachment->getData('title').'" src="'.$attachment->getData('thumb').'"></a>';
                $tmp .= '</div>';
            }*/
            
            
            if($message[0]->getPlace() != false)
                $tmp .= '<span class="place">
                            <a 
                                target="_blank" 
                                href="http://www.openstreetmap.org/?lat='.$message[0]->getData('lat').'&lon='.$message[0]->getData('lon').'&zoom=10"
                            >'.$message[0]->getPlace().'</a>
                         </span>';
                         
            if($message[0]->getData('jid') != $message[0]->getData('uri'))
                $tmp .= '<span class="recycle"><a href="?q=friend&f='.$message[0]->getData('uri').'">'.$message[0]->getData('name').'</a></span>';
              
            if($message[0]->getData('commentson') == 1) {
                $tmp .= '<div class="comments" id="'.$message[0]->getData('nodeid').'comments">';

                $commentshtml = $this->prepareComments($comments);
                
                if($commentshtml != false)
                    $tmp .= $commentshtml;

                $tmp .= '
                         <div class="comment">
                                <a 
                                    class="getcomments icon bubble" 
                                    style="margin-left: 0px;" 
                                    onclick="'.$this->genCallAjax('ajaxGetComments', "'".$message[0]->getData('commentplace')."'", "'".$message[0]->getData('nodeid')."'").'; this.innerHTML = \''.t('Loading comments ...').'\'">'.
                                        t('Get the comments').'
                                </a>
                            </div></div>';
                $tmp .= '<div class="comments">
                            <div 
                                class="comment"
                                onclick="this.parentNode.querySelector(\'#commentsubmit\').style.display = \'table\'; this.style.display =\'none\'">
                                <a class="getcomments icon bubbleadd">'.t('Add a comment').'</a>
                            </div>
                            <table id="commentsubmit">
                                <tr>
                                    <td>
                                        <textarea id="'.$message[0]->getData('nodeid').'commentcontent" onkeyup="movim_textarea_autoheight(this);"></textarea>
                                    </td>
                                </tr>
                                <tr class="commentsubmitrow">
                                    <td style="width: 100%;"></td>
                                    <td>
                                        <a
                                            onclick="
                                                    if(document.getElementById(\''.$message[0]->getData('nodeid').'commentcontent\').value != \'\') {
                                                        '.$this->genCallAjax(
                                                            'ajaxPublishComment', 
                                                            "'".$message[0]->getData('commentplace')."'", 
                                                            "'".$message[0]->getData('nodeid')."'", 
                                                            "encodeURIComponent(document.getElementById('".$message[0]->getData('nodeid')."commentcontent').value)").
                                                            'document.getElementById(\''.$message[0]->getData('nodeid').'commentcontent\').value = \'\';
                                                    }"
                                            class="button tiny icon submit"
                                            style="padding-left: 28px;"
                                        >'.
                                            t("Submit").'
                                        </a>
                                    </td>
                                </tr>
                            </table>';
                $tmp .= '</div>';
            }
              
            $tmp .= '</div>';

        }
        return $tmp;
    }

    protected function prepareComments($comments) {
        $tmp = false;
        
        $size = sizeof($comments);
        
        $comcounter = 0;
        
        if($size > 3) {
            $tmp = '<div 
                        class="comment"
                        onclick="
                            com = this.parentNode.querySelectorAll(\'.comment\'); 
                            for(i = 0; i < com.length; i++) { com.item(i).style.display = \'block\';};
                            this.style.display = \'none\';">
                        <a class="getcomments icon bubbleold">'.t('Show the older comments').'</a>
                    </div>';
            $comcounter = $size - 3;
        }
        
        // Temporary array to prevent duplicate comments
        $duplicate = array();

        if($comments) {
            foreach($comments as $comment) {
                if(!in_array($comment[0]->getData('nodeid'), $duplicate)) {
                    if(isset($comment[1])) {
                        $photo = $comment[1]->getPhoto('s');
                        $name = $comment[1]->getTrueName();
                    }
                    else {
                        $photo = "image.php?c=default";
                    }
                    
                    if($name == null)
                        $name = $comment[0]->getData('uri');
                    
                    $tmp .= '
                        <div class="comment" ';
                    if($comcounter > 0) {
                        $tmp .= 'style="display:none;"';
                        $comcounter--;
                    }
                        
                    $tmp .='>
                            <img class="avatar tiny" src="'.$photo.'">
                            <span><a href="?q=friend&f='.$comment[0]->getData('uri').'">'.$name.'</a></span>
                            <span class="date">'.prepareDate(strtotime($comment[0]->getData('published'))).'</span><br />
                            <div class="content tiny">'.prepareString($comment[0]->getData('content')).'</div>
                        </div>';
                    
                    array_push($duplicate, $comment[0]->getData('nodeid'));
                } else {
                    $comcounter--;
                }
            }
        }
        
        return $tmp;
    }
    
    function onComment($parent, $comments) {
        // We request all the comments relative to our messages
        $query = Post::query()
                            ->join('Contact', array('Post.uri' => 'Contact.jid'))
                            ->where(
                                array(
                                    'Post`.`key' => $this->user->getLogin(), 
                                    //'Contact`.`key' => $this->user->getLogin(), 
                                    'Post`.`parentid' => $parent))
                            ->orderby('Post.published', false);
        $comments = Post::run_query($query);   
        $html = $this->prepareComments($comments);
        RPC::call('movim_fill', $parent.'comments', RPC::cdata($html));
    }
    
    function onNoComment($parent) {     
        $html = '
            <div class="comment">
                <a 
                    class="getcomments icon bubble" 
                    style="margin-left: 0px;">'.
                    t('No comments').
                '</a>
            </div>';
        RPC::call('movim_fill', $parent.'comments', RPC::cdata($html));
    }
    
    function onNoCommentStream($parent) { 
        $html = '
            <div class="comment">
                <a 
                    class="getcomments icon bubble" 
                    style="margin-left: 0px;">'.
                    t('No comments stream').
                '</a>
            </div>';
        RPC::call('movim_fill', $parent.'comments', RPC::cdata($html));
    }
    
	function ajaxGetComments($jid, $id) {
		$c = new moxl\MicroblogCommentsGet();
        $c->setTo($jid)
          ->setId($id)
          ->request();
	}
    
    function ajaxPublishComment($to, $id, $content) {
        if($content != '') {
            $p = new moxl\MicroblogCommentPublish();
            $p->setTo($to)
              ->setFrom($this->user->getLogin())
              ->setParentId($id)
              ->setContent(htmlspecialchars(rawurldecode($content)))
              ->request();
        }
    }
    
    function ajaxDeletePost($to, $id) {
        $p = new moxl\MicroblogPostDelete();
        $p->setTo($to)
          ->setId($id)
          ->request();
    }
    
    function onPostDelete($id) {
        RPC::call('movim_delete', $id);
    }
    
    function onPostDeleteError($params) {
        $html .=
            '<div class="message error">'.t('An error occured : ').$params[1].'</div>';
        RPC::call('movim_fill', $params[0] , RPC::cdata($html));
    }
}
