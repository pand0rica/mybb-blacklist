<div class="tborder blacklist-add-bar">
<form method="post" action="{$mybb->settings['bburl']}/blacklist.php" class="blacklist-add-form">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="action" value="add" />
<label class="smalltext">{$lang->blacklist_lbl_uid}<input type="number" name="uid" min="1" required="required" class="textbox blacklist-add-number" /></label>
<label class="smalltext">{$lang->blacklist_lbl_category}<select name="category_id">{$all_cats_options}</select></label>
<label class="smalltext">{$lang->blacklist_lbl_thread}<input type="number" name="thread_id" min="0" value="0" class="textbox blacklist-add-number" /></label>
<input type="submit" class="button" value="+ {$lang->blacklist_btn_add}" />
</form>
</div>
