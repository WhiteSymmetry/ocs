<?php

/**
 * PresenterAction.inc.php
 *
 * Copyright (c) 2003-2007 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package submission
 *
 * PresenterAction class.
 *
 * $Id$
 */

import('submission.common.Action');

class PresenterAction extends Action {

	/**
	 * Constructor.
	 */
	function PresenterAction() {
		parent::Action();
	}
	
	/**
	 * Actions.
	 */
	 
	/**
	 * Designates the original file the review version.
	 * @param $presenterSubmission object
	 * @param $designate boolean
	 */
	function designateReviewVersion($presenterSubmission, $designate = false) {
		import('file.PaperFileManager');
		$paperFileManager = &new PaperFileManager($presenterSubmission->getPaperId());
		$presenterSubmissionDao = &DAORegistry::getDAO('PresenterSubmissionDAO');
		
		if ($designate && !HookRegistry::call('PresenterAction::designateReviewVersion', array(&$presenterSubmission))) {
			$submissionFile =& $presenterSubmission->getSubmissionFile();
			if ($submissionFile) {
				$reviewFileId = $paperFileManager->copyToReviewFile($submissionFile->getFileId());

				$presenterSubmission->setReviewFileId($reviewFileId);
			
				$presenterSubmissionDao->updatePresenterSubmission($presenterSubmission);

				$trackDirectorSubmissionDao =& DAORegistry::getDAO('TrackDirectorSubmissionDAO');
				$trackDirectorSubmissionDao->createReviewRound($presenterSubmission->getPaperId(), 1, 1, 1);
			}
		}
	}
	 
	/**
	 * Delete an presenter file from a submission.
	 * @param $paper object
	 * @param $fileId int
	 * @param $revisionId int
	 */
	function deletePaperFile($paper, $fileId, $revisionId) {
		import('file.PaperFileManager');

		$paperFileManager = &new PaperFileManager($paper->getPaperId());
		$paperFileDao = &DAORegistry::getDAO('PaperFileDAO');
		$presenterSubmissionDao = &DAORegistry::getDAO('PresenterSubmissionDAO');

		$paperFile = &$paperFileDao->getPaperFile($fileId, $revisionId, $paper->getPaperId());
		$presenterSubmission = $presenterSubmissionDao->getPresenterSubmission($paper->getPaperId());
		$presenterRevisions = $presenterSubmission->getPresenterFileRevisions();

		// Ensure that this is actually an presenter file.
		if (isset($paperFile)) {
			HookRegistry::call('PresenterAction::deletePaperFile', array(&$paperFile, &$presenterRevisions));
			foreach ($presenterRevisions as $round) {
				foreach ($round as $revision) {
					if ($revision->getFileId() == $paperFile->getFileId() &&
					    $revision->getRevision() == $paperFile->getRevision()) {
						$paperFileManager->deleteFile($paperFile->getFileId(), $paperFile->getRevision());
					}
				}
			}
		}
	}

	/**
	 * Upload the revised version of an paper.
	 * @param $presenterSubmission object
	 */
	function uploadRevisedVersion($presenterSubmission) {
		import("file.PaperFileManager");
		$paperFileManager = &new PaperFileManager($presenterSubmission->getPaperId());
		$presenterSubmissionDao = &DAORegistry::getDAO('PresenterSubmissionDAO');
		
		$fileName = 'upload';
		if ($paperFileManager->uploadedFileExists($fileName)) {
			HookRegistry::call('PresenterAction::uploadRevisedVersion', array(&$presenterSubmission));
			if ($presenterSubmission->getRevisedFileId() != null) {
				$fileId = $paperFileManager->uploadDirectorDecisionFile($fileName, $presenterSubmission->getRevisedFileId());
			} else {
				$fileId = $paperFileManager->uploadDirectorDecisionFile($fileName);
			}
		}
		
		if (isset($fileId) && $fileId != 0) {
			$presenterSubmission->setRevisedFileId($fileId);
			
			$presenterSubmissionDao->updatePresenterSubmission($presenterSubmission);

			// Add log entry
			$user = &Request::getUser();
			import('paper.log.PaperLog');
			import('paper.log.PaperEventLogEntry');
			PaperLog::logEvent($presenterSubmission->getPaperId(), PAPER_LOG_PRESENTER_REVISION, LOG_TYPE_PRESENTER, $user->getUserId(), 'log.presenter.documentRevised', array('presenterName' => $user->getFullName(), 'fileId' => $fileId, 'paperId' => $presenterSubmission->getPaperId()));
		}
	}
	
	//
	// Comments
	//
	
	/**
	 * View layout comments.
	 * @param $paper object
	 */
	function viewLayoutComments($paper) {
		if (!HookRegistry::call('PresenterAction::viewLayoutComments', array(&$paper))) {
			import("submission.form.comment.LayoutCommentForm");
			$commentForm = &new LayoutCommentForm($paper, ROLE_ID_DIRECTOR);
			$commentForm->initData();
			$commentForm->display();
		}
	}
	
	/**
	 * Post layout comment.
	 * @param $paper object
	 * @param $emailComment boolean
	 */
	function postLayoutComment($paper, $emailComment) {
		if (!HookRegistry::call('PresenterAction::postLayoutComment', array(&$paper, &$emailComment))) {
			import("submission.form.comment.LayoutCommentForm");

			$commentForm = &new LayoutCommentForm($paper, ROLE_ID_PRESENTER);
			$commentForm->readInputData();
		
			if ($commentForm->validate()) {
				$commentForm->execute();
				
				if ($emailComment) {
					$commentForm->email();
				}
			
			} else {
				$commentForm->display();
				return false;
			}
			return true;
		}
	}
	
	/**
	 * View director decision comments.
	 * @param $paper object
	 */
	function viewDirectorDecisionComments($paper) {
		if (!HookRegistry::call('PresenterAction::viewDirectorDecisionComments', array(&$paper))) {
			import("submission.form.comment.DirectorDecisionCommentForm");

			$commentForm = &new DirectorDecisionCommentForm($paper, ROLE_ID_PRESENTER);
			$commentForm->initData();
			$commentForm->display();
		}
	}
	
	/**
	 * Email director decision comment.
	 * @param $presenterSubmission object
	 * @param $send boolean
	 */
	function emailDirectorDecisionComment($presenterSubmission, $send) {
		$userDao = &DAORegistry::getDAO('UserDAO');
		$conference = &Request::getConference();

		$user = &Request::getUser();
		import('mail.PaperMailTemplate');
		$email = &new PaperMailTemplate($presenterSubmission);
	
		$editAssignments = $presenterSubmission->getEditAssignments();
		$directors = array();
		foreach ($editAssignments as $editAssignment) {
			array_push($directors, $userDao->getUser($editAssignment->getDirectorId()));
		}

		if ($send && !$email->hasErrors()) {
			HookRegistry::call('PresenterAction::emailDirectorDecisionComment', array(&$presenterSubmission, &$email));
			$email->send();

			$paperCommentDao =& DAORegistry::getDAO('PaperCommentDAO');
			$paperComment =& new PaperComment();
			$paperComment->setCommentType(COMMENT_TYPE_DIRECTOR_DECISION);
			$paperComment->setRoleId(ROLE_ID_PRESENTER);
			$paperComment->setPaperId($presenterSubmission->getPaperId());
			$paperComment->setAuthorId($presenterSubmission->getUserId());
			$paperComment->setCommentTitle($email->getSubject());
			$paperComment->setComments($email->getBody());
			$paperComment->setDatePosted(Core::getCurrentDate());
			$paperComment->setViewable(true);
			$paperComment->setAssocId($presenterSubmission->getPaperId());
			$paperCommentDao->insertPaperComment($paperComment);

			return true;
		} else {
			if (!Request::getUserVar('continued')) {
				$email->setSubject($presenterSubmission->getPaperTitle());
				if (!empty($directors)) {
					foreach ($directors as $director) {
						$email->addRecipient($director->getEmail(), $director->getFullName());
					}
				} else {
					$email->addRecipient($conference->getSetting('contactEmail'), $conference->getSetting('contactName'));
				}
			}

			$email->displayEditForm(Request::url(null, null, null, 'emailDirectorDecisionComment', 'send'), array('paperId' => $presenterSubmission->getPaperId()), 'submission/comment/directorDecisionEmail.tpl');

			return false;
		}
	}
	
	//
	// Misc
	//
	
	/**
	 * Download a file an presenter has access to.
	 * @param $paper object
	 * @param $fileId int
	 * @param $revision int
	 * @return boolean
	 * TODO: Complete list of files presenter has access to
	 */
	function downloadPresenterFile($paper, $fileId, $revision = null) {
		$presenterSubmissionDao = &DAORegistry::getDAO('PresenterSubmissionDAO');		

		$submission =& $presenterSubmissionDao->getPresenterSubmission($paper->getPaperId());
		$layoutAssignment =& $submission->getLayoutAssignment();

		$canDownload = false;
		
		// Presenters have access to:
		// 1) The original submission file.
		// 2) Any files uploaded by the reviewers that are "viewable",
		//    although only after a decision has been made by the director.
		// 4) Any of the presenter-revised files.
		// 5) The layout version of the file.
		// 6) Any supplementary file
		// 7) Any galley file
		// 8) All review versions of the file
		// 9) Current director versions of the file
		// THIS LIST SHOULD NOW BE COMPLETE.
		if ($submission->getSubmissionFileId() == $fileId) {
			$canDownload = true;
		} else if ($submission->getRevisedFileId() == $fileId) {
			$canDownload = true;
		} else if ($layoutAssignment->getLayoutFileId() == $fileId) {
			$canDownload = true;
		} else {
			// Check reviewer files
			foreach ($submission->getReviewAssignments(null, null) as $typeReviewAssignments) {
				foreach($typeReviewAssignments as $roundReviewAssignments) {
					foreach ($roundReviewAssignments as $reviewAssignment) {
						if ($reviewAssignment->getReviewerFileId() == $fileId) {
							$paperFileDao = &DAORegistry::getDAO('PaperFileDAO');
						
							$paperFile = &$paperFileDao->getPaperFile($fileId, $revision);
						
							if ($paperFile != null && $paperFile->getViewable()) {
								$canDownload = true;
							}
						}
					}
				}
			}
			
			// Check supplementary files
			foreach ($submission->getSuppFiles() as $suppFile) {
				if ($suppFile->getFileId() == $fileId) {
					$canDownload = true;
				}
			}
			
			// Check galley files
			foreach ($submission->getGalleys() as $galleyFile) {
				if ($galleyFile->getFileId() == $fileId) {
					$canDownload = true;
				}
			}

			// Check current review version
			$reviewAssignmentDao = &DAORegistry::getDAO('ReviewAssignmentDAO');
			$reviewFilesByRound =& $reviewAssignmentDao->getReviewFilesByRound($paper->getPaperId());
			$reviewFile = @$reviewFilesByRound[$paper->getCurrentRound()];
			if ($reviewFile && $fileId == $reviewFile->getFileId()) {
				$canDownload = true;
			}

			// Check director version
			$directorFiles = $submission->getDirectorFileRevisions($paper->getCurrentRound());
			foreach ($directorFiles as $directorFile) {
				if ($directorFile->getFileId() == $fileId) {
					$canDownload = true;
				}
			}
		}
		
		$result = false;
		if (!HookRegistry::call('PresenterAction::downloadPresenterFile', array(&$paper, &$fileId, &$revision, &$canDownload, &$result))) {
			if ($canDownload) {
				return Action::downloadFile($paper->getPaperId(), $fileId, $revision);
			} else {
				return false;
			}
		}
		return $result;
	}
}

?>
