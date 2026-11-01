<form method="post" action="{$mybb->settings['bburl']}/blacklist.php" class="blacklist-inline-form"{$button_confirm}>
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="action" value="{$button_action}" />
<input type="hidden" name="entry_id" value="{$entry_id}" />
<input type="submit" class="button" value="{$button_label}" />
</form>
