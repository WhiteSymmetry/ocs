{**
 * peerReview.tpl
 *
 * Copyright (c) 2003-2005 The Public Knowledge Project
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Subtemplate defining the presenter's peer review table.
 *
 * $Id$
 *}

<a name="peerReview"></a>
<h3>{translate key="submission.peerReview"}</h3>

{assign var=start value="A"|ord}
{section name="round" loop=$submission->getCurrentRound()}
{assign var="round" value=$smarty.section.round.index+1}
{assign var="roundIndex" value=$smarty.section.round.index}
{assign var=presenterFiles value=$submission->getPresenterFileRevisions($round)}
{assign var=directorFiles value=$submission->getDirectorFileRevisions($round)}
{assign var="viewableFiles" value=$presenterViewableFilesByRound[$round]}

<h4>{translate key="submission.round" round=$round}</h4>

<table class="data" width="100%">
	<tr valign="top">
		<td class="label" width="20%">
			{translate key="submission.reviewVersion"}
		</td>
		<td class="value" width="80%">
			{assign var="reviewFile" value=$reviewFilesByRound[$round]}
			{if $reviewFile}
				<a href="{url op="downloadFile" path=$submission->getPaperId()|to_array:$reviewFile->getFileId():$reviewFile->getRevision()}" class="file">{$reviewFile->getFileName()|escape}</a>&nbsp;&nbsp;{$reviewFile->getDateModified()|date_format:$dateFormatShort}
			{else}
				{translate key="common.none"}
			{/if}
		</td>
	</tr>
	<tr valign="top">
		<td class="label" width="20%">
			{translate key="submission.initiated"}
		</td>
		<td class="value" width="80%">
			{if $reviewEarliestNotificationByRound[$round]}
				{$reviewEarliestNotificationByRound[$round]|date_format:$dateFormatShort}
			{else}
				&mdash;
			{/if}
		</td>
	</tr>
	<tr valign="top">
		<td class="label" width="20%">
			{translate key="submission.lastModified"}
		</td>
		<td class="value" width="80%">
			{if $reviewModifiedByRound[$round]}
				{$reviewModifiedByRound[$round]|date_format:$dateFormatShort}
			{else}
				&mdash;
			{/if}
		</td>
	</tr>
	<tr valign="top">
		<td class="label" width="20%">
			{translate key="common.uploadedFile"}
		</td>
		<td class="value" width="80%">
			{foreach from=$viewableFiles item=reviewerFiles key=reviewer}
				{foreach from=$reviewerFiles item=viewableFile key=key}
					{assign var=thisReviewer value=$start+$reviewer|chr}
					{translate key="user.role.reviewer"} {$thisReviewer}
					<a href="{url op="downloadFile" path=$submission->getPaperId()|to_array:$viewableFile->getFileId():$viewableFile->getRevision()}" class="file">{$viewableFile->getFileName()|escape}</a>&nbsp;&nbsp;{$viewableFile->getDateModified()|date_format:$dateFormatShort}<br />
				{/foreach}
			{foreachelse}
				{translate key="common.none"}
			{/foreach}
		</td>
	</tr>
	{if !$smarty.section.round.last}
		<tr valign="top">
			<td class="label" width="20%">
				{translate key="submission.directorVersion"}
			</td>
			<td class="value" width="80%">
				{foreach from=$directorFiles item=directorFile key=key}
					<a href="{url op="downloadFile" path=$submission->getPaperId()|to_array:$directorFile->getFileId():$directorFile->getRevision()}" class="file">{$directorFile->getFileName()|escape}</a>&nbsp;&nbsp;{$directorFile->getDateModified()|date_format:$dateFormatShort}<br />
				{foreachelse}
					{translate key="common.none"}
				{/foreach}
			</td>
		</tr>
		<tr valign="top">
			<td class="label" width="20%">
				{translate key="submission.presenterVersion"}
			</td>
			<td class="value" width="80%">
				{foreach from=$presenterFiles item=presenterFile key=key}
					<a href="{url op="downloadFile" path=$submission->getPaperId()|to_array:$presenterFile->getFileId():$presenterFile->getRevision()}" class="file">{$presenterFile->getFileName()|escape}</a>&nbsp;&nbsp;{$presenterFile->getDateModified()|date_format:$dateFormatShort}<br />
				{foreachelse}
					{translate key="common.none"}
				{/foreach}
			</td>
		</tr>
	{/if}
</table>

{if !$smarty.section.round.last}
	<div class="separator"></div>
{/if}

{/section}
