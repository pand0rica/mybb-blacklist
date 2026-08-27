<form method="post" action="{$mybb->settings['bburl']}/blacklist.php" style="display:inline">
<input type="hidden" name="my_post_key" value="{$post_key}">
<input type="hidden" name="action" value="resolve">
<input type="hidden" name="entry_id" value="{$entry_id}">
<button type="submit" class="button"><svg viewBox="0 0 16 16" {$svg_attrs} aria-hidden="true"><polyline points="3,8 6,12 13,4"/></svg> {$lang->blacklist_btn_im_active}</button>
</form>
