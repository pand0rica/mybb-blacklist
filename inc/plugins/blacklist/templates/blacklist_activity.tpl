<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->blacklist_activity_title}</title>
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
<strong>{$lang->blacklist_activity_title}</strong>
<span class="float_right smalltext" style="line-height:1.8">
<a href="{$mybb->settings['bburl']}/blacklist.php">{$lang->blacklist_nav_current}</a>
<span class="blacklist-nav-sep">&middot;</span>
<span class="blacklist-nav-active">{$lang->blacklist_nav_activity}</span>
</span>
</td>
</tr>
</thead>
<tr class="trow2">
<td class="smalltext blacklist-activity-hint">{$lang->blacklist_activity_hint}</td>
</tr>
</table>

{$categories_html}
{$no_entries_html}

</td>
</tr>
</table>
{$footer}
</body>
</html>
