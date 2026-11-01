<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->blacklist_debug_title}</title>
{$headerinclude}
</head>
<body>
{$header}
<table border="0" cellspacing="1" cellpadding="6" class="tborder" style="margin-bottom:12px">
<tr>
<td class="thead"><strong>{$lang->blacklist_debug_title}</strong>
<span class="float_right smalltext"><a href="{$mybb->settings['bburl']}/blacklist.php?action=activity">&larr; {$lang->blacklist_debug_back}</a></span></td>
</tr>
<tr>
<td class="trow2 smalltext">{$lang->blacklist_debug_legend}:
<span class="blacklist-debug-solo">{$lang->blacklist_debug_legend_solo}</span>
<span class="blacklist-debug-not-next">{$lang->blacklist_debug_legend_not_next}</span>
<span class="blacklist-debug-hit">{$lang->blacklist_debug_legend_hit}</span>
<span class="blacklist-debug-ok">{$lang->blacklist_debug_legend_ok}</span></td>
</tr>
</table>
<table border="0" cellspacing="1" cellpadding="4" class="tborder blacklist-debug-table">
<tr>
<td class="tcat">{$lang->blacklist_debug_col_category}</td>
<td class="tcat">{$lang->blacklist_debug_col_user}</td>
<td class="tcat">scene_id</td>
<td class="tcat">tid</td>
<td class="tcat">free_order</td>
<td class="tcat">{$lang->blacklist_debug_col_participants}</td>
<td class="tcat">next_poster_uid</td>
<td class="tcat">{$lang->blacklist_debug_col_last_post}</td>
<td class="tcat">{$lang->blacklist_debug_col_days}</td>
<td class="tcat">{$lang->blacklist_debug_col_threshold}</td>
<td class="tcat">{$lang->blacklist_debug_col_condition}</td>
<td class="tcat">{$lang->blacklist_debug_col_skip}</td>
</tr>
{$debug_rows}
</table>
{$footer}
</body>
</html>
