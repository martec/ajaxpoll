<?php
/**
 *	Ajax Poll
 *
 *	Ajax Poll for MyBB 1.8
 *
 * @Ajax Poll
 * @author	martec
 * @license http://www.gnu.org/copyleft/gpl.html GPLv3 license
 * @version 1.0
 * @Special Thanks: Aries-Belgium http://mods.mybb.com/view/ajax-poll-voting
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

define('AJAXPOLL_VERSION', '1.0');

$plugins->add_hook('polls_vote_end', 'ajaxpoll_voted');
$plugins->add_hook('polls_do_undovote_end', 'ajaxpoll_undo_vote');
$plugins->add_hook('polls_showresults_end', 'ajaxpoll_showresults');

// Plugin info
function ajaxpoll_info ()
{
	return array(
		"name"			  => "Ajax Poll",
		"description"	 => 'Ajax Poll for MyBB 1.8',
		"website"		 => "",
		"author"		=> "martec",
		"authorsite"	=> "",
		"version"		 => AJAXPOLL_VERSION,
		"guid"			   => "",
		"compatibility" => "18*"
	);
}

/**
 * The activation function for the plugin system
 */
function ajaxpoll_activate()
{
	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("showthread_poll", "#^(.*)$#si", '<div id="ajaxpoll">$1</div>');
	find_replace_templatesets("showthread_poll_results", "#^(.*)$#si", '<div id="ajaxpoll">$1</div>');
	find_replace_templatesets(
		'showthread',
		'#' . preg_quote('</head>') . '#i',
		'<script type="text/javascript" src="{$mybb->asset_url}/jscripts/jquery.ajaxpoll.js"></script>
</head>'
	);
}

/**
 * The activation function for the plugin system
 */
function ajaxpoll_deactivate()
{
	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets(
		'showthread',
		'#' . preg_quote('<script type="text/javascript" src="{$mybb->asset_url}/jscripts/jquery.ajaxpoll.js"></script>
</head>') . '#i',
		'</head>'
	);
	$search = array(
		"#^".preg_quote('<div id="ajaxpoll">')."#si",
		"#".preg_quote('</div>')."$#si"
	);
	find_replace_templatesets("showthread_poll", $search, "", 0);
	find_replace_templatesets("showthread_poll_results", $search, "", 0);
}

/**
 * Implementation of the ajaxpoll_vote_end hook
 *
 * Show the results after successfully voting
 */
function ajaxpoll_voted()
{
	global $mybb;

	if(isset($mybb->input['ajax']) && $mybb->input['ajax'] == 1)
	{
		global $poll, $updatedpoll;

		$poll['votes'] = $updatedpoll['votes'];
		$pollbox = ajaxpoll_pollbox($poll);
		print $pollbox;
		die();
	}
}

/**
 * Implementation of the polls_do_undovote_end hook
 *
 * After removing the vote, return the pollbox again.
 */
function ajaxpoll_undo_vote()
{
	global $mybb;

	if(isset($mybb->input['ajax']) && $mybb->input['ajax'] == 1)
	{
		global $poll, $updatedpoll;

		$poll['votes'] = $updatedpoll['votes'];
		$pollbox = ajaxpoll_pollbox($poll);
		print $pollbox;
		die();
	}
}

/**
 * Implementation of the polls_showresults_end hook
 *
 * Show the poll results
 */
function ajaxpoll_showresults()
{
	global $mybb;

	if(isset($mybb->input['ajax']) && $mybb->input['ajax'] == 1)
	{
		global $poll, $theme, $lang, $templates, $polloptions, $totpercent;

		$lang->load("showthread");
		$lang->load("misc");

		$thread = get_thread($poll['tid']);

		// Check if user is allowed to edit posts; if so, show "edit poll" link.
		if(!is_moderator($thread['fid'], 'caneditposts'))
		{
			$edit_poll = '';
		}
		else
		{
			$edit_poll = " | <a href=\"polls.php?action=editpoll&amp;pid={$poll['pid']}\">{$lang->edit_poll}</a>";
		}

		$poll['question'] = htmlspecialchars_uni($poll['question']);
		eval("\$showresults = \"".$templates->get("polls_showresults")."\";");

		if(preg_match("/(\<table.*?\<\/table\>)/si", $showresults, $matches))
		{
			$showresults = $matches[1];
			$showresults .= '<table cellspacing="0" cellpadding="2" border="0" width="100%" align="center">'
				.'<tr><td align="left"><span class="smalltext">'.$lang->you_voted.'</span></td>'
				.'<td align="right"><span class="smalltext">[<a href="showthread.php?tid='.$poll['tid'].'" id="ajaxpoll_back">'.$lang->close.'</a>'.$edit_poll.']</span></td>'
				.'</tr></table><br />';

			print '<div id="ajaxpoll_results">'.$showresults.'</div>';
			die();
		}
	}
}

/**
 * Prepare the pollbox
 *
 *
 */
function ajaxpoll_pollbox($poll)
{
	global $mybb, $db, $lang, $theme, $templates, $plugins, $parser;

	$lang->load("showthread");

	$thread = get_thread($poll['tid']);
	$forum = get_forum($thread['fid']);

	// --- START MYBB CODE ---
	$poll['timeout'] = $poll['timeout']*60*60*24;
	$expiretime = $poll['dateline'] + $poll['timeout'];
	$now = TIME_NOW;

	// If the poll or the thread is closed or if the poll is expired, show the results.
	if($poll['closed'] == 1 || $thread['closed'] == 1 || ($expiretime < $now && $poll['timeout'] > 0))
	{
		$showresults = 1;
	}

	// If the user is not a guest, check if he already voted.
	if($mybb->user['uid'] != 0)
	{
		$query = $db->simple_select("pollvotes", "*", "uid='".$mybb->user['uid']."' AND pid='".$poll['pid']."'");
		while($votecheck = $db->fetch_array($query))
		{
			$alreadyvoted = 1;
			$votedfor[$votecheck['voteoption']] = 1;
		}
	}
	else
	{
		if(isset($mybb->cookies['pollvotes'][$poll['pid']]) && $mybb->cookies['pollvotes'][$poll['pid']] !== "")
		{
			$alreadyvoted = 1;
		}
	}
	$optionsarray = explode("||~|~||", $poll['options']);
	$votesarray = explode("||~|~||", $poll['votes']);
	$poll['question'] = htmlspecialchars_uni($poll['question']);
	$polloptions = '';
	$totalvotes = 0;
	$poll['totvotes'] = 0;

	for($i = 1; $i <= $poll['numoptions']; ++$i)
	{
		$poll['totvotes'] = $poll['totvotes'] + $votesarray[$i-1];
	}

	// Loop through the poll options.
	for($i = 1; $i <= $poll['numoptions']; ++$i)
	{
		// Set up the parser options.
		$parser_options = array(
			"allow_html" => $forum['allowhtml'],
			"allow_mycode" => $forum['allowmycode'],
			"allow_smilies" => $forum['allowsmilies'],
			"allow_imgcode" => $forum['allowimgcode'],
			"allow_videocode" => $forum['allowvideocode'],
			"filter_badwords" => 1
		);

		if($mybb->user['showimages'] != 1 && $mybb->user['uid'] != 0 || $mybb->settings['guestimages'] != 1 && $mybb->user['uid'] == 0)
		{
			$parser_options['allow_imgcode'] = 0;
		}

		if($mybb->user['showvideos'] != 1 && $mybb->user['uid'] != 0 || $mybb->settings['guestvideos'] != 1 && $mybb->user['uid'] == 0)
		{
			$parser_options['allow_videocode'] = 0;
		}

		$option = $parser->parse_message($optionsarray[$i-1], $parser_options);
		$votes = $votesarray[$i-1];
		$totalvotes += $votes;
		$number = $i;

		// Mark the option the user voted for.
		if(!empty($votedfor[$number]))
		{
			$optionbg = "trow2";
			$votestar = "*";
		}
		else
		{
			$optionbg = "trow1";
			$votestar = "";
		}

		// If the user already voted or if the results need to be shown, do so; else show voting screen.
		if(isset($alreadyvoted) || isset($showresults))
		{
			if((int)$votes == "0")
			{
				$percent = "0";
			}
			else
			{
				$percent = number_format($votes / $poll['totvotes'] * 100, 2);
			}
			$imagewidth = round($percent);
			eval("\$polloptions .= \"".$templates->get("showthread_poll_resultbit")."\";");
		}
		else
		{
			if($poll['multiple'] == 1)
			{
				eval("\$polloptions .= \"".$templates->get("showthread_poll_option_multiple")."\";");
			}
			else
			{
				eval("\$polloptions .= \"".$templates->get("showthread_poll_option")."\";");
			}
		}
	}

	// If there are any votes at all, all votes together will be 100%; if there are no votes, all votes together will be 0%.
	if($poll['totvotes'])
	{
		$totpercent = "100%";
	}
	else
	{
		$totpercent = "0%";
	}

	// Check if user is allowed to edit posts; if so, show "edit poll" link.
	$edit_poll = '';
	if(is_moderator($fid, 'canmanagepolls'))
	{
		eval("\$edit_poll = \"".$templates->get("showthread_poll_editpoll")."\";");
	}

	// Decide what poll status to show depending on the status of the poll and whether or not the user voted already.
	if(isset($alreadyvoted) || isset($showresults))
	{
		if($alreadyvoted)
		{
			$pollstatus = $lang->already_voted;

			if($mybb->usergroup['canundovotes'] == 1)
			{
				eval("\$pollstatus .= \"".$templates->get("showthread_poll_undovote")."\";");
			}
		}
		else
		{
			$pollstatus = $lang->poll_closed;
		}
		$lang->total_votes = $lang->sprintf($lang->total_votes, $totalvotes);
		eval("\$pollbox = \"".$templates->get("showthread_poll_results")."\";");
		$plugins->run_hooks("showthread_poll_results");
	}
	else
	{
		$closeon = '&nbsp;';
		if($poll['timeout'] != 0)
		{
			$closeon = $lang->sprintf($lang->poll_closes, my_date($mybb->settings['dateformat'], $expiretime));
		}

		$publicnote = '&nbsp;';
		if($poll['public'] == 1)
		{
			$publicnote = $lang->public_note;
		}

		eval("\$pollbox = \"".$templates->get("showthread_poll")."\";");
		$plugins->run_hooks("showthread_poll");
	}
	// --- END MYBB CODE ---

	return $pollbox;
}
?>
