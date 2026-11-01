<div class="blacklist-run-actions">
<form method="post" action="{$mybb->settings['bburl']}/blacklist.php" class="blacklist-inline-form"{$run_confirm}>
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="action" value="{$run_action}" />
<input type="submit" class="button" value="{$run_label}" />
</form>
</div>
