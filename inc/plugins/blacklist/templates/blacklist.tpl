<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->blacklist_title}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
{$usercpnav}
<td valign="top">

<table border="0" cellspacing="1" cellpadding="6" class="tborder" style="margin-bottom:12px">
<thead>
<tr>
<td class="thead">
<strong>{$lang->blacklist_title}</strong>
<span class="float_right smalltext" style="line-height:1.8">
<span class="blacklist-nav-active">{$lang->blacklist_nav_current}</span>
<span class="blacklist-nav-sep">&middot;</span>
<a href="{$activity_url}">{$lang->blacklist_nav_activity}</a>
</span>
</td>
</tr>
</thead>
{$intro_html}
</table>

{$run_notice_html}
{$run_actions_html}
{$add_form_html}

{$deadline_info_html}
{$categories_html}
{$closed_run_html}
{$no_entries_html}

</td>
</tr>
</table>
{$footer}
</body>
</html>
