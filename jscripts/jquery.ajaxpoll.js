// Credits: http://mods.mybb.com/view/ajax-poll-voting (modified by martec)
var AjaxPoll = {
	init: function(){
		$(document).ready(function(){
			$('<div/>', { id: 'spinner', class: 'top-right'}).appendTo('body');
			AjaxPoll.initVote();
			AjaxPoll.initUndo();
			AjaxPoll.initShow();
		});
	},

	initVote: function(){
		$('#ajaxpoll form').submit(function(event){
				event.preventDefault();
				var urlaj = $(this).attr('action');
				urlaj += urlaj.indexOf('?') == -1 ? "?" : "&";
				urlaj += $(this).serialize();
				urlaj += "&ajax=1";

				$.ajax({
					type: "post",
					dataType: "json",
					url: urlaj,
					beforeSend: function(){
						$('#spinner').jGrowl('<img src="images/spinner_big.gif" />');
					},
					success: function(resp) {
						$("#spinner .jGrowl-notification:last-child").remove();
						if(resp.hasOwnProperty("errors")) {
							$.jGrowl(resp.errors);
						}
						return false;
					},
					error: function(request) {
						setTimeout(function(){
							$("#spinner .jGrowl-notification:last-child").remove();
						}, 400);
						$('#ajaxpoll').html(request.responseText);
						AjaxPoll.initUndo();
						AjaxPoll.initShow();
					}
				});
				event.preventDefault();
				return false;
			});
	},

	initUndo: function(){
		$('#ajaxpoll a[href*="polls.php?action=do_undovote"]').click(function(event){
				event.preventDefault();
				var urlaj = $(this).attr('href');
				urlaj += "&ajax=1";		

				$.ajax({
					type: "post",
					dataType: "json",
					url: urlaj,
					beforeSend: function(){
						$('#spinner').jGrowl('<img src="images/spinner_big.gif" />');
					},
					success: function(resp) {
						$("#spinner .jGrowl-notification:last-child").remove();
						if(resp.hasOwnProperty("errors")) {
							$.jGrowl(resp.errors);
						}
						return false;
					},
					error: function(request) {
						setTimeout(function(){
							$("#spinner .jGrowl-notification:last-child").remove();
						}, 400);
						$('#ajaxpoll').html(request.responseText);
						AjaxPoll.initVote();
						AjaxPoll.initShow();
					}
				});
				event.preventDefault();
				return false;
		});
		return false;
	},

	initShow: function(){
		$('#ajaxpoll a[href*="polls.php?action=showresults"]').click(function(event){
				event.preventDefault();
				var urlaj = $(this).attr('href');
				urlaj += "&ajax=1";

				$.ajax({
					type: "post",
					dataType: "json",
					url: urlaj,
					beforeSend: function(){
						$('#spinner').jGrowl('<img src="images/spinner_big.gif" />');
					},
					success: function(resp) {
						$("#spinner .jGrowl-notification:last-child").remove();
						if(resp.hasOwnProperty("errors")) {
							$.jGrowl(resp.errors);
						}
						return false;
					},
					error: function(request) {
						setTimeout(function(){
							$("#spinner .jGrowl-notification:last-child").remove();
						}, 400);
						$('#ajaxpoll').hide();
						$('#ajaxpoll').before(request.responseText);
						if($('#ajaxpoll_back')){
							$('#ajaxpoll_back').click(function(event){
								$('#ajaxpoll').show();
								$('#ajaxpoll_results').remove();

								event.preventDefault();
								return false;
							});
						}
					}
				});
			event.preventDefault();
			return false;
		});
	}
};
AjaxPoll.init();